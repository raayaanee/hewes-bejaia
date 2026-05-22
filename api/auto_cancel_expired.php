<?php


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

$cancelled_count  = 0;
$errors           = [];
$log              = [];

try {
    $db->beginTransaction();

   
    $findQuery = "
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
        JOIN activities  a  ON r.activity_id  = a.id
        JOIN time_slots  ts ON r.time_slot_id  = ts.id
        WHERE r.status = 'pending'
          AND CONCAT(r.reservation_date, ' ', ts.end_time) < NOW()
        ORDER BY r.reservation_date ASC
    ";

    $findStmt = $db->prepare($findQuery);
    $findStmt->execute();
    $expired = $findStmt->fetchAll(PDO::FETCH_ASSOC);

    $log[] = "🔍 Réservations expirées trouvées : " . count($expired);

    foreach ($expired as $reservation) {
        try {
            // 1. Annuler la réservation
            $cancelStmt = $db->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = :id AND status = 'pending'");
            $cancelStmt->execute([':id' => $reservation['id']]);

            if ($cancelStmt->rowCount() === 0) {
                // Déjà traitée entre temps, on skip
                continue;
            }

            // 2. Libérer les places dans availability
            if ($reservation['availability_id']) {
                $unitsToFree = ($reservation['booking_type'] === 'private') ? 1 : (int)$reservation['participants'];

                $freeStmt = $db->prepare("
                    UPDATE availability
                    SET current_reservations = GREATEST(0, current_reservations - :units)
                    WHERE id = :availability_id
                ");
                $freeStmt->execute([
                    ':units'           => $unitsToFree,
                    ':availability_id' => $reservation['availability_id']
                ]);
            }

            $cancelled_count++;

            $log[] = "✅ Annulée : [{$reservation['confirmation_code']}] "
                   . "{$reservation['client_name']} - {$reservation['activity_name']} "
                   . "le {$reservation['reservation_date']} à {$reservation['start_time']}";

        } catch (Exception $e) {
            $errors[] = "❌ Erreur pour réservation #{$reservation['id']} : " . $e->getMessage();
            error_log("auto_cancel: erreur réservation #{$reservation['id']} : " . $e->getMessage());
        }
    }

    $db->commit();

    // Log final
    $log[] = "──────────────────────────────────────";
    $log[] = "✅ Total annulées : {$cancelled_count}";
    if (!empty($errors)) {
        $log[] = "⚠️  Erreurs : " . count($errors);
    }

    $log_message = "[" . date('Y-m-d H:i:s') . "] AUTO-CANCEL\n" . implode("\n", $log);
    error_log($log_message);

    // Réponse si appelé directement via URL (pas en include)
    if ($is_direct_url) {
        header('Content-Type: application/json');
        echo json_encode([
            'success'         => true,
            'cancelled'       => $cancelled_count,
            'log'             => $log,
            'errors'          => $errors,
            'executed_at'     => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        // Sortie console pour cron
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