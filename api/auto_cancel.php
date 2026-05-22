<?php
/**
 * auto_cancel.php  (VERSION FUSIONNÉE)
 *
 * Basé sur le fichier original qui fonctionnait.
 * Ajout : gestion des hébergements (nightly) via LEFT JOIN + check check_in_date
 */

$is_direct_url = (PHP_SAPI !== 'cli') && !defined('INCLUDED_FROM_ADMIN');
if ($is_direct_url) {
    $cron_key = $_GET['cron_key'] ?? '';
    if ($cron_key !== 'HB_SECRET_2024') {
        http_response_code(403);
        die('Accès refusé');
    }
}

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$cancelled_count = 0;
$errors          = [];
$log             = [];

try {
    $db->beginTransaction();

    // ════════════════════════════════════════════════════════
    // 1. ACTIVITÉS — pending dont le créneau est passé
    //    Même requête qu'avant, LEFT JOIN pour ne pas planter
    //    si time_slot_id est fictif
    // ════════════════════════════════════════════════════════
    $findStmt = $db->prepare("
        SELECT
            r.id,
            r.confirmation_code,
            r.client_name,
            r.client_phone,
            r.participants,
            r.availability_id,
            r.reservation_date,
            a.name        AS activity_name,
            a.booking_type,
            ts.start_time,
            ts.end_time,
            CONCAT(r.reservation_date, ' ', ts.end_time) AS slot_end_datetime
        FROM reservations r
        JOIN      activities a  ON r.activity_id  = a.id
        LEFT JOIN time_slots ts ON r.time_slot_id = ts.id
        WHERE r.status = 'pending'
          AND r.accommodation_id IS NULL
          AND (
            -- Créneau horaire passé
            (ts.end_time IS NOT NULL AND CONCAT(r.reservation_date, ' ', ts.end_time) < NOW())
            OR
            -- Journée complète passée (daily sans time_slot)
            (ts.end_time IS NULL AND r.reservation_date < CURDATE())
          )
        ORDER BY r.reservation_date ASC
    ");
    $findStmt->execute();
    $expiredActivities = $findStmt->fetchAll(PDO::FETCH_ASSOC);

    $log[] = "🔍 Activités expirées trouvées : " . count($expiredActivities);

    foreach ($expiredActivities as $reservation) {
        try {
            $cancelStmt = $db->prepare("
                UPDATE reservations SET status = 'cancelled'
                WHERE id = :id AND status = 'pending'
            ");
            $cancelStmt->execute([':id' => $reservation['id']]);

            if ($cancelStmt->rowCount() === 0) continue; // Déjà traitée

            if ($reservation['availability_id']) {
                $unitsToFree = ($reservation['booking_type'] === 'private')
                    ? 1
                    : (int)$reservation['participants'];

                $db->prepare("
                    UPDATE availability
                    SET current_reservations = GREATEST(0, current_reservations - :units)
                    WHERE id = :availability_id
                ")->execute([
                    ':units'           => $unitsToFree,
                    ':availability_id' => $reservation['availability_id']
                ]);
            }

            $cancelled_count++;
            $log[] = "✅ [Activité] Annulée : [{$reservation['confirmation_code']}] "
                   . "{$reservation['client_name']} - {$reservation['activity_name']} "
                   . "le {$reservation['reservation_date']}";

        } catch (Exception $e) {
            $errors[] = "❌ Erreur activité #{$reservation['id']} : " . $e->getMessage();
            error_log("auto_cancel: erreur activité #{$reservation['id']} : " . $e->getMessage());
        }
    }

    // ════════════════════════════════════════════════════════
    // 2. HÉBERGEMENTS — pending dont la date d'arrivée est passée
    //    (client pas venu, admin n'a pas confirmé)
    // ════════════════════════════════════════════════════════
    $findAccoStmt = $db->prepare("
        SELECT
            r.id,
            r.confirmation_code,
            r.client_name,
            r.client_phone,
            r.accommodation_id,
            r.check_in_date
        FROM reservations r
        WHERE r.status = 'pending'
          AND r.accommodation_id IS NOT NULL
          AND r.check_in_date < CURDATE()
        ORDER BY r.check_in_date ASC
    ");
    $findAccoStmt->execute();
    $expiredAccos = $findAccoStmt->fetchAll(PDO::FETCH_ASSOC);

    $log[] = "🔍 Hébergements expirés trouvés : " . count($expiredAccos);

    foreach ($expiredAccos as $reservation) {
        try {
            $cancelStmt = $db->prepare("
                UPDATE reservations SET status = 'cancelled'
                WHERE id = :id AND status = 'pending'
            ");
            $cancelStmt->execute([':id' => $reservation['id']]);

            if ($cancelStmt->rowCount() === 0) continue;

            // Libérer les dates bloquées
            $db->prepare("
                DELETE FROM accommodation_blocked_dates
                WHERE reservation_id = :rid
            ")->execute([':rid' => $reservation['id']]);

            $cancelled_count++;
            $log[] = "✅ [Hébergement] Annulée : [{$reservation['confirmation_code']}] "
                   . "{$reservation['client_name']} — arrivée prévue : {$reservation['check_in_date']}";

        } catch (Exception $e) {
            $errors[] = "❌ Erreur hébergement #{$reservation['id']} : " . $e->getMessage();
            error_log("auto_cancel: erreur hébergement #{$reservation['id']} : " . $e->getMessage());
        }
    }

    $db->commit();

    $log[] = "──────────────────────────────────────";
    $log[] = "✅ Total annulées : {$cancelled_count}";
    if (!empty($errors)) {
        $log[] = "⚠️  Erreurs : " . count($errors);
        foreach ($errors as $err) $log[] = "   " . $err;
    }

    $log_message = "[" . date('Y-m-d H:i:s') . "] AUTO-CANCEL\n" . implode("\n", $log);
    error_log($log_message);

    if ($is_direct_url) {
        header('Content-Type: application/json');
        echo json_encode([
            'success'     => true,
            'cancelled'   => $cancelled_count,
            'log'         => $log,
            'errors'      => $errors,
            'executed_at' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo implode("\n", $log) . "\n";
    }

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    $error_msg = "❌ Erreur critique auto_cancel : " . $e->getMessage();
    error_log($error_msg);

    if ($is_direct_url) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } else {
        echo $error_msg . "\n";
    }
}