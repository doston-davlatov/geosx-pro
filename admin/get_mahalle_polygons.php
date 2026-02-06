<?php
require_once "../connection/config.php";
$db = new Database();

$tuman_id = isset($_GET['tuman_id']) ? (int)$_GET['tuman_id'] : 0;
if (!$tuman_id) {
    echo json_encode([]);
    exit;
}

// mysqli bilan to‘g‘ri ishlash
$mahallelar = $db->select("mahallelar", "id, nomi, polygon", "tuman_id = ?", [$tuman_id], "i");

echo json_encode($mahallelar);
