<?php
// ===================================
// DB bilan bog‘lanish
// ===================================
require_once dirname(__DIR__) . '/connection/pdo.php';
// ===================================
// GET orqali kelgan mahalla id
// ===================================
$mahalla_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$mahalla_id) die("❌ Mahalla ID ko‘rsatilmagan!");

// Mahalla ma’lumotini olish (tuman, viloyat bilan)
$stmt = $pdo->prepare("
    SELECT m.id as mahalla_id, m.nomi as mahalla_nomi, m.polygon, 
           t.id as tuman_id, t.nomi as tuman_nomi, 
           v.id as viloyat_id, v.nomi as viloyat_nomi
    FROM mahallelar m
    JOIN tumanlar t ON m.tuman_id = t.id
    JOIN viloyatlar v ON t.viloyat_id = v.id
    WHERE m.id = ?
");
$stmt->execute([$mahalla_id]);
$mahalla = $stmt->fetch();
if (!$mahalla) die("❌ Mahalla topilmadi!");

// ===================================
// Jinoyat qo‘shish
// ===================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        INSERT INTO crimes 
        (jk_modda, qismi, bandi, ogrilik_turi, sodir_vaqti, viloyat_id, tuman_id, mahalla_id, jinoyat_matni, lat, lng)
        VALUES 
        (:jk_modda, :qismi, :bandi, :ogrilik_turi, :sodir_vaqti, :viloyat_id, :tuman_id, :mahalla_id, :jinoyat_matni, :lat, :lng)
    ");
    $stmt->execute([
        ':jk_modda'      => $_POST['jk_modda'] ?? '',
        ':qismi'         => $_POST['qismi'] ?? '',
        ':bandi'         => $_POST['bandi'] ?? '',
        ':ogrilik_turi'  => $_POST['ogrilik_turi'] ?? '',
        ':sodir_vaqti'   => $_POST['sodir_vaqti'] ?: null,
        ':viloyat_id'    => $mahalla['viloyat_id'],
        ':tuman_id'      => $mahalla['tuman_id'],
        ':mahalla_id'    => $mahalla['mahalla_id'],
        ':jinoyat_matni' => $_POST['jinoyat_matni'] ?? '',
        ':lat'           => $_POST['lat'] ?? null,
        ':lng'           => $_POST['lng'] ?? null,
    ]);
    // echo "<div class='alert success'>✅ Jinoyat muvaffaqiyatli qo‘shildi!</div>";
}

$stmt = $pdo->prepare("SELECT * FROM crimes WHERE mahalla_id = ?");
$stmt->execute([$mahalla_id]);
$crimess = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <title>🗺️ Jinoyat qo‘shish</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* === Global Styles === */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #f0f2f5;
            color: #333;
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            font-weight: 700;
            color: #1a1a1a;
        }

        /* === Container === */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        /* === Info Box === */
        .info-box {
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            color: #fff;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-around;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* === Form Styles === */
        form {
            background: #fff;
            padding: 30px 25px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        form:hover {
            transform: translateY(-3px);
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: 600;
            color: #555;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            margin-top: 5px;
            border-radius: 10px;
            border: 1px solid #ccc;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #2575fc;
            box-shadow: 0 0 8px rgba(37, 117, 252, 0.3);
        }

        /* === Button === */
        button {
            background: linear-gradient(135deg, #2575fc, #6a11cb);
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            margin-top: 20px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 117, 252, 0.3);
        }

        /* Formani grid bilan bo‘lish */
        .crime-form .form-grid {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }

        .crime-form .form-left {
            flex: 1 1 350px;
            display: flex;
            flex-direction: column;
        }

        .crime-form .form-right {
            flex: 1 1 450px;
        }

        /* Map balandligini sozlash */
        #map {
            height: 100%;
            min-height: 450px;
            border-radius: 15px;
        }

        /* === Alerts === */
        .alert.success {
            background: #d4edda;
            color: #155724;
            padding: 12px 18px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        /* === Responsive === */
        @media(max-width:768px) {
            .info-box {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-lg py-2">
        <div class="container-fluid">
            <a href="index.php" class="btn btn-light btn-sm me-3 fw-semibold">
                <i class="bi bi-house-door-fill me-1"></i> Asosiy
            </a>
            <span class="navbar-brand mb-0 h5 d-flex align-items-center gap-2 text-white">
                <i class="bi bi-geo-alt-fill"></i>
                <?= htmlspecialchars($mahalla['mahalla_nomi']) ?> —
                <?= htmlspecialchars($mahalla['tuman_nomi']) ?>,
                <?= htmlspecialchars($mahalla['viloyat_nomi']) ?>
            </span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarMenu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarMenu"></div>
        </div>
    </nav>

    <div class="container">
        <h1>🗺️ Jinoyat qo‘shish</h1>

        <div class="info-box">
            <span><b>Viloyat:</b> <?= htmlspecialchars($mahalla['viloyat_nomi']) ?></span>
            <span><b>Tuman:</b> <?= htmlspecialchars($mahalla['tuman_nomi']) ?></span>
            <span><b>Mahalla:</b> <?= htmlspecialchars($mahalla['mahalla_nomi']) ?></span>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="alert alert-success">Qo‘shildi!</div>
        <?php endif; ?>

        <form method="post" class="crime-form">
            <div class="form-grid">
                <div class="form-left">
                    <label>JK modda:</label>
                    <input type="text" name="jk_modda" required>

                    <label>Qismi:</label>
                    <input type="text" name="qismi">

                    <label>Bandi:</label>
                    <input type="text" name="bandi">

                    <label>Jinoyat turi:</label>
                    <select name="ogrilik_turi" id="ogrilik_turi" onchange="updateMarkerColor()" required>
                        <option value="">Tanlang...</option>
                        <option value="ijtimoiy xavfi katta bo‘lmagan">ijtimoiy xavfi katta bo‘lmagan</option>
                        <option value="uncha og‘ir bo‘lmagan">uncha og‘ir bo‘lmagan</option>
                        <option value="og‘ir">og‘ir</option>
                        <option value="o‘ta og‘ir">o‘ta og‘ir</option>
                    </select>

                    <label>Sodir vaqti:</label>
                    <input type="datetime-local" name="sodir_vaqti">

                    <label>Jinoyat matni:</label>
                    <textarea name="jinoyat_matni" rows="5"></textarea>

                    <button type="submit">Qo‘shish</button>
                </div>

                <div class="form-right">
                    <div id="map"></div>
                </div>
            </div>

            <input type="hidden" name="lat" id="lat">
            <input type="hidden" name="lng" id="lng">
        </form>

    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Xaritani sozlash
        let map = L.map('map').setView([41.3111, 69.2797], 12);
        L.tileLayer(
            'https://core-renderer-tiles.maps.yandex.net/tiles?l=map&v=21.12.09-0&x={x}&y={y}&z={z}&scale=1&lang=ru_RU').addTo(map);

        let marker = null;
        let polygonLayer = null;

        // Mahalla poligoni
        const polygonData = <?= json_encode($mahalla['polygon']) ?>;
        if (polygonData) {
            try {
                const geo = JSON.parse(polygonData);
                polygonLayer = L.geoJSON(geo, {
                    color: '#2575fc',
                    weight: 3,
                    fillColor: '#6a11cb',
                    fillOpacity: 0.2
                }).addTo(map);
                map.fitBounds(polygonLayer.getBounds());
            } catch (e) {
                console.error("Poligon JSON xatolik:", e);
            }
        }

        // Marker qo‘yish
        map.on('click', e => {
            const {
                lat,
                lng
            } = e.latlng;
            document.getElementById('lat').value = lat.toFixed(6);
            document.getElementById('lng').value = lng.toFixed(6);
            if (marker) marker.setLatLng(e.latlng);
            else marker = L.marker(e.latlng, {
                draggable: true
            }).addTo(map);
            updateMarkerColor();
        });

        // Marker rangi
        function updateMarkerColor() {
            if (!marker) return;
            const type = document.getElementById('ogrilik_turi').value;
            let color = 'blue';
            switch (type) {
                case 'ijtimoiy xavfi katta bo‘lmagan':
                    color = 'yellow';
                    break;
                case 'uncha og‘ir bo‘lmagan':
                    color = 'green';
                    break;
                case 'og‘ir':
                    color = 'red';
                    break;
                case 'o‘ta og‘ir':
                    color = 'black';
                    break;
            }
            marker.setIcon(L.icon({
                iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-${color}.png`,
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png'
            }));
        }

        // Eski jinoyat markerlarini ko‘rsatish
        <?php foreach ($crimess as $c): if ($c['lat'] && $c['lng']): ?>
                L.marker([<?= $c['lat'] ?>, <?= $c['lng'] ?>])
                    .addTo(map)
                    .bindPopup(`<b>JK:</b> <?= addslashes($c['jk_modda']) ?><br><b>Og‘irlik:</b> <?= addslashes($c['ogrilik_turi']) ?>`);
        <?php endif;
        endforeach; ?>
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>