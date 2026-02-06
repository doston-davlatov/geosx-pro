<?php
header('Content-Type: application/json; charset=utf-8');

// ----------------------------
// 1. DB CONFIGURATION
// ----------------------------
$host = 'localhost';
$db   = 'uzb_gis';
$user = 'root';
$pass = ''; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// ----------------------------
// 2. INPUT PARAMETERS
// ----------------------------
$viloyat_id = isset($_GET['viloyat_id']) ? intval($_GET['viloyat_id']) : null;
$tuman_id   = isset($_GET['tuman_id']) ? intval($_GET['tuman_id']) : null;

// ----------------------------
// 3. QUERY PREPARATION
// ----------------------------
$query = "SELECT 
              m.id, 
              m.nomi,
              t.nomi AS tuman_nomi, 
              v.nomi AS viloyat_nomi
          FROM mahallelar m
          JOIN tumanlar t ON m.tuman_id = t.id
          JOIN viloyatlar v ON m.viloyat_id = v.id";

$conditions = [];
$params = [];

if ($viloyat_id !== null) {
    $conditions[] = "m.viloyat_id = :viloyat_id";
    $params[':viloyat_id'] = $viloyat_id;
}

if ($tuman_id !== null) {
    $conditions[] = "m.tuman_id = :tuman_id";
    $params[':tuman_id'] = $tuman_id;
}

if ($conditions) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

$query .= " ORDER BY m.nomi ASC";

// ----------------------------
// 4. EXECUTE QUERY
// ----------------------------
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $mahallalar = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'count' => count($mahallalar),
        'data' => $mahallalar
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
