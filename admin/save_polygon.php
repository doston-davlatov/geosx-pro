<?php
require_once "config.php";
$db = new Database();

$data = json_decode(file_get_contents("php://input"), true);

if(isset($data['polygon'], $data['mahalle_id'])) {
    $mahalle_id = intval($data['mahalle_id']);
    $polygonJson = json_encode($data['polygon'], JSON_UNESCAPED_UNICODE);

    if($mahalle_id > 0 && $polygonJson) {
        // 's' — polygon uchun string, 'i' — id uchun integer
        $success = $db->update(
            "mahallelar",
            ['polygon' => $polygonJson],
            "id = ?",
            [$mahalle_id],
            "i"
        );
        echo json_encode(['status' => $success ? 'success' : 'error']);
        exit;
    }
}

echo json_encode(['status' => 'error']);
?>
