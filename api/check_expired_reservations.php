<?php
/**
 * check_expired_reservations.php
 * 
 * Filet de sécurité : vérifie et annule les réservations expirées
 * à inclure dans les pages admin qui affichent des réservations.
 * 
 * Usage :
 *   require_once '../config/check_expired_reservations.php';
 *   checkAndCancelExpired($db);
 * 
 * Placer ce fichier dans : config/check_expired_reservations.php
 */

function checkAndCancelExpired(PDO $db): int
{
    // Throttle : ne vérifier qu'une fois toutes les 5 minutes par session
    // pour éviter de ralentir chaque chargement de page
    $lastCheck = $_SESSION['last_expired_check'] ?? 0;
    if ((time() - $lastCheck) < 300) {
        return 0; // Pas encore 5 minutes, on skip
    }
    $_SESSION['last_expired_check'] = time();

    $cancelled = 0;

    try {
        $db->beginTransaction();

        $findStmt = $db->prepare("
            SELECT
                r.id,
                r.availability_id,
                r.participants,
                a.booking_type
            FROM reservations r
            JOIN activities  a  ON r.activity_id  = a.id
            JOIN time_slots  ts ON r.time_slot_id  = ts.id
            WHERE r.status = 'pending'
              AND CONCAT(r.reservation_date, ' ', ts.end_time) < NOW()
        ");
        $findStmt->execute();
        $expired = $findStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expired as $r) {
            $upd = $db->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = :id AND status = 'pending'");
            $upd->execute([':id' => $r['id']]);

            if ($upd->rowCount() > 0 && $r['availability_id']) {
                $units = ($r['booking_type'] === 'private') ? 1 : (int)$r['participants'];
                $db->prepare("
                    UPDATE availability
                    SET current_reservations = GREATEST(0, current_reservations - :units)
                    WHERE id = :id
                ")->execute([':units' => $units, ':id' => $r['availability_id']]);
                $cancelled++;
            }
        }

        $db->commit();

        if ($cancelled > 0) {
            error_log("[checkExpired] {$cancelled} réservation(s) expirée(s) annulée(s) automatiquement");
        }

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("[checkExpired] Erreur : " . $e->getMessage());
    }

    return $cancelled;
}