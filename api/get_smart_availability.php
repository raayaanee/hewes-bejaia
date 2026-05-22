<?php
/**
 * get_smart_availability.php
 * 
 * Endpoint unifié — gère les 3 modes de réservation :
 *   • timeslot → crée automatiquement les créneaux depuis le planning
 *   • daily    → vérifie si la journée est disponible
 *   • nightly  → vérification via le système hébergement (séparé)
 * 
 * GET params: activity_id, date
 */

header('Content-Type: application/json');
require_once '../config/database.php';

if (empty($_GET['activity_id']) || empty($_GET['date'])) {
    echo json_encode(['success' => false, 'error' => 'Paramètres manquants: activity_id et date requis']);
    exit;
}

$activityId = (int)$_GET['activity_id'];
$date       = $_GET['date'];

// Validation date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    echo json_encode(['success' => false, 'error' => 'Date invalide']);
    exit;
}
if ($date < date('Y-m-d')) {
    echo json_encode(['success' => false, 'error' => 'Date passée']);
    exit;
}

try {
    $database = new Database();
    $db       = $database->getConnection();

    // ── Charger l'activité ──────────────────────────────────
    $act = $db->prepare("
        SELECT id, name, booking_mode, booking_type, duration_minutes, price,
               max_participants, min_participants
        FROM activities
        WHERE id = :id AND is_active = 1
    ");
    $act->execute([':id' => $activityId]);
    $activity = $act->fetch(PDO::FETCH_ASSOC);

    if (!$activity) {
        echo json_encode(['success' => false, 'error' => 'Activité introuvable ou inactive']);
        exit;
    }

    // ── Vérifier si la date est bloquée ────────────────────
    $blk = $db->prepare("
        SELECT id FROM activity_blocked_dates
        WHERE blocked_date = :d
          AND (activity_id = :a OR activity_id IS NULL)
        LIMIT 1
    ");
    $blk->execute([':d' => $date, ':a' => $activityId]);
    if ($blk->fetch()) {
        echo json_encode([
            'success'  => true,
            'mode'     => $activity['booking_mode'],
            'blocked'  => true,
            'slots'    => [],
            'message'  => 'Activité non disponible ce jour'
        ]);
        exit;
    }

    $mode = $activity['booking_mode'];

    // ════════════════════════════════════════════════════════
    // MODE : TIMESLOT
    // Auto-génère les créneaux depuis activity_schedule
    // ════════════════════════════════════════════════════════
    if ($mode === 'timeslot') {

        // Récupérer le planning du jour (day_of_week 0=tous, ou jour exact)
        $dayOfWeek = (int)date('N', strtotime($date)); // 1=Lun ... 7=Dim
        $sched = $db->prepare("
            SELECT open_time, close_time, max_capacity
            FROM activity_schedule
            WHERE activity_id = :a
              AND is_active = 1
              AND (day_of_week = 0 OR day_of_week = :d)
            ORDER BY day_of_week DESC
            LIMIT 1
        ");
        $sched->execute([':a' => $activityId, ':d' => $dayOfWeek]);
        $schedule = $sched->fetch(PDO::FETCH_ASSOC);

        if (!$schedule) {
            echo json_encode([
                'success' => true,
                'mode'    => $mode,
                'blocked' => false,
                'slots'   => [],
                'message' => 'Aucun planning défini pour ce jour'
            ]);
            exit;
        }

        // Calculer les créneaux automatiquement
        $duration  = (int)$activity['duration_minutes'];
        $maxCap    = (int)$schedule['max_capacity'];
        $openTS    = strtotime($date . ' ' . $schedule['open_time']);
        $closeTS   = strtotime($date . ' ' . $schedule['close_time']);

        // Récupérer les réservations existantes pour ce jour (depuis availability si elle existe)
        // On garde la table availability pour la compatibilité mais on la génère à la volée si absente
        $existingRes = $db->prepare("
            SELECT 
                av.time_slot_id,
                ts.start_time,
                ts.end_time,
                av.id          AS availability_id,
                av.max_participants,
                av.current_reservations,
                av.price_override
            FROM availability av
            JOIN time_slots ts ON av.time_slot_id = ts.id
            WHERE av.activity_id = :a
              AND av.date = :d
              AND av.is_available = 1
        ");
        $existingRes->execute([':a' => $activityId, ':d' => $date]);
        $existingMap = [];
        foreach ($existingRes->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existingMap[$row['start_time']] = $row;
        }

        // Générer les créneaux
        $slots = [];
        $current = $openTS;
        $slotIndex = 0;

        while (($current + $duration * 60) <= $closeTS) {
            $startTime = date('H:i:s', $current);
            $endTime   = date('H:i:s', $current + $duration * 60);
            $startLabel = date('H:i', $current);
            $endLabel   = date('H:i', $current + $duration * 60);

            if (isset($existingMap[$startTime])) {
                // Créneau déjà dans la base → utiliser ses données réelles
                $ex = $existingMap[$startTime];
                $available = (int)$ex['max_participants'] - (int)$ex['current_reservations'];
                $price     = $ex['price_override'] ?? $activity['price'];
                $slots[] = [
                    'availability_id'    => (int)$ex['availability_id'],
                    'time_slot_id'       => (int)$ex['time_slot_id'],
                    'start_time'         => $startTime,
                    'end_time'           => $endTime,
                    'label'              => "$startLabel – $endLabel",
                    'max_participants'   => (int)$ex['max_participants'],
                    'reserved'           => (int)$ex['current_reservations'],
                    'available_spots'    => max(0, $available),
                    'price'              => (float)$price,
                    'is_available'       => $available > 0,
                    'source'             => 'db'
                ];
            } else {
                // Créneau auto-généré depuis le planning → disponible, pas encore en base
                $slots[] = [
                    'availability_id'    => null,  // sera créé lors de la réservation
                    'time_slot_id'       => null,
                    'start_time'         => $startTime,
                    'end_time'           => $endTime,
                    'label'              => "$startLabel – $endLabel",
                    'max_participants'   => $maxCap,
                    'reserved'           => 0,
                    'available_spots'    => $maxCap,
                    'price'              => (float)$activity['price'],
                    'is_available'       => true,
                    'source'             => 'schedule'
                ];
            }

            $current += $duration * 60;
            $slotIndex++;
            if ($slotIndex > 50) break; // sécurité anti-boucle infinie
        }

        echo json_encode([
            'success'      => true,
            'mode'         => $mode,
            'blocked'      => false,
            'activity'     => $activity,
            'slots'        => $slots,
            'schedule'     => $schedule,
            'date'         => $date
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════════
    // MODE : DAILY (journée entière)
    // ════════════════════════════════════════════════════════
    if ($mode === 'daily') {

        $sched = $db->prepare("
            SELECT max_capacity FROM activity_schedule
            WHERE activity_id = :a AND is_active = 1
              AND (day_of_week = 0 OR day_of_week = :d)
            ORDER BY day_of_week DESC LIMIT 1
        ");
        $sched->execute([':a' => $activityId, ':d' => (int)date('N', strtotime($date))]);
        $schedule = $sched->fetch(PDO::FETCH_ASSOC);

        $maxCap = $schedule ? (int)$schedule['max_capacity'] : (int)$activity['max_participants'];

        // Chercher une availability existante pour ce jour (time_slot 10 = Journée Complète)
        $avail = $db->prepare("
            SELECT av.*, av.max_participants, av.current_reservations, av.price_override
            FROM availability av
            WHERE av.activity_id = :a AND av.date = :d AND av.is_available = 1
            LIMIT 1
        ");
        $avail->execute([':a' => $activityId, ':d' => $date]);
        $existing = $avail->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $reserved  = (int)$existing['current_reservations'];
            $cap       = (int)$existing['max_participants'];
            $price     = $existing['price_override'] ?? $activity['price'];
            $avId      = (int)$existing['id'];
            $tsId      = (int)$existing['time_slot_id'];
        } else {
            $reserved = 0;
            $cap      = $maxCap;
            $price    = (float)$activity['price'];
            $avId     = null;
            $tsId     = 10; // Journée Complète
        }

        $availableSpots = max(0, $cap - $reserved);

        echo json_encode([
            'success'         => true,
            'mode'            => $mode,
            'blocked'         => false,
            'activity'        => $activity,
            'date'            => $date,
            'availability_id' => $avId,
            'time_slot_id'    => $tsId,
            'max_participants' => $cap,
            'reserved'        => $reserved,
            'available_spots' => $availableSpots,
            'price'           => (float)$price,
            'is_available'    => $availableSpots > 0
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════════
    // MODE : NIGHTLY (hébergement)
    // → Utiliser l'endpoint accommodations/check_dates.php
    // ════════════════════════════════════════════════════════
    echo json_encode([
        'success' => true,
        'mode'    => 'nightly',
        'message' => 'Utiliser /api/accommodations/check_dates.php pour les hébergements'
    ]);

} catch (Exception $e) {
    error_log('get_smart_availability error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
?>
