<?php
header('Content-Type: application/json; charset=utf-8');

$pdo = new PDO("mysql:host=localhost;dbname=uzb_gis;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$mahalla_id = isset($_GET['mahalla_id']) ? intval($_GET['mahalla_id']) : 0;

if ($mahalla_id <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid mahalla ID"]);
    exit;
}

$query = "
    SELECT 
        YEAR(sodir_vaqti) AS yil,
        COUNT(*) AS soni
    FROM crimes
    WHERE mahalla_id = :mahalla_id
    GROUP BY YEAR(sodir_vaqti)
    ORDER BY yil ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute([":mahalla_id" => $mahalla_id]);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "success" => true,
    "data" => $data
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
