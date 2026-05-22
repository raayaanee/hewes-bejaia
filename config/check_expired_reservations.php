<?php

function checkAndCancelExpired(PDO $db): int
{
    // Throttle : une vérification toutes les 5 minutes par session
    $lastCheck = $_SESSION['last_expired_check'] ?? 0;
    if ((time() - $lastCheck) < 300) {
        return 0;
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
            JOIN      activities a  ON r.activity_id  = a.id
            LEFT JOIN time_slots ts ON r.time_slot_id = ts.id
            WHERE r.status = 'pending'
              AND r.accommodation_id IS NULL
              AND (
                (ts.end_time IS NOT NULL AND CONCAT(r.reservation_date, ' ', ts.end_time) < NOW())
                OR
                (ts.end_time IS NULL AND r.reservation_date < CURDATE())
              )
        ");
        $findStmt->execute();
        $expired = $findStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expired as $r) {
            $upd = $db->prepare("
                UPDATE reservations SET status = 'cancelled'
                WHERE id = :id AND status = 'pending'
            ");
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

        // ── . HÉBERGEMENTS expirés ──────────────────────────
        $findAccoStmt = $db->prepare("
            SELECT r.id, r.accommodation_id
            FROM reservations r
            WHERE r.status = 'pending'
              AND r.accommodation_id IS NOT NULL
              AND r.check_in_date < CURDATE()
        ");
        $findAccoStmt->execute();
        $expiredAccos = $findAccoStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expiredAccos as $r) {
            $upd = $db->prepare("
                UPDATE reservations SET status = 'cancelled'
                WHERE id = :id AND status = 'pending'
            ");
            $upd->execute([':id' => $r['id']]);

            if ($upd->rowCount() > 0) {
                // Libérer les dates bloquées
                $db->prepare("
                    DELETE FROM accommodation_blocked_dates
                    WHERE reservation_id = :rid
                ")->execute([':rid' => $r['id']]);
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