<?php
/**
 * api/accommodations/blocked_dates.php
 * Gérer les dates bloquées pour un hébergement (admin)
 * 
 * GET  ?accommodation_id=X  → liste des dates bloquées
 * POST { action: 'block', accommodation_id, dates: ['2025-07-01',...], reason }
 * POST { action: 'unblock', accommodation_id, dates: [...] }
 * POST { action: 'block_range', accommodation_id, start_date, end_date, reason }
 */

header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
require_once '../../config/database.php';

try {
    $database = new Database();
    $db       = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $accoId = isset($_GET['accommodation_id']) ? (int)$_GET['accommodation_id'] : 0;
        if (!$accoId) {
            echo json_encode(['success' => false, 'error' => 'accommodation_id requis']); exit;
        }

        $month = $_GET['month'] ?? date('Y-m'); // format YYYY-MM
        $start = $month . '-01';
        $end   = date('Y-m-t', strtotime($start));

        $stmt = $db->prepare("
            SELECT bd.blocked_date, bd.reason, r.confirmation_code, r.client_name
            FROM accommodation_blocked_dates bd
            LEFT JOIN reservations r ON bd.reservation_id = r.id
            WHERE bd.accommodation_id = :id
              AND bd.blocked_date BETWEEN :s AND :e
            ORDER BY bd.blocked_date
        ");
        $stmt->execute([':id' => $accoId, ':s' => $start, ':e' => $end]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'blocked_dates' => $rows]);
        exit;
    }

    $input  = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $accoId = isset($input['accommodation_id']) ? (int)$input['accommodation_id'] : 0;

    if (!$accoId) {
        echo json_encode(['success' => false, 'error' => 'accommodation_id requis']); exit;
    }

    if ($action === 'block' || $action === 'block_range') {

        if ($action === 'block_range') {
            // Générer les dates entre start et end (inclus)
            $s   = strtotime($input['start_date']);
            $e   = strtotime($input['end_date']);
            $dates = [];
            $cur = $s;
            while ($cur <= $e) {
                $dates[] = date('Y-m-d', $cur);
                $cur += 86400;
            }
        } else {
            $dates = $input['dates'] ?? [];
        }

        $reason = $input['reason'] ?? 'Bloqué par admin';
        $ins = $db->prepare("
            INSERT IGNORE INTO accommodation_blocked_dates
              (accommodation_id, blocked_date, reason)
            VALUES (:acc, :d, :r)
        ");
        $count = 0;
        foreach ($dates as $d) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $ins->execute([':acc' => $accoId, ':d' => $d, ':r' => $reason]);
                $count++;
            }
        }
        echo json_encode(['success' => true, 'blocked_count' => $count]);

    } elseif ($action === 'unblock') {

        $dates = $input['dates'] ?? [];
        $del   = $db->prepare("
            DELETE FROM accommodation_blocked_dates
            WHERE accommodation_id = :acc AND blocked_date = :d
              AND reservation_id IS NULL
        ");
        $count = 0;
        foreach ($dates as $d) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $del->execute([':acc' => $accoId, ':d' => $d]);
                $count += $del->rowCount();
            }
        }
        echo json_encode(['success' => true, 'unblocked_count' => $count]);

    } else {
        echo json_encode(['success' => false, 'error' => 'Action inconnue']);
    }

} catch (Exception $e) {
    error_log('blocked_dates error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
?>
