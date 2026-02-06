<?php
session_start();
require_once "../connection/config.php";

$db = new Database();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Foydalanuvchi tizimga kirmagan']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user = $db->select('users', '*', 'id = ?', [$user_id], 'i')[0] ?? null;
if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'Foydalanuvchi topilmadi']);
    exit;
}

$operator_id = $user_id;
$viloyatlar = $db->select('viloyatlar', '*');
$tumanlar = $db->select('tumanlar', '*');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        echo json_encode(['status' => 'error', 'message' => 'So‘rov maʼlumotlari topilmadi']);
        exit;
    }

    $viloyat_id = intval($data['viloyat_id'] ?? 0);
    $tuman_id   = intval($data['tuman_id'] ?? 0);
    $nomi       = trim($data['nomi'] ?? '');
    $polygon    = $data['polygon'] ?? [];

    if (!$viloyat_id || !$tuman_id || !$nomi || !is_array($polygon) || !isset($polygon[0][0])) {
        echo json_encode(['status' => 'error', 'message' => 'Barcha maydonlar to‘ldirilmagan']);
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

    $id = $db->insert("mahallelar", [
        'viloyat_id' => $viloyat_id,
        'tuman_id'   => $tuman_id,
        'nomi'       => $nomi,
        'polygon'    => json_encode($polygon_geojson, JSON_UNESCAPED_UNICODE),
        'operator_id' => $operator_id,
        'markaz_lat' => $center_lat,
        'markaz_lng' => $center_lng
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Mahalla saqlandi', 'id' => $id]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFY Qo'shish | GeoTizim</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #00e5ff;
            --bg: #000814;
            --sidebar: #001b46;
            --card: rgba(255, 255, 255, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: #cfd8dc;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Network fon */
        #network-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -2;
            background: radial-gradient(ellipse at bottom, #01021a 0%, #000000 100%);
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #001b46 0%, #000e24 100%);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.6);
            z-index: 1000;
            padding: 25px 0;
            overflow-y: auto;
            transition: transform 0.4s ease;
        }

        .sidebar h2 {
            text-align: center;
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 30px;
            font-weight: 700;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: #cfd8dc;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(0, 229, 255, 0.15);
            color: #fff;
            border-left-color: var(--primary);
            transform: translateX(5px);
        }

        .nav-link i {
            width: 28px;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .nav-link span {
            margin-left: 12px;
        }

        /* Main content */
        .main-content {
            margin-left: 280px;
            padding: 40px;
            min-height: 100vh;
        }

        .page-title {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 30px;
            text-align: center;
        }

        .card {
            background: var(--card);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(0, 229, 255, 0.2);
        }

        #map {
            height: 550px;
            border-radius: 16px;
            border: 2px solid var(--primary);
            margin: 25px 0;
        }

        .save-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #06d6a0, #1a936f);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .save-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(6, 214, 160, 0.4);
        }

        select,
        input[type="text"] {
            width: 100%;
            padding: 14px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(0, 229, 255, 0.3);
            border-radius: 12px;
            color: white;
            margin-bottom: 20px;
        }

        select:focus,
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 229, 255, 0.2);
        }

        label {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        /* ------------ */
        /* Form-Group yaxshiroq ko‘rinish */
        .form-group {
            margin-bottom: 22px;
        }

        /* Label modern style */
        .form-group label {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
            letter-spacing: .3px;
        }

        /* Select umumiy dizayn */
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.08);
            border: 1.5px solid rgba(0, 229, 255, 0.25);
            border-radius: 14px;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            transition: all .25s ease;
            appearance: none;
            position: relative;
            backdrop-filter: blur(6px);
        }

        /* Hover — yengil ko‘tarilish */
        .form-group select:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(0, 229, 255, 0.5);
            transform: translateY(-2px);
        }

        /* Focus — yorqin neon ko‘rinish */
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 12px rgba(0, 229, 255, 0.4);
        }

        /* Option dizayni */
        .form-group select option {
            background: #00172e;
            color: #e0f7fa;
            padding: 12px;
            border-bottom: 1px solid rgba(0, 229, 255, 0.15);
        }

        /* Hover bo‘lganda dropdown ichki variant efеkti */
        .form-group select option:hover {
            background: #003b85 !important;
            color: white;
        }

        /* Selectga "custom arrow" qo‘shish */
        .select-wrapper {
            position: relative;
        }

        .select-wrapper::after {
            content: "\f078";
            /* Font Awesome arrow-down */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            pointer-events: none;
            font-size: 0.9rem;
            opacity: 0.8;
        }
    </style>
</head>

<body>

    <!-- Network fon -->
    <canvas id="network-bg"></canvas>

    <!-- Sidebar -->
    <div class="sidebar">
        <h2><i class="fa-solid fa-shield-halved"></i> GeoTizim</h2>

        <a href="../" class="nav-link"><i class="fa-solid fa-house"></i><span>Asosiy sahifa</span></a>
        <a href="./creat_mfy.php" class="nav-link active"><i class="fa-solid fa-map-marked-alt"></i><span>Yangi MFY qo'shish</span></a>
        <a href="./edit_mfy.php" class="nav-link"><i class="fa-solid fa-edit"></i><span>MFY tahrirlash</span></a>
        <a href="../dashboard.php" class="nav-link"><i class="fa-solid fa-chart-pie"></i><span>Statistika</span></a>

        <?php if ($user['role'] === 'admin'): ?>
            <a href="../admin/" class="nav-link" style="margin-top: 30px; background: rgba(239,71,111,0.2);">
                <i class="fa-solid fa-user-shield"></i><span>Admin panel</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Main content -->
    <div class="main-content">
        <h1 class="page-title">Yangi Mahalla Fuqarolar Yig‘ini (MFY) qo‘shish</h1>

        <div class="card">
            <div class="form-group">
                <label>Viloyat</label>
                <div class="select-wrapper">
                    <select id="viloyatSelect" required>
                        <option value="">Viloyatni tanlang</option>
                        <?php foreach ($viloyatlar as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['nomi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Tuman/Shahar</label>
                <div class="select-wrapper">
                    <select id="tumanSelect" required>
                        <option value="">Avval viloyatni tanlang</option>
                        <?php foreach ($tumanlar as $t): ?>
                            <option value="<?= $t['id'] ?>" data-viloyat="<?= $t['viloyat_id'] ?>">
                                <?= htmlspecialchars($t['nomi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>


            <div class="form-group">
                <label>Mahalla nomi</label>
                <input type="text" id="mahalleName" placeholder="Masalan: Olmazor MFY" required>
            </div>

            <div id="map"></div>

            <button type="button" id="savePolygon" class="save-btn">
                <i class="fas fa-save"></i> Mahallani saqlash
            </button>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
    <script>
        // Tuman filtr — to'g'ri versiya
        document.getElementById('viloyatSelect').addEventListener('change', function() {
            const viloyatId = this.value;
            const tumanSelect = document.getElementById('tumanSelect');
            const allOptions = tumanSelect.querySelectorAll('option[data-viloyat]');

            tumanSelect.innerHTML = '<option value="">Tumanni tanlang</option>';
            if (!viloyatId) return;

            allOptions.forEach(opt => {
                if (opt.dataset.viloyat == viloyatId) {
                    tumanSelect.appendChild(opt.cloneNode(true));
                }
            });
        });

        // Xarita
        const map = L.map('map').setView([41.3111, 69.2797], 10);
        L.tileLayer('https://core-renderer-tiles.maps.yandex.net/tiles?l=map&x={x}&y={y}&z={z}&scale=1&lang=tr_TR', {
            attribution: '© Yandex'
        }).addTo(map);

        const drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        new L.Control.Draw({
            edit: {
                featureGroup: drawnItems
            },
            draw: {
                polygon: {
                    shapeOptions: {
                        color: '#00e5ff',
                        weight: 5
                    }
                },
                polyline: false,
                rectangle: false,
                circle: false,
                marker: false,
                circlemarker: false
            }
        }).addTo(map);

        map.on(L.Draw.Event.CREATED, e => {
            drawnItems.clearLayers();
            drawnItems.addLayer(e.layer);
        });

        // Saqlash
        document.getElementById('savePolygon').addEventListener('click', () => {
            const viloyat_id = document.getElementById('viloyatSelect').value;
            const tuman_id = document.getElementById('tumanSelect').value;
            const nomi = document.getElementById('mahalleName').value.trim();

            if (!viloyat_id || !tuman_id || !nomi) {
                return alert('Barcha maydonlarni to‘ldiring!');
            }
            if (drawnItems.getLayers().length === 0) {
                return alert('Hududni xaritada belgilang!');
            }

            const polygons = [];
            drawnItems.eachLayer(l => {
                polygons.push(l.getLatLngs()[0].map(p => [p.lng, p.lat]));
            });

            fetch('creat_mfy.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        viloyat_id,
                        tuman_id,
                        nomi,
                        polygon: polygons
                    })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        alert('Mahalla muvaffaqiyatli saqlandi!');
                        drawnItems.clearLayers();
                        document.getElementById('mahalleForm')?.reset();
                        document.getElementById('tumanSelect').innerHTML = '<option value="">Tumanni tanlang</option>';
                    } else {
                        alert('Xatolik: ' + res.message);
                    }
                });
        });

        // Network animatsiya (fon)
        const canvas = document.getElementById('network-bg');
        const ctx = canvas.getContext('2d');
        let w, h, dots = [];

        function init() {
            w = canvas.width = window.innerWidth;
            h = canvas.height = window.innerHeight;
            dots = Array.from({
                length: 70
            }, () => ({
                x: Math.random() * w,
                y: Math.random() * h,
                vx: (Math.random() - 0.5) * 0.6,
                vy: (Math.random() - 0.5) * 0.6
            }));
        }
        window.addEventListener('resize', init);
        init();

        function animate() {
            ctx.clearRect(0, 0, w, h);
            dots.forEach(d => {
                d.x += d.vx;
                d.y += d.vy;
                if (d.x < 0 || d.x > w) d.vx *= -1;
                if (d.y < 0 || d.y > h) d.vy *= -1;

                ctx.fillStyle = '#00e5ff';
                ctx.beginPath();
                ctx.arc(d.x, d.y, 1.5, 0, Math.PI * 2);
                ctx.fill();
            });

            for (let i = 0; i < dots.length; i++) {
                for (let j = i + 1; j < dots.length; j++) {
                    const dx = dots[i].x - dots[j].x;
                    const dy = dots[i].y - dots[j].y;
                    const dist = Math.hypot(dx, dy);
                    if (dist < 130) {
                        ctx.strokeStyle = `rgba(0,229,255,${1 - dist/130})`;
                        ctx.lineWidth = 0.5;
                        ctx.beginPath();
                        ctx.moveTo(dots[i].x, dots[i].y);
                        ctx.lineTo(dots[j].x, dots[j].y);
                        ctx.stroke();
                    }
                }
            }
            requestAnimationFrame(animate);
        }
        animate();
    </script>
</body>

</html>