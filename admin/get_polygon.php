<?php
require_once "../connection/config.php";
$db = new Database();

$id = intval($_GET['id'] ?? 0);

if($id > 0) {
    // Table nomi, columns, condition, params, types
    $row = $db->select("mahallelar", "polygon", "id = ?", [$id], "i");

    $polygon = isset($row[0]['polygon']) ? json_decode($row[0]['polygon'], true) : [];
    echo json_encode(['polygon' => $polygon]);
} else {
    echo json_encode(['polygon' => []]);
}
?>
