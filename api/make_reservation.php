<?php
/**
 * make_reservation.php  (VERSION FINALE)
 *
 * Résolution SQLSTATE 1048 (client_id NOT NULL) :
 *   1. Lit client_id depuis : input JSON → $_SESSION → lookup/création par email
 *   2. Ne nécessite aucun ALTER TABLE en production.
 *   3. booking-hebergement.js corrigé envoie aussi client_id.
 */

require_once '../config/database.php'; // gère session_name + session_start
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
error_log("📥 make_reservation: " . json_encode($input));

// ── Champs communs obligatoires ──────────────────────────────
foreach (['activity_id', 'client_name', 'client_email', 'client_phone', 'participants'] as $f) {
    if (empty($input[$f])) {
        echo json_encode(['success' => false, 'error' => "Champ requis manquant: $f"]);
        exit;
    }
}

try {
    $database = new Database();
    $db       = $database->getConnection();

    // ── Résoudre client_id ───────────────────────────────────
    // Priorité : payload JSON → session PHP → lookup/création par email
    $clientId = null;

    if (!empty($input['client_id']) && (int)$input['client_id'] > 0) {
        $clientId = (int)$input['client_id'];

    } elseif (!empty($_SESSION['client_id']) && (int)$_SESSION['client_id'] > 0) {
        $clientId = (int)$_SESSION['client_id'];

    } else {
        // Pas de session ni client_id : chercher par email ou créer guest
        $email = trim($input['client_email']);
        $stmt  = $db->prepare("SELECT id FROM clients WHERE email = :e LIMIT 1");
        $stmt->execute([':e' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $clientId = (int)$row['id'];
        } else {
            // Créer un compte guest minimal (email_verified = 0)
            $db->prepare("
                INSERT INTO clients (full_name, email, phone, password, is_active, email_verified, created_at)
                VALUES (:name, :email, :phone, :pwd, 1, 0, NOW())
            ")->execute([
                ':name'  => $input['client_name'],
                ':email' => $email,
                ':phone' => $input['client_phone'],
                ':pwd'   => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT)
            ]);
            $clientId = (int)$db->lastInsertId();
        }
    }

    if (!$clientId) {
        echo json_encode(['success' => false, 'error' => 'Impossible d\'identifier le client']);
        exit;
    }

    // ── Charger l'activité ───────────────────────────────────
    $actStmt = $db->prepare("SELECT * FROM activities WHERE id = :id AND is_active = 1");
    $actStmt->execute([':id' => (int)$input['activity_id']]);
    $activity = $actStmt->fetch(PDO::FETCH_ASSOC);

    if (!$activity) {
        echo json_encode(['success' => false, 'error' => 'Activité introuvable']);
        exit;
    }

    $mode         = $activity['booking_mode'] ?? 'timeslot';
    $participants = (int)$input['participants'];

    // ════════════════════════════════════════════════════════
    // MODE NIGHTLY — Hébergement
    // ════════════════════════════════════════════════════════
    if ($mode === 'nightly') {

        foreach (['accommodation_id', 'check_in_date', 'check_out_date'] as $f) {
            if (empty($input[$f])) {
                echo json_encode(['success' => false, 'error' => "Champ requis: $f"]);
                exit;
            }
        }

        $accoId   = (int)$input['accommodation_id'];
        $checkIn  = $input['check_in_date'];
        $checkOut = $input['check_out_date'];

        if ($checkIn >= $checkOut) {
            echo json_encode(['success' => false, 'error' => "Date départ doit être après arrivée"]);
            exit;
        }

        $nights = (int)round((strtotime($checkOut) - strtotime($checkIn)) / 86400);
        if ($nights < 1) {
            echo json_encode(['success' => false, 'error' => 'Durée minimum : 1 nuit']);
            exit;
        }

        $db->beginTransaction();

        $accStmt = $db->prepare("SELECT * FROM accommodations WHERE id = :id AND is_active = 1");
        $accStmt->execute([':id' => $accoId]);
        $acco = $accStmt->fetch(PDO::FETCH_ASSOC);

        if (!$acco) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => 'Hébergement introuvable']);
            exit;
        }
        if ($nights < (int)$acco['min_nights']) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => "Minimum {$acco['min_nights']} nuit(s)"]);
            exit;
        }
        if ($participants > (int)$acco['capacity']) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => "Capacité max: {$acco['capacity']} personnes"]);
            exit;
        }

        // Construire la liste des dates à bloquer (check_in inclus, check_out exclu)
        $cur = strtotime($checkIn);
        $end = strtotime($checkOut);
        $datesToCheck = [];
        while ($cur < $end) {
            $datesToCheck[] = date('Y-m-d', $cur);
            $cur += 86400;
        }

        // Vérifier disponibilité
        $ph = implode(',', array_fill(0, count($datesToCheck), '?'));
        $blkStmt = $db->prepare("
            SELECT blocked_date FROM accommodation_blocked_dates
            WHERE accommodation_id = ? AND blocked_date IN ($ph) LIMIT 1
        ");
        $blkStmt->execute(array_merge([$accoId], $datesToCheck));
        $blocked = $blkStmt->fetch();
        if ($blocked) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => "La date du {$blocked['blocked_date']} n'est pas disponible"]);
            exit;
        }

        $totalPrice = $nights * (float)$acco['price_per_night'];
        $code = 'HB' . strtoupper(substr(md5(uniqid()), 0, 8));

        $db->prepare("
            INSERT INTO reservations
              (client_id, availability_id, activity_id, accommodation_id,
               reservation_date, check_in_date, check_out_date, nights,
               time_slot_id, participants, total_price,
               client_name, client_phone, client_email, special_requests,
               confirmation_code, status)
            VALUES (:cid, 0, :acid, :acoid, :rdate, :cin, :cout, :nights,
                    7, :p, :tp, :name, :phone, :email, :notes, :code, 'pending')
        ")->execute([
            ':cid'    => $clientId,
            ':acid'   => (int)$activity['id'],
            ':acoid'  => $accoId,
            ':rdate'  => $checkIn,
            ':cin'    => $checkIn,
            ':cout'   => $checkOut,
            ':nights' => $nights,
            ':p'      => $participants,
            ':tp'     => $totalPrice,
            ':name'   => $input['client_name'],
            ':phone'  => $input['client_phone'],
            ':email'  => $input['client_email'],
            ':notes'  => $input['special_requests'] ?? '',
            ':code'   => $code
        ]);
        $reservationId = $db->lastInsertId();

        // Bloquer les dates
        $blkIns = $db->prepare("
            INSERT INTO accommodation_blocked_dates (accommodation_id, blocked_date, reservation_id, reason)
            VALUES (:acc, :d, :rid, 'reservation')
        ");
        foreach ($datesToCheck as $d) {
            $blkIns->execute([':acc' => $accoId, ':d' => $d, ':rid' => $reservationId]);
        }

        $db->commit();

        echo json_encode([
            'success'           => true,
            'reservation_id'    => (int)$reservationId,
            'confirmation_code' => $code,
            'accommodation'     => $acco['name'],
            'check_in'          => $checkIn,
            'check_out'         => $checkOut,
            'nights'            => $nights,
            'total_price'       => $totalPrice,
            'message'           => 'Réservation créée avec succès'
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════════
    // MODE DAILY — Journée complète
    // ════════════════════════════════════════════════════════
    if ($mode === 'daily') {

        if (empty($input['date'])) {
            echo json_encode(['success' => false, 'error' => 'Date requise']);
            exit;
        }
        $date = $input['date'];
        $db->beginTransaction();

        $avStmt = $db->prepare("
            SELECT * FROM availability
            WHERE activity_id = :a AND date = :d AND is_available = 1
            LIMIT 1 FOR UPDATE
        ");
        $avStmt->execute([':a' => (int)$activity['id'], ':d' => $date]);
        $avail = $avStmt->fetch(PDO::FETCH_ASSOC);

        if (!$avail) {
            $sc = $db->prepare("SELECT max_capacity FROM activity_schedule WHERE activity_id = :a AND is_active = 1 LIMIT 1");
            $sc->execute([':a' => (int)$activity['id']]);
            $sc     = $sc->fetch(PDO::FETCH_ASSOC);
            $maxCap = $sc ? (int)$sc['max_capacity'] : (int)$activity['max_participants'];
            $db->prepare("
                INSERT INTO availability (activity_id, date, time_slot_id, max_participants, current_reservations, is_available)
                VALUES (:a, :d, 10, :m, 0, 1)
            ")->execute([':a' => (int)$activity['id'], ':d' => $date, ':m' => $maxCap]);
            $availId  = $db->lastInsertId();
            $reserved = 0;
            $price    = (float)$activity['price'];
            $tsId     = 10;
        } else {
            $availId  = (int)$avail['id'];
            $maxCap   = (int)$avail['max_participants'];
            $reserved = (int)$avail['current_reservations'];
            $price    = $avail['price_override'] ?? (float)$activity['price'];
            $tsId     = (int)$avail['time_slot_id'];
        }

        $available = $maxCap - $reserved;
        if ($participants > $available) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => "Seulement $available place(s) disponible(s)"]);
            exit;
        }

        $totalPrice = $price * $participants;
        $code = 'HB' . strtoupper(substr(md5(uniqid()), 0, 8));

        $db->prepare("
            INSERT INTO reservations
              (client_id, availability_id, activity_id, reservation_date,
               time_slot_id, participants, total_price,
               client_name, client_phone, client_email, special_requests,
               confirmation_code, status)
            VALUES (:cid,:avid,:acid,:date,:tsid,:p,:tp,:name,:phone,:email,:notes,:code,'pending')
        ")->execute([
            ':cid'   => $clientId, ':avid'  => $availId,
            ':acid'  => (int)$activity['id'], ':date' => $date,
            ':tsid'  => $tsId,     ':p'     => $participants,
            ':tp'    => $totalPrice,':name'  => $input['client_name'],
            ':phone' => $input['client_phone'], ':email' => $input['client_email'],
            ':notes' => $input['special_requests'] ?? '', ':code' => $code
        ]);
        $reservationId = $db->lastInsertId();

        $db->prepare("UPDATE availability SET current_reservations = current_reservations + :p WHERE id = :id")
           ->execute([':p' => $participants, ':id' => $availId]);
        $db->commit();

        echo json_encode([
            'success'           => true,
            'reservation_id'    => (int)$reservationId,
            'confirmation_code' => $code,
            'date'              => $date,
            'total_price'       => $totalPrice
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════════
    // MODE TIMESLOT
    // ════════════════════════════════════════════════════════
    if (empty($input['date'])) {
        echo json_encode(['success' => false, 'error' => 'Date requise']);
        exit;
    }
    $date = $input['date'];
    $db->beginTransaction();

    if (!empty($input['availability_id'])) {
        // Créneau existant → lire start/end depuis time_slots via JOIN
        $avStmt = $db->prepare("
            SELECT av.*,
                   a.price           AS activity_price,
                   a.booking_type,
                   a.max_participants AS activity_max,
                   ts.start_time     AS ts_start,
                   ts.end_time       AS ts_end
            FROM availability av
            JOIN activities   a  ON av.activity_id  = a.id
            LEFT JOIN time_slots ts ON av.time_slot_id = ts.id
            WHERE av.id = :id FOR UPDATE
        ");
        $avStmt->execute([':id' => (int)$input['availability_id']]);
        $avail = $avStmt->fetch(PDO::FETCH_ASSOC);

        if (!$avail) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => 'Créneau introuvable']);
            exit;
        }

        $availId   = (int)$avail['id'];
        $maxCap    = (int)$avail['max_participants'];
        $reserved  = (int)$avail['current_reservations'];
        $price     = $avail['price_override'] ?? (float)$avail['activity_price'];
        $tsId      = (int)$avail['time_slot_id'];
        // DB en priorité, payload en fallback
        $startTime = !empty($avail['ts_start']) ? $avail['ts_start'] : ($input['start_time'] ?? '');
        $endTime   = !empty($avail['ts_end'])   ? $avail['ts_end']   : ($input['end_time']   ?? '');

    } else {
        // Créneau auto depuis planning
        $startTime = $input['start_time'] ?? '';
        $endTime   = $input['end_time']   ?? '';
        if (!$startTime || !$endTime) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => 'Créneau horaire requis']);
            exit;
        }

        $tsFind = $db->prepare("SELECT id FROM time_slots WHERE start_time = :s AND end_time = :e LIMIT 1");
        $tsFind->execute([':s' => $startTime, ':e' => $endTime]);
        $tsRow = $tsFind->fetch(PDO::FETCH_ASSOC);
        if ($tsRow) {
            $tsId = (int)$tsRow['id'];
        } else {
            $db->prepare("INSERT INTO time_slots (name, start_time, end_time, is_active) VALUES (:n,:s,:e,1)")
               ->execute([
                   ':n' => date('H:i', strtotime($startTime)) . ' – ' . date('H:i', strtotime($endTime)),
                   ':s' => $startTime, ':e' => $endTime
               ]);
            $tsId = (int)$db->lastInsertId();
        }

        $sc = $db->prepare("SELECT max_capacity FROM activity_schedule WHERE activity_id = :a AND is_active = 1 LIMIT 1");
        $sc->execute([':a' => (int)$activity['id']]);
        $sc     = $sc->fetch(PDO::FETCH_ASSOC);
        $maxCap = $sc ? (int)$sc['max_capacity'] : (int)$activity['max_participants'];

        $chk = $db->prepare("
            SELECT id, current_reservations FROM availability
            WHERE activity_id = :a AND date = :d AND time_slot_id = :t FOR UPDATE
        ");
        $chk->execute([':a' => (int)$activity['id'], ':d' => $date, ':t' => $tsId]);
        $ex = $chk->fetch(PDO::FETCH_ASSOC);

        if ($ex) {
            $availId  = (int)$ex['id'];
            $reserved = (int)$ex['current_reservations'];
        } else {
            $db->prepare("
                INSERT INTO availability (activity_id, date, time_slot_id, max_participants, current_reservations, is_available)
                VALUES (:a,:d,:t,:m,0,1)
            ")->execute([':a' => (int)$activity['id'], ':d' => $date, ':t' => $tsId, ':m' => $maxCap]);
            $availId  = (int)$db->lastInsertId();
            $reserved = 0;
        }
        $price = (float)$activity['price'];
    }

    $isPrivate = ($activity['booking_type'] === 'private');
    $available = $maxCap - $reserved;

    if ($isPrivate) {
        if ($available < 1) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => 'Plus de disponibilité pour ce créneau']);
            exit;
        }
        if ($participants > (int)$activity['max_participants']) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => "Max {$activity['max_participants']} personnes"]);
            exit;
        }
        $totalPrice = $price;
        $unitsToAdd = 1;
    } else {
        if ($participants > $available) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => "Seulement $available place(s)"]);
            exit;
        }
        $totalPrice = $price * $participants;
        $unitsToAdd = $participants;
    }

    $code = 'HB' . strtoupper(substr(md5(uniqid()), 0, 8));

    $db->prepare("
        INSERT INTO reservations
          (client_id, availability_id, activity_id, reservation_date,
           time_slot_id, participants, total_price,
           client_name, client_phone, client_email, special_requests,
           confirmation_code, status)
        VALUES (:cid,:avid,:acid,:date,:tsid,:p,:tp,:name,:phone,:email,:notes,:code,'pending')
    ")->execute([
        ':cid'   => $clientId, ':avid'  => $availId,
        ':acid'  => (int)$activity['id'], ':date' => $date,
        ':tsid'  => $tsId,     ':p'     => $participants,
        ':tp'    => $totalPrice,':name'  => $input['client_name'],
        ':phone' => $input['client_phone'], ':email' => $input['client_email'],
        ':notes' => $input['special_requests'] ?? '', ':code' => $code
    ]);
    $reservationId = $db->lastInsertId();

    $db->prepare("UPDATE availability SET current_reservations = current_reservations + :u WHERE id = :id")
       ->execute([':u' => $unitsToAdd, ':id' => $availId]);
    $db->commit();

    echo json_encode([
        'success'           => true,
        'reservation_id'    => (int)$reservationId,
        'confirmation_code' => $code,
        'total_price'       => $totalPrice,
        'date'              => $date,
        'time'              => $startTime
            ? date('H:i', strtotime($startTime)) . ' – ' . date('H:i', strtotime($endTime))
            : ''
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('make_reservation error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>