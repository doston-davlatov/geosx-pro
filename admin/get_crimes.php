<?php
include '../connection/config.php'; // DB bilan ulanish fayli

// Qaysi hudud bo‘yicha filtr qilish
$viloyat_id = isset($_GET['viloyat_id']) ? intval($_GET['viloyat_id']) : null;
$tuman_id = isset($_GET['tuman_id']) ? intval($_GET['tuman_id']) : null;
$mahalla_id = isset($_GET['mahalla_id']) ? intval($_GET['mahalla_id']) : null;

// So'rov tayyorlash
$query = "SELECT 
            COUNT(*) AS jinoyat_soni,
            viloyat_id,
            tuman_id,
            mahalla_id
          FROM crimes
          WHERE 1=1";

if ($viloyat_id) {
    $query .= " AND viloyat_id = $viloyat_id";
}
if ($tuman_id) {
    $query .= " AND tuman_id = $tuman_id";
}
if ($mahalla_id) {
    $query .= " AND mahalla_id = $mahalla_id";
}

$query .= " GROUP BY viloyat_id, tuman_id, mahalla_id";

$result = $conn->query($query);
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);
?>
