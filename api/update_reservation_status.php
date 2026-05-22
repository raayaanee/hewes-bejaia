<?php
/**
 * update_reservation_status.php — VERSION CORRIGÉE
 *
 * Correction : LEFT JOIN time_slots (au lieu de JOIN)
 * → Les réservations hébergement (time_slot_id fictif) ne plantent plus
 * → Fonctionne pour activités ET hébergements
 */

require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['reservation_id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'error' => 'Données manquantes']);
    exit;
}

$reservation_id = (int)$input['reservation_id'];
$new_status     = $input['status'];

if (!in_array($new_status, ['confirmed', 'cancelled', 'completed'])) {
    echo json_encode(['success' => false, 'error' => 'Statut invalide']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->beginTransaction();

    // ── CORRECTION : LEFT JOIN time_slots pour ne pas planter sur les hébergements ──
    $stmt = $db->prepare("
        SELECT r.*,
               a.name        AS activity_name,
               a.booking_type,
               acco.name     AS accommodation_name,
               ts.start_time,
               ts.end_time
        FROM reservations r
        JOIN      activities    a    ON r.activity_id      = a.id
        LEFT JOIN accommodations acco ON r.accommodation_id = acco.id
        LEFT JOIN time_slots    ts   ON r.time_slot_id     = ts.id
        WHERE r.id = :id
    ");
    $stmt->execute([':id' => $reservation_id]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'Réservation non trouvée']);
        exit;
    }

    $isAccommodation = !empty($reservation['accommodation_id']);

    // ── Vérifier qu'on ne marque pas comme terminé avant la fin ──────────
    // Activité  : compare sysdate avec reservation_date + end_time
    // Hébergement : compare sysdate avec check_out_date
    if ($new_status === 'completed') {
        if ($isAccommodation) {
            // Hébergement : check_out_date entier doit être passé (fin de journée = 23:59:59)
            if (!empty($reservation['check_out_date'])) {
                $end_ts = strtotime($reservation['check_out_date'] . ' 23:59:59');
                if (time() < $end_ts) {
                    $db->rollBack();
                    $label = date('d/m/Y', strtotime($reservation['check_out_date']));
                    echo json_encode(['success' => false, 'error' => "Impossible : la date de départ ({$label}) n'est pas encore passée"]);
                    exit;
                }
            }
        } else {
            // Activité : reservation_date + end_time doit être passé
            if (!empty($reservation['end_time'])) {
                $end_ts = strtotime($reservation['reservation_date'] . ' ' . $reservation['end_time']);
                if (time() < $end_ts) {
                    $db->rollBack();
                    $label = date('d/m/Y', strtotime($reservation['reservation_date']))
                           . ' à ' . substr($reservation['end_time'], 0, 5);
                    echo json_encode(['success' => false, 'error' => "Impossible : le créneau n'est pas encore terminé ({$label})"]);
                    exit;
                }
            }
        }
    }

    // Libérer les places lors d'une annulation d'activité
    if ($new_status === 'cancelled' && !$isAccommodation && $reservation['availability_id']) {
        $avStmt = $db->prepare("
            SELECT av.*, a.booking_type
            FROM availability av
            JOIN activities a ON av.activity_id = a.id
            WHERE av.id = :id
        ");
        $avStmt->execute([':id' => $reservation['availability_id']]);
        $availability = $avStmt->fetch(PDO::FETCH_ASSOC);

        if ($availability) {
            $unitsToFree = ($availability['booking_type'] === 'private') ? 1 : $reservation['participants'];
            $unitsToFree = min($unitsToFree, $availability['current_reservations'] ?? 0);
            $db->prepare("
                UPDATE availability
                SET current_reservations = GREATEST(0, current_reservations - :units)
                WHERE id = :id
            ")->execute([':units' => $unitsToFree, ':id' => $reservation['availability_id']]);
        }
    }

    // Libérer les dates bloquées lors d'une annulation hébergement
    if ($new_status === 'cancelled' && $isAccommodation) {
        $db->prepare("
            DELETE FROM accommodation_blocked_dates
            WHERE reservation_id = :rid
        ")->execute([':rid' => $reservation_id]);
    }

    // Mettre à jour le statut
    $db->prepare("UPDATE reservations SET status = :status WHERE id = :id")
       ->execute([':status' => $new_status, ':id' => $reservation_id]);

    $db->commit();

    // ── Message WhatsApp selon le type ──────────────────────
    if ($isAccommodation) {
        // Message adapté hébergement
        $accoName   = $reservation['accommodation_name'] ?? 'votre hébergement';
        $checkIn    = !empty($reservation['check_in_date'])
            ? date('d/m/Y', strtotime($reservation['check_in_date'])) : '—';
        $checkOut   = !empty($reservation['check_out_date'])
            ? date('d/m/Y', strtotime($reservation['check_out_date'])) : '—';
        $nights     = $reservation['nights'] ?? '—';

        if ($new_status === 'confirmed') {
            $msg  = "✅ *Réservation Hébergement Confirmée !*\n\n";
            $msg .= "Bonjour {$reservation['client_name']},\n\n";
            $msg .= "Votre réservation a été confirmée !\n\n";
            $msg .= "📋 *Code:* {$reservation['confirmation_code']}\n";
            $msg .= "🏠 *Logement:* {$accoName}\n";
            $msg .= "📅 *Arrivée:* {$checkIn}\n";
            $msg .= "📅 *Départ:* {$checkOut}\n";
            $msg .= "🌙 *Durée:* {$nights} nuit(s)\n";
            $msg .= "👥 *Personnes:* {$reservation['participants']}\n";
            $msg .= "💰 *Total:* " . number_format($reservation['total_price'], 0, ',', ' ') . " DA\n\n";
            $msg .= "📍 Béjaïa\n";
            $msg .= "📞 Contact: +213 775 654 995\n\n";
            $msg .= "Merci de votre confiance ! 🙏\nHawas Bjaya";
        } elseif ($new_status === 'completed') {
            $msg  = "🎉 *Merci pour votre séjour !*\n\n";
            $msg .= "Bonjour {$reservation['client_name']},\n\n";
            $msg .= "Nous espérons que vous avez passé un excellent séjour !\n\n";
            $msg .= "📋 *Réservation:* {$reservation['confirmation_code']}\n";
            $msg .= "🏠 *Logement:* {$accoName}\n\n";
            $msg .= "⭐ N'hésitez pas à nous laisser un avis !\n";
            $msg .= "À très bientôt !\nHawas Bjaya";
        } else {
            $msg  = "❌ *Réservation Annulée*\n\n";
            $msg .= "Bonjour {$reservation['client_name']},\n\n";
            $msg .= "Votre réservation hébergement a été annulée.\n\n";
            $msg .= "📋 *Code:* {$reservation['confirmation_code']}\n";
            $msg .= "🏠 *Logement:* {$accoName}\n";
            $msg .= "📅 *Arrivée prévue:* {$checkIn}\n\n";
            $msg .= "📞 Contact: +213 775 654 995\nHawas Bjaya";
        }
    } else {
        // Message activité (logique originale)
        $date_formatted = date('d/m/Y', strtotime($reservation['reservation_date']));
        $time_range     = !empty($reservation['start_time'])
            ? substr($reservation['start_time'], 0, 5) . ' - ' . substr($reservation['end_time'], 0, 5)
            : '—';

        if ($new_status === 'confirmed') {
            $msg  = "✅ *Réservation Confirmée !*\n\n";
            $msg .= "Bonjour {$reservation['client_name']},\n\n";
            $msg .= "Votre réservation a été confirmée !\n\n";
            $msg .= "📋 *Code:* {$reservation['confirmation_code']}\n";
            $msg .= "🎯 *Activité:* {$reservation['activity_name']}\n";
            $msg .= "📅 *Date:* {$date_formatted}\n";
            $msg .= "⏰ *Horaire:* {$time_range}\n";
            $msg .= "👥 *Participants:* {$reservation['participants']}\n";
            $msg .= "💰 *Total:* " . number_format($reservation['total_price'], 0, ',', ' ') . " DA\n\n";
            $msg .= "📍 Rendez-vous à Béjaïa\n";
            $msg .= "📞 Contact: +213 775 654 995\n\n";
            $msg .= "Merci de votre confiance ! 🙏\nHawas Bjaya - Béjaïa Adventures";
        } elseif ($new_status === 'completed') {
            $msg  = "🎉 *Merci pour votre visite !*\n\n";
            $msg .= "Bonjour {$reservation['client_name']},\n\n";
            $msg .= "Nous espérons que vous avez passé un excellent moment !\n\n";
            $msg .= "📋 *Réservation:* {$reservation['confirmation_code']}\n";
            $msg .= "🎯 *Activité:* {$reservation['activity_name']}\n";
            $msg .= "📅 *Date:* {$date_formatted}\n\n";
            $msg .= "⭐ N'hésitez pas à nous laisser un avis !\n";
            $msg .= "À très bientôt !\nHawas Bjaya - Béjaïa Adventures";
        } else {
            $msg  = "❌ *Réservation Annulée*\n\n";
            $msg .= "Bonjour {$reservation['client_name']},\n\n";
            $msg .= "Votre réservation a été annulée.\n\n";
            $msg .= "📋 *Code:* {$reservation['confirmation_code']}\n";
            $msg .= "🎯 *Activité:* {$reservation['activity_name']}\n";
            $msg .= "📅 *Date:* {$date_formatted}\n\n";
            $msg .= "📞 Contact: +213 775 654 995\nHawas Bjaya - Béjaïa Adventures";
        }
    }

    $phone_clean = preg_replace('/[^0-9]/', '', $reservation['client_phone']);
    echo json_encode([
        'success'      => true,
        'message'      => 'Statut mis à jour avec succès',
        'whatsapp_url' => "https://wa.me/{$phone_clean}?text=" . urlencode($msg)
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('update_reservation_status error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur: ' . $e->getMessage()]);
}
?>