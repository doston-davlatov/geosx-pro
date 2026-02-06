<?php
$conn = new mysqli("localhost", "root", "", "uzb_gis");
if ($conn->connect_error) {
    http_response_code(500);
    echo "MySQL ulanmadi: " . $conn->connect_error;
    exit;
}

$viloyat_id = isset($_GET['viloyat_id']) ? intval($_GET['viloyat_id']) : 0;
if ($viloyat_id <= 0) {
    echo "<div class='text-danger px-3 py-2'>Tuman topilmadi</div>";
    exit;
}

$stmt = $conn->prepare("SELECT id, nomi FROM tumanlar WHERE viloyat_id = ? ORDER BY nomi ASC");
$stmt->bind_param("i", $viloyat_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<div class='text-warning px-3 py-2'>Tuman topilmadi</div>";
    exit;
}

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $nomi = htmlspecialchars($row['nomi'], ENT_QUOTES);
    echo "<button onclick=\"setActiveButton(this); loadMahallalar($id, '$nomi')\">$nomi</button>";
}
?>
