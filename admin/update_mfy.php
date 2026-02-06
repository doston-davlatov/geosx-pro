<?php
session_start();
require_once "../connection/config.php";

$db = new Database();

$data = json_decode(file_get_contents("php://input"), true);

if (empty($_SESSION['loggedin'])) {
    header("Location: ../login/");
    exit;
}

$mysqli = new mysqli("172.16.5.163", "root", "", "uzb_gis");
if ($mysqli->connect_errno) die("MySQL ulanmadi: " . $mysqli->connect_error);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $nomi = $_POST['nomi'];
    $manzil = $_POST['manzil'];
    $tavsif = $_POST['tavsif'];

    $stmt = $mysqli->prepare("UPDATE mahallalar SET nomi=?, manzil=?, tavsif=? WHERE id=?");
    $stmt->bind_param("sssi", $nomi, $manzil, $tavsif, $id);
    if ($stmt->execute()) {
        header("Location: edit_mfy.php?id=$id&success=1");
        exit;
    } else {
        echo "Xatolik yuz berdi: " . $stmt->error;
    }
}

$id = intval($data['id']);
$nomi = $data['nomi'];
$viloyat_id = intval($data['viloyat_id']);
$tuman_id = intval($data['tuman_id']);
$polygon = $data['polygon'];

$geojson = [
    "type" => "Feature",
    "geometry" => [
        "type" => "Polygon",
        "coordinates" => $polygon
    ],
    "properties" => ["nomi" => $nomi]
];

$coords = $polygon[0];
$sumLat = $sumLng = 0;
foreach ($coords as $p) {
    $sumLng += $p[0];
    $sumLat += $p[1];
}

$center_lat = $sumLat / count($coords);
$center_lng = $sumLng / count($coords);

$db->update("mahallelar", [
    "nomi" => $nomi,
    "viloyat_id" => $viloyat_id,
    "tuman_id" => $tuman_id,
    "polygon" => json_encode($geojson, JSON_UNESCAPED_UNICODE),
    "markaz_lat" => $center_lat,
    "markaz_lng" => $center_lng
], "id = ?", [$id], "i");

echo json_encode(["status" => "success", "message" => "MFY muvaffaqiyatli yangilandi"]);
