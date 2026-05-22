<?php
/**
 * api/generate_availability.php
 * Génère automatiquement les enregistrements availability
 * pour une activité sur une période donnée.
 * 
 * POST {
 *   activity_id, start_date, end_date,
 *   max_capacity, open_time, close_time,
 *   overwrite (bool, default false)
 * }
 */
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['admin_id'])) { echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit; }
require_once '../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$actId = isset($input['activity_id']) ? (int)$input['activity_id'] : 0;
$start = $input['start_date'] ?? '';
$end   = $input['end_date']   ?? '';

if (!$actId || !$start || !$end) {
    echo json_encode(['success'=>false,'error'=>'activity_id, start_date, end_date requis']); exit;
}

try {
    $db  = (new Database())->getConnection();

    // Charger l'activité
    $act = $db->prepare("SELECT * FROM activities WHERE id=:id AND is_active=1");
    $act->execute([':id'=>$actId]);
    $activity = $act->fetch(PDO::FETCH_ASSOC);
    if (!$activity) { echo json_encode(['success'=>false,'error'=>'Activité introuvable']); exit; }

    $duration  = (int)$activity['duration_minutes'];
    $maxCap    = (int)($input['max_capacity'] ?? 10);
    $openTime  = $input['open_time']  ?? '08:00:00';
    $closeTime = $input['close_time'] ?? '18:00:00';
    $mode      = $activity['booking_mode'];
    $overwrite = !empty($input['overwrite']);

    $created  = 0;
    $skipped  = 0;

    // Itérer sur chaque jour
    $cur = strtotime($start);
    $endTS = strtotime($end);

    // Préparer le time_slot "Journée complète" pour mode daily
    if ($mode === 'daily') {
        $tsStmt = $db->prepare("SELECT id FROM time_slots WHERE id=10 LIMIT 1");
        $tsStmt->execute();
        $tsRow = $tsStmt->fetch();
        $dailyTsId = $tsRow ? 10 : null;
        if (!$dailyTsId) {
            $db->prepare("INSERT INTO time_slots (id,name,start_time,end_time,is_active) VALUES (10,'Journée Complète','08:00:00','18:00:00',1)")->execute();
            $dailyTsId = 10;
        }
    }

    $checkExist = $db->prepare("SELECT id FROM availability WHERE activity_id=:a AND date=:d AND time_slot_id=:t LIMIT 1");
    $insAvail   = $db->prepare("
        INSERT INTO availability (activity_id, date, time_slot_id, max_participants, current_reservations, is_available)
        VALUES (:a, :d, :t, :m, 0, 1)
    ");

    while ($cur <= $endTS) {
        $dateStr = date('Y-m-d', $cur);

        if ($mode === 'daily') {
            $checkExist->execute([':a'=>$actId,':d'=>$dateStr,':t'=>$dailyTsId]);
            if (!$checkExist->fetch() || $overwrite) {
                $insAvail->execute([':a'=>$actId,':d'=>$dateStr,':t'=>$dailyTsId,':m'=>$maxCap]);
                $created++;
            } else { $skipped++; }

        } elseif ($mode === 'timeslot') {
            $openTS  = strtotime($dateStr . ' ' . $openTime);
            $closeTS = strtotime($dateStr . ' ' . $closeTime);
            $slot    = $openTS;

            while (($slot + $duration * 60) <= $closeTS) {
                $slotStart = date('H:i:s', $slot);
                $slotEnd   = date('H:i:s', $slot + $duration * 60);

                // Trouver ou créer le time_slot
                $tsFind = $db->prepare("SELECT id FROM time_slots WHERE start_time=:s AND end_time=:e LIMIT 1");
                $tsFind->execute([':s'=>$slotStart,':e'=>$slotEnd]);
                $tsRow = $tsFind->fetch(PDO::FETCH_ASSOC);
                if ($tsRow) {
                    $tsId = (int)$tsRow['id'];
                } else {
                    $sLabel = date('H:i', $slot);
                    $eLabel = date('H:i', $slot + $duration * 60);
                    $db->prepare("INSERT INTO time_slots (name,start_time,end_time,is_active) VALUES (:n,:s,:e,1)")
                       ->execute([':n'=>"$sLabel – $eLabel",':s'=>$slotStart,':e'=>$slotEnd]);
                    $tsId = (int)$db->lastInsertId();
                }

                $checkExist->execute([':a'=>$actId,':d'=>$dateStr,':t'=>$tsId]);
                if (!$checkExist->fetch() || $overwrite) {
                    $insAvail->execute([':a'=>$actId,':d'=>$dateStr,':t'=>$tsId,':m'=>$maxCap]);
                    $created++;
                } else { $skipped++; }

                $slot += $duration * 60;
            }
        }
        $cur += 86400;
    }

    echo json_encode([
        'success' => true,
        'created' => $created,
        'skipped' => $skipped,
        'message' => "$created créneau(x) créé(s), $skipped ignoré(s) (déjà existants)"
    ]);

} catch (Exception $e) {
    error_log('generate_availability: ' . $e->getMessage());
    echo json_encode(['success'=>false,'error'=>'Erreur serveur: '.$e->getMessage()]);
}
?>
