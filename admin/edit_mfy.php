<?php
session_start();
require_once "../connection/config.php";
$db = new Database();

if (!isset($_SESSION['user_id'])) {
    die("Foydalanuvchi tizimga kirmagan");
}

$user_id = $_SESSION['user_id'];
$user = $db->select('users', '*', 'id = ?', [$user_id], 'i')[0] ?? null;
if (!$user) die("Foydalanuvchi topilmadi");

$viloyatlar = $db->select('viloyatlar', '*');
$operator_id = $user_id;

$mahalle = null;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT m.*, v.nomi AS viloyat_nomi, t.nomi AS tuman_nomi
            FROM mahallelar m
            LEFT JOIN viloyatlar v ON m.viloyat_id = v.id
            LEFT JOIN tumanlar t ON m.tuman_id = t.id
            WHERE m.id = ?";
    $mahalle = $db->executeQuery($sql, [$id])->get_result()->fetch_assoc();
}

// POST — saqlash
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['mahalle_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Mahalla tanlanmagan']);
        exit;
    }

    $mahalle_id = intval($data['mahalle_id']);
    $viloyat_id = intval($data['viloyat_id'] ?? 0);
    $tuman_id = intval($data['tuman_id'] ?? 0);
    $nomi = trim($data['nomi'] ?? '');
    $polygon = $data['polygon'] ?? [];

    if (!$viloyat_id || !$tuman_id || !$nomi || !is_array($polygon) || count($polygon) === 0 || !isset($polygon[0][0])) {
        echo json_encode(['status' => 'error', 'message' => 'Barcha maydonlar to‘ldirilishi kerak']);
        exit;
    }

    $polygon_geojson = [
        "type" => "Feature",
        "geometry" => ["type" => "Polygon", "coordinates" => $polygon],
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
        'viloyat_id' => $viloyat_id,
        'tuman_id' => $tuman_id,
        'nomi' => $nomi,
        'polygon' => json_encode($polygon_geojson, JSON_UNESCAPED_UNICODE),
        'operator_id' => $operator_id,
        'markaz_lat' => $center_lat,
        'markaz_lng' => $center_lng
    ], 'id = ?', [$mahalle_id], 'i');

    echo json_encode(['status' => 'success', 'message' => 'Mahalla muvaffaqiyatli yangilandi']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mahalla Tahrirlash | GeoTizim</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --primary: #00e5ff; --bg: #000814; --card: rgba(255,255,255,0.08); }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:var(--bg); color:#cfd8dc; min-height:100vh; }
        .sidebar {
            position:fixed; top:0; left:0; width:280px; height:100vh;
            background:linear-gradient(180deg,#001b46 0%,#000e24 100%);
            box-shadow:4px 0 20px rgba(0,0,0,0.6); z-index:1000; padding:25px 0; overflow-y:auto;
        }
        .sidebar h2 { text-align:center; color:var(--primary); font-size:1.5rem; margin-bottom:30px; font-weight:700; }
        .nav-link { display:flex; align-items:center; padding:14px 20px; color:#cfd8dc; text-decoration:none; transition:all 0.3s; border-left:4px solid transparent; }
        .nav-link:hover, .nav-link.active { background:rgba(0,229,255,0.15); color:#fff; border-left-color:var(--primary); transform:translateX(5px); }
        .nav-link i { width:28px; color:var(--primary); font-size:1.1rem; }
        .nav-link span { margin-left:12px; }

        .main-content { margin-left:280px; padding:40px; min-height:100vh; }
        .page-title { font-size:2rem; color:var(--primary); margin-bottom:30px; text-align:center; }
        .card { background:var(--card); backdrop-filter:blur(12px); border-radius:20px; padding:30px; box-shadow:0 10px 40px rgba(0,0,0,0.4); border:1px solid rgba(0,229,255,0.2); }
        #map { height:550px; border-radius:16px; border:2px solid var(--primary); margin:25px 0; }

        .select-wrapper { position:relative; }
        .select-wrapper::after { content:"\f078"; font-family:"Font Awesome 6 Free"; font-weight:900; position:absolute; right:18px; top:50%; transform:translateY(-50%); color:var(--primary); pointer-events:none; font-size:0.9rem; }

        select {
            width:100%; padding:14px 18px; background:rgba(255,255,255,0.08);
            border:1.5px solid rgba(0,229,255,0.25); border-radius:14px; color:white; font-size:1rem; margin-bottom:20px;
        }
        select:focus { outline:none; border-color:var(--primary); box-shadow:0 0 12px rgba(0,229,255,0.4); }
        label { color:var(--primary); font-weight:600; margin-bottom:10px; display:block; }

        .save-btn { width:100%; padding:16px; background:linear-gradient(135deg,#06d6a0,#1a936f); color:white; border:none; border-radius:14px; font-size:1.2rem; font-weight:600; cursor:pointer; transition:all 0.3s; }
        .save-btn:hover { transform:translateY(-4px); box-shadow:0 15px 30px rgba(6,214,160,0.4); }
        .save-btn:disabled { opacity:0.6; cursor:not-allowed; transform:none; background:#444; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2><i class="fa-solid fa-shield-halved"></i> GeoTizim</h2>
        <a href="../" class="nav-link"><i class="fa-solid fa-house"></i><span>Asosiy sahifa</span></a>
        <a href="./creat_mfy.php" class="nav-link"><i class="fa-solid fa-map-marked-alt"></i><span>Yangi MFY qo'shish</span></a>
        <a href="./edit_mfy.php" class="nav-link active"><i class="fa-solid fa-edit"></i><span>MFY tahrirlash</span></a>
        <a href="../dashboard.php" class="nav-link"><i class="fa-solid fa-chart-pie"></i><span>Statistika</span></a>
        <?php if ($user['role'] === 'admin'): ?>
            <a href="../admin/" class="nav-link" style="margin-top:30px; background:rgba(239,71,111,0.2);">
                <i class="fa-solid fa-user-shield"></i><span>Admin panel</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="main-content">
        <h1 class="page-title">Mahalla Fuqarolar Yig‘ini (MFY) tahrirlash</h1>
        <div class="card">
            <div class="form-group">
                <label>Viloyat</label>
                <div class="select-wrapper">
                    <select id="viloyatSelect">
                        <option value="">Viloyatni tanlang</option>
                        <?php foreach ($viloyatlar as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= $mahalle && $mahalle['viloyat_id'] == $v['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v['nomi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Tuman/Shahar</label>
                <div class="select-wrapper">
                    <select id="tumanSelect" disabled>
                        <option value="">Avval viloyatni tanlang</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Mahalla</label>
                <div class="select-wrapper">
                    <select id="mahalleSelect" disabled>
                        <option value="">Avval tuman tanlang</option>
                    </select>
                </div>
            </div>

            <div id="map"></div>

            <button type="button" id="saveBtn" class="save-btn" disabled>
                <i class="fas fa-save"></i> Yangilash va saqlash
            </button>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const viloyatSelect = document.getElementById('viloyatSelect');
        const tumanSelect = document.getElementById('tumanSelect');
        const mahalleSelect = document.getElementById('mahalleSelect');
        const saveBtn = document.getElementById('saveBtn');

        let currentMahalle = null;

        const map = L.map('map').setView([41.3111, 69.2797], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        const drawControl = new L.Control.Draw({
            edit: { featureGroup: drawnItems },
            draw: {
                polygon: { shapeOptions: { color: '#00e5ff', weight: 5 } },
                polyline: false, rectangle: false, circle: false, marker: false, circlemarker: false
            }
        });
        map.addControl(drawControl);

        map.on(L.Draw.Event.CREATED, e => {
            drawnItems.clearLayers();
            drawnItems.addLayer(e.layer);
            saveBtn.disabled = false;
        });
        map.on(L.Draw.Event.EDITED, () => saveBtn.disabled = false);

        // Viloyat tanlanganda tumanlarni yuklash (HTML tugmalar → <option>)
        viloyatSelect.addEventListener('change', () => {
            const vilId = viloyatSelect.value;

            tumanSelect.innerHTML = '<option value="">Yuklanmoqda...</option>';
            tumanSelect.disabled = true;

            mahalleSelect.innerHTML = '<option value="">Avval tuman tanlang</option>';
            mahalleSelect.disabled = true;

            drawnItems.clearLayers();
            saveBtn.disabled = true;
            currentMahalle = null;

            if (!vilId) {
                tumanSelect.innerHTML = '<option value="">Avval viloyatni tanlang</option>';
                return;
            }

            fetch(`../connection/get_tumanlar.php?viloyat_id=${vilId}`)
                .then(r => r.text())
                .then(html => {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html.trim();

                    tumanSelect.innerHTML = '<option value="">Tumanni tanlang</option>';

                    const buttons = tempDiv.querySelectorAll('button');
                    if (buttons.length === 0) {
                        tumanSelect.innerHTML = '<option value="">Tuman topilmadi</option>';
                    } else {
                        buttons.forEach(btn => {
                            const match = btn.getAttribute('onclick').match(/loadMahallalar\((\d+),/);
                            if (match) {
                                const opt = document.createElement('option');
                                opt.value = match[1];
                                opt.textContent = btn.textContent.trim();
                                tumanSelect.appendChild(opt);
                            }
                        });
                    }
                    tumanSelect.disabled = false;
                })
                .catch(err => {
                    tumanSelect.innerHTML = '<option value="">Xatolik yuz berdi</option>';
                    console.error(err);
                });
        });

        // Tuman tanlanganda mahallalarni modal orqali ko‘rsatish
        tumanSelect.addEventListener('change', () => {
            const tumanId = tumanSelect.value;
            const tumanNomi = tumanSelect.options[tumanSelect.selectedIndex].textContent;

            drawnItems.clearLayers();
            saveBtn.disabled = true;
            currentMahalle = null;

            if (!tumanId) return;

            const existingModal = document.getElementById('mahallaModal');
            if (existingModal) existingModal.remove();

            const modalHtml = `
            <div class="modal fade" id="mahallaModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content bg-dark text-light border border-info border-2">
                        <div class="modal-header bg-info bg-opacity-10">
                            <h5 class="modal-title text-info fw-bold">${tumanNomi} — Mahallalar</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="mahallaList" class="row g-3">
                                <div class="col-12 text-center text-secondary">Yuklanmoqda...</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-info btn-sm" data-bs-dismiss="modal">Yopish</button>
                        </div>
                    </div>
                </div>
            </div>`;

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('mahallaModal'));
            modal.show();

            fetch(`../connection/get_mahallalar.php?tuman_id=${tumanId}`)
                .then(res => res.json())
                .then(result => {
                    const container = document.getElementById('mahallaList');
                    container.innerHTML = '';

                    if (!result.success || !result.data || result.data.length === 0) {
                        container.innerHTML = '<div class="col-12 text-center text-danger">Mahalla topilmadi</div>';
                        return;
                    }

                    result.data.forEach(m => {
                        const div = document.createElement('div');
                        div.className = 'col-12 col-md-6 col-lg-4';
                        div.innerHTML = `
                            <button class="btn btn-outline-info w-100 text-start py-3" onclick="selectMahalla(${m.id}, '${m.nomi.replace(/'/g, "\\'")}', ${m.polygon ? `'${m.polygon.replace(/'/g, "\\'")}'` : 'null'})">
                                <i class="fa-solid fa-house-chimney me-2"></i> ${m.nomi}
                            </button>`;
                        container.appendChild(div);
                    });
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('mahallaList').innerHTML = '<div class="col-12 text-center text-danger">Xatolik yuz berdi</div>';
                });
        });

        // Mahalla tanlanganda — mahalla_info.php dagi kabi aniq poligon chizish
        function selectMahalla(id, nomi, polygonJson) {
            bootstrap.Modal.getInstance(document.getElementById('mahallaModal')).hide();

            mahalleSelect.innerHTML = `<option value="${id}">${nomi}</option>`;
            mahalleSelect.disabled = false;

            currentMahalle = { id, nomi };

            drawnItems.clearLayers();
            saveBtn.disabled = true;

            if (!polygonJson) {
                alert('Bu mahallada hudud belgilanmagan. Yangi hudud chizing.');
                saveBtn.disabled = false;
                return;
            }

            try {
                const geojson = JSON.parse(polygonJson);

                // mahalla_info.php dagi kabi aniq style
                const polygonLayer = L.geoJSON(geojson, {
                    style: {
                        color: '#4361ee',
                        weight: 5,
                        opacity: 0.8,
                        fillColor: '#2f4fdaff',
                        fillOpacity: 0.08
                    }
                });

                drawnItems.addLayer(polygonLayer);
                map.fitBounds(polygonLayer.getBounds());
                saveBtn.disabled = false;
            } catch (e) {
                alert('Polygon maʼlumotlari buzilgan. Yangi hudud chizing.');
                console.error(e);
                saveBtn.disabled = false;
            }
        }

        // Saqlash
        saveBtn.addEventListener('click', async () => {
            if (!currentMahalle) {
                alert('Mahalla tanlanmagan!');
                return;
            }
            if (drawnItems.getLayers().length === 0) {
                alert('Hudud chizilmagan!');
                return;
            }

            const polygons = [];
            drawnItems.eachLayer(l => {
                if (l instanceof L.Polygon) {
                    polygons.push(l.getLatLngs()[0].map(p => [p.lng, p.lat]));
                }
            });

            if (polygons.length === 0) {
                alert('Polygon topilmadi!');
                return;
            }

            try {
                const res = await fetch(window.location.pathname + window.location.search, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        mahalle_id: currentMahalle.id,
                        viloyat_id: viloyatSelect.value,
                        tuman_id: tumanSelect.value,
                        nomi: currentMahalle.nomi,
                        polygon: polygons
                    })
                });
                const data = await res.json();
                alert(data.status === 'success' ? data.message : 'Xatolik: ' + data.message);
            } catch (err) {
                alert('Saqlashda xatolik yuz berdi');
                console.error(err);
            }
        });

        // ?id bilan ochilganda avtomatik yuklash (mahalla_info.php dagi kabi poligon)
        <?php if ($mahalle && !empty($mahalle['polygon'])): ?>
        document.addEventListener('DOMContentLoaded', () => {
            viloyatSelect.value = "<?= $mahalle['viloyat_id'] ?>";
            viloyatSelect.dispatchEvent(new Event('change'));

            const waitForTuman = setInterval(() => {
                if (tumanSelect.querySelector(`option[value="<?= $mahalle['tuman_id'] ?>"]`)) {
                    tumanSelect.value = "<?= $mahalle['tuman_id'] ?>";
                    tumanSelect.dispatchEvent(new Event('change'));
                    clearInterval(waitForTuman);
                }
            }, 100);

            // Mahalla modal ochilganda avtomatik tanlash
            const waitModal = setInterval(() => {
                if (document.getElementById('mahallaModal')) {
                    const btn = Array.from(document.querySelectorAll('#mahallaList button'))
                        .find(b => b.textContent.trim().includes("<?= addslashes($mahalle['nomi']) ?>"));
                    if (btn) {
                        btn.click();
                        clearInterval(waitModal);
                    }
                }
            }, 200);
        });
        <?php endif; ?>
    </script>
</body>
</html>