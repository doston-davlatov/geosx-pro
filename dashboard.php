<?php
session_start();
// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['loggedin']) || empty($_SESSION['username'])) {
    header("Location: ../login/");
    exit;
}
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}
$mysqli = new mysqli("localhost", "qizilte7_uzb_gis", "Doston_Davlatov_2006", "qizilte7_uzb_gis");
if ($mysqli->connect_errno) {
    die("MySQL ulanmadi: " . $mysqli->connect_error);
}
// Joriy foydalanuvchi ma'lumotlari
$username = $_SESSION['username'];
$stmt = $mysqli->prepare("SELECT id, username, role, mahalla_id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
// Jadval nomlari
$tables = [
    'crimes' => ['nomi' => 'Jinoyatlar', 'icon' => 'handcuffs'],
    'nizokash_oilalar' => ['nomi' => 'Nizokash oilalar', 'icon' => 'users-slash'],
    'order_olganlar' => ['nomi' => 'Order olgan ayollar', 'icon' => 'medal'],
];
$selectedTable = $_GET['table'] ?? 'crimes';
if (!array_key_exists($selectedTable, $tables)) {
    $selectedTable = 'crimes';
}
$tableInfo = $tables[$selectedTable];
// Filtrlar
$viloyat_id = $_GET['viloyat'] ?? '';
$tuman_id = $_GET['tuman'] ?? '';
$mahalla_id = $_GET['mahalla'] ?? '';
// === DELETE ===
if (($_POST['action'] ?? '') === 'delete') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("CSRF xato");
    }
    $id = (int)$_POST['id'];
    $safeTable = $mysqli->real_escape_string($selectedTable);
    $mysqli->query("DELETE FROM `$safeTable` WHERE id = $id");
    header("Location: ?table=$selectedTable&viloyat=$viloyat_id&tuman=$tuman_id&mahalla=$mahalla_id");
    exit;
}
// === EDIT SAQLASH ===
if (($_POST['action'] ?? '') === 'edit') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("CSRF xato");
    }
    $id = (int)$_POST['id'];
    $safeTable = $mysqli->real_escape_string($selectedTable);
    if ($selectedTable === 'crimes') {
        $stmt = $mysqli->prepare("UPDATE crimes SET jk_modda=?, qismi=?, bandi=?, ogrilik_turi=?, sodir_vaqti=?, viloyat_id=?, tuman_id=?, mahalla_id=?, lat=?, lng=? WHERE id=?");
        $stmt->bind_param(
            "ssssssiiidi",
            $_POST['jk_modda'],
            $_POST['qismi'],
            $_POST['bandi'],
            $_POST['ogrilik_turi'],
            $_POST['sodir_vaqti'] ?: null,
            $_POST['viloyat_id'],
            $_POST['tuman_id'],
            $_POST['mahalla_id'],
            $_POST['lat'] ?: null,
            $_POST['lng'] ?: null,
            $id
        );
    } elseif ($selectedTable === 'nizokash_oilalar') {
        $stmt = $mysqli->prepare("UPDATE nizokash_oilalar SET fish=?, azolar_soni=?, sababi=?, sana=?, viloyat_id=?, tuman_id=?, mahalla_id=? WHERE id=?");
        $stmt->bind_param(
            "sisssiii",
            $_POST['fish'],
            $_POST['azolar_soni'],
            $_POST['sababi'],
            $_POST['sana'],
            $_POST['viloyat_id'],
            $_POST['tuman_id'],
            $_POST['mahalla_id'],
            $id
        );
    } elseif ($selectedTable === 'order_olganlar') {
        $stmt = $mysqli->prepare("UPDATE order_olganlar SET fish=?, order_nomi=?, order_darajasi=?, berilgan_sana=?, viloyat_id=?, tuman_id=?, mahalla_id=? WHERE id=?");
        $stmt->bind_param(
            "sssssiii",
            $_POST['fish'],
            $_POST['order_nomi'],
            $_POST['order_darajasi'],
            $_POST['berilgan_sana'],
            $_POST['viloyat_id'],
            $_POST['tuman_id'],
            $_POST['mahalla_id'],
            $id
        );
    }
    $stmt->execute();
    header("Location: ?table=$selectedTable&viloyat=$viloyat_id&tuman=$tuman_id&mahalla=$mahalla_id");
    exit;
}
// === EDIT MA'LUMOT OLISH ===
$editData = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $safeTable = $mysqli->real_escape_string($selectedTable);
    $stmt = $mysqli->prepare("SELECT * FROM `$safeTable` WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tableInfo['nomi'] ?> | GeoTizim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body {
            min-height: 100vh;
            overflow-x: hidden;
            color: white;
            background: radial-gradient(ellipse at bottom, #01021a 0%, #000000 100%);
            font-family: 'Segoe UI', sans-serif;
        }

        .sidebar {
            background: linear-gradient(180deg, #001b46 0%, #000e24 100%);
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.5);
            position: fixed;
            top: 0;
            left: 0;
            width: 270px;
            height: 100vh;
            padding-top: 25px;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar h2 {
            text-align: center;
            color: #00e5ff;
            font-size: 1.5rem;
            margin-bottom: 40px;
            font-weight: 600;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: #cfd8dc;
            text-decoration: none;
            border-left: 4px solid transparent;
            transition: all 0.3s;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(0, 229, 255, 0.15);
            color: #fff;
            border-left-color: #00e5ff;
        }

        .nav-link i {
            color: #00e5ff;
            width: 24px;
            text-align: center;
        }

        main {
            margin-left: 280px;
            padding: 40px;
        }

        .card-custom {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(0, 229, 255, 0.3);
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }

        .scroll-table {
            max-height: 65vh;
            overflow-y: auto;
        }

        .scroll-table::-webkit-scrollbar {
            width: 8px;
        }

        .scroll-table::-webkit-scrollbar-thumb {
            background: #00e5ff;
            border-radius: 4px;
        }

        #network-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -1;
        }

        #map {
            height: 320px;
            border-radius: 12px;
            margin-top: 10px;
        }

        .page-link {
            background: #001b46;
            border-color: #00e5ff;
            color: #00e5ff;
        }

        .page-item.active .page-link {
            background: #00e5ff;
            color: #000;
            border-color: #00e5ff;
        }
    </style>
</head>

<body>
    <canvas id="network-bg"></canvas>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>GeoTizim</h2>
        <a href="../" class="nav-link"><i class="fas fa-home"></i> Asosiy sahifa</a>
        <a href="?table=crimes" class="nav-link <?= $selectedTable == 'crimes' ? 'active' : '' ?>"><i class="fas fa-handcuffs"></i> Jinoyatlar</a>
        <a href="?table=nizokash_oilalar" class="nav-link <?= $selectedTable == 'nizokash_oilalar' ? 'active' : '' ?>"><i class="fas fa-users-slash"></i> Nizokash oilalar</a>
        <a href="?table=order_olganlar" class="nav-link <?= $selectedTable == 'order_olganlar' ? 'active' : '' ?>"><i class="fas fa-medal"></i> Order olganlar</a>
        <?php if (in_array($user['role'], ['admin', 'super_admin'])): ?>
            <a href="../admin/" class="nav-link"><i class="fas fa-cog"></i> Admin panel</a>
        <?php endif; ?>
        <hr class="text-info opacity-50">
        <small class="d-block text-center text-info"><?= htmlspecialchars($user['username']) ?><br>(<?= $user['role'] ?>)</small>
    </div>
    <main>
        <div class="container-fluid">
            <h1 class="mb-4"><?= $tableInfo['nomi'] ?></h1>
            <!-- Filtrlar -->
            <div class="card-custom p-4 mb-4">
                <form method="get" class="row g-3 align-items-end">
                    <input type="hidden" name="table" value="<?= $selectedTable ?>">
                    <div class="col-md-3">
                        <label class="form-label text-info">Viloyat</label>
                        <select name="viloyat" class="form-select bg-dark text-light border-info" onchange="this.form.submit()">
                            <option value="">Barchasi</option>
                            <?php
                            $res = $mysqli->query("SELECT id, nomi FROM viloyatlar ORDER BY nomi");
                            while ($v = $res->fetch_assoc()): ?>
                                <option value="<?= $v['id'] ?>" <?= $viloyat_id == $v['id'] ? 'selected' : '' ?>><?= $v['nomi'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-info">Tuman</label>
                        <select name="tuman" class="form-select bg-dark text-light border-info" onchange="this.form.submit()" <?= $viloyat_id ? '' : 'disabled' ?>>
                            <option value="">Barchasi</option>
                            <?php if ($viloyat_id):
                                $stmt = $mysqli->prepare("SELECT id, nomi FROM tumanlar WHERE viloyat_id = ? ORDER BY nomi");
                                $stmt->bind_param("i", $viloyat_id);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                while ($t = $res->fetch_assoc()): ?>
                                    <option value="<?= $t['id'] ?>" <?= $tuman_id == $t['id'] ? 'selected' : '' ?>><?= $t['nomi'] ?></option>
                            <?php endwhile;
                                $stmt->close();
                            endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-info">Mahalla</label>
                        <select name="mahalla" class="form-select bg-dark text-light border-info" onchange="this.form.submit()" <?= $tuman_id ? '' : 'disabled' ?>>
                            <option value="">Barchasi</option>
                            <?php if ($tuman_id):
                                $stmt = $mysqli->prepare("SELECT id, nomi FROM mahallelar WHERE tuman_id = ? ORDER BY nomi");
                                $stmt->bind_param("i", $tuman_id);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                while ($m = $res->fetch_assoc()): ?>
                                    <option value="<?= $m['id'] ?>" <?= $mahalla_id == $m['id'] ? 'selected' : '' ?>><?= $m['nomi'] ?></option>
                            <?php endwhile;
                                $stmt->close();
                            endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <a href="?table=<?= $selectedTable ?>" class="btn btn-outline-info">Filtrni tozalash</a>
                    </div>
                </form>
            </div>
            <?php
            // === Ma'lumotlarni olish ===
            $limit = 15;
            $page = max(1, (int)($_GET['page'] ?? 1));
            $offset = ($page - 1) * $limit;
            
            // === WHERE shartlarini yig'ish ===
            $whereConditions = [];
            $bindParams = [];
            $bindTypes = "";
            
           $tablePrefix = match ($selectedTable) {
            'crimes' => 'c',
            'nizokash_oilalar' => 'n',
            'order_olganlar' => 'o',
            default => 'c'
        };
            // Operator cheklovi
        if ($user['role'] === 'operator' && !empty($user['mahalla_id'])) {
            $whereConditions[] = "$tablePrefix.mahalla_id = ?";
            $bindParams[] = $user['mahalla_id'];
            $bindTypes .= "i";
        }
        
        // Filtrlar
        if (!empty($viloyat_id)) {
            $whereConditions[] = "$tablePrefix.viloyat_id = ?";
            $bindParams[] = $viloyat_id;
            $bindTypes .= "i";
        }
        if (!empty($tuman_id)) {
            $whereConditions[] = "$tablePrefix.tuman_id = ?";
            $bindParams[] = $tuman_id;
            $bindTypes .= "i";
        }
        if (!empty($mahalla_id)) {
            $whereConditions[] = "$tablePrefix.mahalla_id = ?";
            $bindParams[] = $mahalla_id;
            $bindTypes .= "i";
        }
        

            $whereClause = $whereConditions ? "WHERE " . implode(" AND ", $whereConditions) : "";
            $countSql = "SELECT COUNT(*) FROM `$selectedTable` $tablePrefix $whereClause";
            $stmt = $mysqli->prepare($countSql);
            if (!empty($bindParams)) {
                $stmt->bind_param($bindTypes, ...$bindParams);
            }
            $stmt->execute();
            $total = $stmt->get_result()->fetch_row()[0];
            $stmt->close();

            // === Ma'lumotlar so‘rovi (JOIN bilan, nomi uchun) ===
            if ($selectedTable === 'crimes') {
                $sql = "SELECT c.*, v.nomi AS viloyat, t.nomi AS tuman, m.nomi AS mahalla
            FROM crimes c
            LEFT JOIN viloyatlar v ON c.viloyat_id = v.id
            LEFT JOIN tumanlar t ON c.tuman_id = t.id
            LEFT JOIN mahallelar m ON c.mahalla_id = m.id
            $whereClause ORDER BY c.id DESC LIMIT ? OFFSET ?";
            } elseif ($selectedTable === 'nizokash_oilalar') {
                $sql = "SELECT n.*, v.nomi AS viloyat, t.nomi AS tuman, m.nomi AS mahalla
            FROM nizokash_oilalar n
            LEFT JOIN viloyatlar v ON n.viloyat_id = v.id
            LEFT JOIN tumanlar t ON n.tuman_id = t.id
            LEFT JOIN mahallelar m ON n.mahalla_id = m.id
            $whereClause ORDER BY n.id DESC LIMIT ? OFFSET ?";
            } else {
                $sql = "SELECT o.*, v.nomi AS viloyat, t.nomi AS tuman, m.nomi AS mahalla
            FROM order_olganlar o
            LEFT JOIN viloyatlar v ON o.viloyat_id = v.id
            LEFT JOIN tumanlar t ON o.tuman_id = t.id
            LEFT JOIN mahallelar m ON o.mahalla_id = m.id
            $whereClause ORDER BY o.id DESC LIMIT ? OFFSET ?";
            }

            // === Jami soni ===
            $stmt = $mysqli->prepare($sql);
            if (!empty($bindParams)) {
                $bindParams[] = $limit;
                $bindParams[] = $offset;
                $bindTypes .= "ii";
                $stmt->bind_param($bindTypes, ...$bindParams);
            } else {
                $stmt->bind_param("ii", $limit, $offset);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $pages = ceil($total / $limit);
            ?>
            <div class="card-custom p-4">
                <div class="scroll-table">
                    <table class="table table-hover text-light">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <?php if ($selectedTable === 'crimes'): ?>
                                    <th>JK modda</th>
                                    <th>Q/B</th>
                                    <th>Turi</th>
                                    <th>Vaqt</th>
                                <?php elseif ($selectedTable === 'nizokash_oilalar'): ?>
                                    <th>F.I.Sh.</th>
                                    <th>A'zolar</th>
                                    <th>Sabab</th>
                                    <th>Sana</th>
                                <?php else: ?>
                                    <th>F.I.Sh.</th>
                                    <th>Order</th>
                                    <th>Daraja</th>
                                    <th>Sana</th>
                                <?php endif; ?>
                                <th>Viloyat</th>
                                <th>Tuman</th>
                                <th>Mahalla</th>
                                <th>Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows == 0): ?>
                                <tr>
                                    <td colspan="12" class="text-center py-5 text-info">Ma'lumot topilmadi</td>
                                </tr>
                                <?php else: $no = $offset + 1;
                                while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <?php if ($selectedTable === 'crimes'): ?>
                                            <td><?= htmlspecialchars($row['jk_modda'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($row['qismi'] ?? '') . '/' . htmlspecialchars($row['bandi'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['ogrilik_turi'] ?? '-') ?></td>
                                            <td><?= $row['sodir_vaqti'] ? date('d.m.Y H:i', strtotime($row['sodir_vaqti'])) : '-' ?></td>
                                        <?php elseif ($selectedTable === 'nizokash_oilalar'): ?>
                                            <td><?= htmlspecialchars($row['fish']) ?></td>
                                            <td><?= $row['azolar_soni'] ?></td>
                                            <td><?= mb_substr(htmlspecialchars($row['sababi'] ?? ''), 0, 50) . (mb_strlen($row['sababi'] ?? '') > 50 ? '...' : '') ?></td>
                                            <td><?= date('d.m.Y', strtotime($row['sana'])) ?></td>
                                        <?php else: ?>
                                            <td><?= htmlspecialchars($row['fish']) ?></td>
                                            <td><?= htmlspecialchars($row['order_nomi']) ?></td>
                                            <td><?= htmlspecialchars($row['order_darajasi'] ?? '-') ?></td>
                                            <td><?= date('d.m.Y', strtotime($row['berilgan_sana'])) ?></td>
                                        <?php endif; ?>
                                        <td><?= htmlspecialchars($row['viloyat'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['tuman'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['mahalla'] ?? '-') ?></td>
                                        <td>
                                            <a href="?table=<?= $selectedTable ?>&edit=<?= $row['id'] ?>&viloyat=<?= $viloyat_id ?>&tuman=<?= $tuman_id ?>&mahalla=<?= $mahalla_id ?>&page=<?= $page ?>"
                                                class="btn btn-sm btn-outline-info">Tahrirlash</a>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Oʻchirishni tasdiqlang');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Oʻchirish</button>
                                            </form>
                                        </td>
                                    </tr>
                            <?php endwhile;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?table=<?= $selectedTable ?>&viloyat=<?= $viloyat_id ?>&tuman=<?= $tuman_id ?>&mahalla=<?= $mahalla_id ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <!-- EDIT MODAL -->
    <?php if ($editData && !empty($editData['mahalla_id'])): ?>
        <?php
        // Mahalla poligonini olish (faqat crimes uchun)
        $mahallaPolygon = null;
        
            $stmt = $mysqli->prepare("SELECT polygon FROM mahallelar WHERE id = ? ");
            $stmt->bind_param("i", $editData['mahalla_id']);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $mahallaPolygon = $res['polygon'] ?? null;
            $stmt->close();
        ?>
        <div class="modal fade show" id="editModal" style="display:block; background:rgba(0,0,0,0.95);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content bg-dark text-light">
                    <form method="post">
                        <div class="modal-header border-info">
                            <h5 class="modal-title">Ma'lumotni tahrirlash</h5>
                            <a href="?table=<?= $selectedTable ?>&viloyat=<?= $viloyat_id ?>&tuman=<?= $tuman_id ?>&mahalla=<?= $mahalla_id ?>&page=<?= $page ?>"
                                class="btn-close btn-close-white"></a>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= $editData['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <?php if ($selectedTable === 'crimes'): ?>
                                <div class="row g-3">
                                    <div class="col-md-6"><label>JK modda</label><input name="jk_modda" class="form-control bg-secondary text-white" value="<?= htmlspecialchars($editData['jk_modda'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label>Qism</label><input name="qismi" class="form-control bg-secondary text-white" value="<?= htmlspecialchars($editData['qismi'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label>Band</label><input name="bandi" class="form-control bg-secondary text-white" value="<?= htmlspecialchars($editData['bandi'] ?? '') ?>"></div>
                                    <div class="col-12"><label>O'g'rilik turi</label><input name="ogrilik_turi" class="form-control bg-secondary text-white" value="<?= htmlspecialchars($editData['ogrilik_turi'] ?? '') ?>"></div>
                                    <div class="col-12"><label>Sodir bo'lgan vaqt</label><input type="datetime-local" name="sodir_vaqti" class="form-control bg-secondary text-white" value="<?= $editData['sodir_vaqti'] ? str_replace(' ', 'T', $editData['sodir_vaqti']) : '' ?>"></div>
                                </div>
                            <?php elseif ($selectedTable === 'nizokash_oilalar'): ?>
                                <div class="row g-3">
                                    <div class="col-12"><label>F.I.Sh.</label><input name="fish" class="form-control bg-secondary text-white" value="<?= htmlspecialchars($editData['fish'] ?? '') ?>" required></div>
                                    <div class="col-md-4"><label>A'zolar soni</label><input type="number" name="azolar_soni" class="form-control bg-secondary text-white" value="<?= $editData['azolar_soni'] ?? '' ?>" min="1"></div>
                                    <div class="col-md-4"><label>Sana</label><input type="date" name="sana" class="form-control bg-secondary text-white" value="<?= $editData['sana'] ?? '' ?>" required></div>
                                    <div class="col-12"><label>Sabab</label><textarea name="sababi" class="form-control bg-secondary text-white" rows="3"><?= htmlspecialchars($editData['sababi'] ?? '') ?></textarea></div>
                                </div>
                            <?php elseif ($selectedTable === 'order_olganlar'): ?>
                                <div class="row g-3">
                                    <div class="col-12"><label>F.I.Sh.</label><input name="fish" class="form-control bg-secondary text-white" value="<?= htmlspecialchars($editData['fish'] ?? '') ?>" required></div>
                                    <div class="col-md-6"><label>Order nomi</label><input name="order_nomi" class="form-control bg-secondary text-white" value="<?= htmlspecialchars($editData['order_nomi'] ?? '') ?>" required></div>
                                    <div class="col-md-6"><label>Daraja</label><input name="order_darajasi" class="form-control bg-secondary text-white" value="<?= htmlspecialchars($editData['order_darajasi'] ?? '') ?>"></div>
                                    <div class="col-12"><label>Berilgan sana</label><input type="date" name="berilgan_sana" class="form-control bg-secondary text-white" value="<?= $editData['berilgan_sana'] ?? '' ?>" required></div>
                                </div>
                            <?php endif; ?>
                            <!-- Joylashuv -->
                            <div class="row g-3 mt-4">
                                <div class="col-md-4">
                                    <label>Viloyat</label>
                                    <select name="viloyat_id" id="viloyat_id" class="form-select bg-secondary text-white" required onchange="loadTumanlar(this.value)">
                                        <option value="">Tanlang</option>
                                        <?php
                                        $res = $mysqli->query("SELECT id, nomi FROM viloyatlar ORDER BY nomi");
                                        while ($v = $res->fetch_assoc()): ?>
                                            <option value="<?= $v['id'] ?>" <?= ($editData['viloyat_id'] ?? 0) == $v['id'] ? 'selected' : '' ?>><?= $v['nomi'] ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>Tuman</label>
                                    <select name="tuman_id" id="tuman_id" class="form-select bg-secondary text-white" onchange="loadMahallalar(this.value)">
                                        <option value="">Avval viloyatni tanlang</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>Mahalla</label>
                                    <select name="mahalla_id" id="mahalla_id" class="form-select bg-secondary text-white">
                                        <option value="">Avval tumanni tanlang</option>
                                    </select>
                                </div>
                            </div>
                                <div class="mt-4">
                                    <label>Xaritada joylashuv</label>
                                    <div id="map"></div>
                                    <div class="row mt-2">
                                        <div class="col"><input type="text" name="lat" id="lat" class="form-control bg-secondary text-white" value="<?= $editData['lat'] ?? '41.311100' ?>" readonly></div>
                                        <div class="col"><input type="text" name="lng" id="lng" class="form-control bg-secondary text-white" value="<?= $editData['lng'] ?? '69.279700' ?>" readonly></div>
                                    </div>
                                </div>
                        </div>
                        <div class="modal-footer">
                            <a href="?table=<?= $selectedTable ?>&viloyat=<?= $viloyat_id ?>&tuman=<?= $tuman_id ?>&mahalla=<?= $mahalla_id ?>&page=<?= $page ?>" class="btn btn-secondary">Bekor qilish</a>
                            <button type="submit" class="btn btn-info">Saqlash</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- AJAX Tuman/Mahalla -->
    <?php
    if (isset($_GET['get'])) {
        header('Content-Type: application/json');
        if ($_GET['get'] === 'tuman') {
            $stmt = $mysqli->prepare("SELECT id, nomi FROM tumanlar WHERE viloyat_id = ? ORDER BY nomi");
            $stmt->bind_param("i", $_GET['viloyat_id']);
        } elseif ($_GET['get'] === 'mahalla') {
            $stmt = $mysqli->prepare("SELECT id, nomi FROM mahallelar WHERE tuman_id = ? ORDER BY nomi");
            $stmt->bind_param("i", $_GET['tuman_id']);
        }
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit;
    }
    ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($editData): ?>
            let map, marker, mahallaLayer;
            const lat = <?= $editData['lat'] ?? 41.311100 ?>;
            const lng = <?= $editData['lng'] ?? 69.279700 ?>;
            setTimeout(() => {
                map = L.map('map').setView([lat, lng], 15);
                // Yandex xarita (yoki OSM)
                L.tileLayer('https://core-renderer-tiles.maps.yandex.net/tiles?l=map&x={x}&y={y}&z={z}&scale=1&lang=ru_RU', {
                    maxZoom: 19
                }).addTo(map);
                // Rangli markerlar (jinoyat turiga qarab)
                function getCrimeColor(turi) {
                    if (!turi) return 'grey';
                    const lower = turi.toLowerCase();
                    if (lower.includes('o\'g\'rilik') || lower.includes('ogrilik')) return 'red';
                    if (lower.includes('zo') || lower.includes('hujum') || lower.includes('urish')) return 'darkred';
                    if (lower.includes('giyoh') || lower.includes('narko')) return 'violet';
                    if (lower.includes('firib') || lower.includes('aldash')) return 'orange';
                    if (lower.includes('qotil') || lower.includes('o\'ldirish')) return 'black';
                    return 'blue';
                }
                const color = getCrimeColor("<?= htmlspecialchars($editData['ogrilik_turi'] ?? '') ?>");
                const allowedColors = ['red','green','orange','yellow','blue','violet','grey','black'];
                const safeColor = allowedColors.includes(color) ? color : 'blue';

                const iconUrl = `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-${color === 'black' ? 'grey' : color}.png`;
                marker = L.marker([lat, lng], {
                    draggable: true,
                    icon: L.icon({
                        iconUrl: iconUrl,
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34]
                    })
                }).addTo(map);
                marker.bindPopup(`<b><?= htmlspecialchars($editData['jk_modda'] ?? 'Jinoyat') ?></b><br><?= htmlspecialchars($editData['ogrilik_turi'] ?? '') ?>`).openPopup();
                marker.on('dragend', e => {
                    const pos = e.target.getLatLng();
                    document.getElementById('lat').value = pos.lat.toFixed(8);
                    document.getElementById('lng').value = pos.lng.toFixed(8);
                });
                // Mahalla poligoni yuklash
                const polygonJson = <?= json_encode($mahallaPolygon) ?>;
                if (polygonJson) {
                    try {
                        const geo = typeof polygonJson === 'string' ? JSON.parse(polygonJson) : polygonJson;
                        mahallaLayer = L.geoJSON(geo, {
                            style: {
                                color: "#00e5ff",
                                weight: 5,
                                opacity: 0.9,
                                fillColor: "#00e5ff",
                                fillOpacity: 0.15
                            }
                        }).addTo(map);
                        // Xaritani mahalla chegarasiga moslashtirish
                        map.fitBounds(mahallaLayer.getBounds(), {
                            padding: [50, 50]
                        });
                    } catch (e) {
                        console.warn("Mahalla poligoni yuklanmadi:", e);
                    }
                }
            }, 300);
            // Modal ochilganda xaritani yangilash
            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal._element.addEventListener('shown.bs.modal', () => {
                setTimeout(() => {
                    map.invalidateSize();
                    map.setView([lat, lng], 15);
                }, 150);
            });
        <?php endif; ?>

        function loadTumanlar(vid) {
            if (!vid) return;
            fetch(`?get=tuman&viloyat_id=${vid}`).then(r => r.json()).then(data => {
                const sel = document.getElementById('tuman_id');
                sel.innerHTML = '<option value="">Tanlang</option>';
                data.forEach(t => sel.innerHTML += `<option value="${t.id}">${t.nomi}</option>`);
                <?php if ($editData): ?> sel.value = <?= $editData['tuman_id'] ?? 0 ?>;
                    loadMahallalar(sel.value);
                <?php endif; ?>
            });
        }

        function loadMahallalar(tid) {
            if (!tid) return;
            fetch(`?get=mahalla&tuman_id=${tid}`).then(r => r.json()).then(data => {
                const sel = document.getElementById('mahalla_id');
                sel.innerHTML = '<option value="">Tanlang</option>';
                data.forEach(m => sel.innerHTML += `<option value="${m.id}">${m.nomi}</option>`);
                <?php if ($editData): ?> sel.value = <?= $editData['mahalla_id'] ?? 0 ?>;
                <?php endif; ?>
            });
        }
        <?php if ($editData): ?>
            document.getElementById('viloyat_id').dispatchEvent(new Event('change'));
        <?php endif; ?>
        // Network animatsiyasi
        const canvas = document.getElementById('network-bg');
        const ctx = canvas.getContext('2d');
        let w, h, dots = [];

        function resize() {
            w = canvas.width = innerWidth;
            h = canvas.height = innerHeight;
            dots = Array.from({
                length: 90
            }, () => ({
                x: Math.random() * w,
                y: Math.random() * h,
                vx: (Math.random() - 0.5) * 0.7,
                vy: (Math.random() - 0.5) * 0.7
            }));
        }
        window.addEventListener('resize', resize);
        resize();

        function animate() {
            ctx.clearRect(0, 0, w, h);
            ctx.fillStyle = '#00e5ff';
            dots.forEach(d => {
                d.x += d.vx;
                d.y += d.vy;
                if (d.x < 0 || d.x > w) d.vx *= -1;
                if (d.y < 0 || d.y > h) d.vy *= -1;
                ctx.beginPath();
                ctx.arc(d.x, d.y, 1.8, 0, Math.PI * 2);
                ctx.fill();
            });
            for (let i = 0; i < dots.length; i++)
                for (let j = i + 1; j < dots.length; j++) {
                    let dx = dots[i].x - dots[j].x,
                        dy = dots[i].y - dots[j].y,
                        dist = Math.hypot(dx, dy);
                    if (dist < 130) {
                        ctx.strokeStyle = `rgba(0,229,255,${1-dist/130})`;
                        ctx.lineWidth = 0.5;
                        ctx.beginPath();
                        ctx.moveTo(dots[i].x, dots[i].y);
                        ctx.lineTo(dots[j].x, dots[j].y);
                        ctx.stroke();
                    }
                }
            requestAnimationFrame(animate);
        }
        animate();
    </script>
</body>

</html>