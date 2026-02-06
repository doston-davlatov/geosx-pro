<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once dirname(__DIR__) . '/connection/pdo.php';


$mahalla_id = $_GET['id'] ?? 0;
if (!$mahalla_id) die("Mahalla ID kerak!");

$stmt = $pdo->prepare("
    SELECT m.*, t.nomi as tuman_nomi, v.nomi as viloyat_nomi 
    FROM mahallelar m
    JOIN tumanlar t ON m.tuman_id = t.id
    JOIN viloyatlar v ON t.viloyat_id = v.id
    WHERE m.id = ?
");
$stmt->execute([$mahalla_id]);
$mahalla = $stmt->fetch();
if (!$mahalla) die("Mahalla topilmadi!");

// Saqlash
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        INSERT INTO nizokash_oilalar 
        (fish, azolar_soni, sabab, sana, viloyat_id, tuman_id, mahalla_id, manzil, lat, lng, operator_id, status, izoh)
        VALUES 
        (:fish, :azolar_soni, :sabab, :sana, :viloyat_id, :tuman_id, :mahalla_id, :manzil, :lat, :lng, :operator_id, 'faol', :izoh)
    ");
    $stmt->execute([
        ':fish' => $_POST['fish'],
        ':azolar_soni' => $_POST['azolar_soni'],
        ':sabab' => $_POST['sabab'],
        ':sana' => $_POST['sana'],
        ':viloyat_id' => $mahalla['viloyat_id'],
        ':tuman_id' => $mahalla['tuman_id'],
        ':mahalla_id' => $mahalla_id,
        ':manzil' => $_POST['manzil'] ?? null,
        ':lat' => $_POST['lat'] ?? null,
        ':lng' => $_POST['lng'] ?? null,
        ':operator_id' => $_SESSION['user_id'],
        ':izoh' => $_POST['izoh'] ?? null
    ]);

    header("Location: ?id=$mahalla_id&success=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <title>Nizokash oila qo‘shish</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1150px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }

        .header {
            background: linear-gradient(135deg, #2a27f7ff, #2333c8ff);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 26px;
        }

        .info {
            background: #f8d7da;
            color: #1c2972ff;
            padding: 15px;
            text-align: center;
            font-weight: 600;
        }

        form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            padding: 30px;
        }

        .form-left,
        .form-right {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        label {
            font-weight: 600;
            color: #333;
        }

        input,
        select,
        textarea {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 15px;
        }

        button {
            grid-column: span 2;
            background: #3835dcff;
            color: white;
            padding: 15px;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 600;
            cursor: pointer;
        }

        button:hover {
            background: #2341c8ff;
        }

        #map {
            height: 520px;
            border-radius: 14px;
            border: 3px solid #3540dcff;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin: 20px;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            form {
                grid-template-columns: 1fr;
            }

            #map {
                height: 400px;
            }
        }
    </style>
    <style>
        .custom-navbar {
            background: linear-gradient(135deg, #2a27f7ff, #2333c8ff);
            padding: 15px 25px;
            border-radius: 14px;
            margin-bottom: 20px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .back-btn {
            background: #ffffff;
            color: #2333c8ff;
            padding: 8px 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .back-btn i {
            font-size: 18px;
        }

        .back-btn:hover {
            background: #e2e6ff;
        }

        .nav-center {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .nav-center i {
            font-size: 22px;
        }

        .title {
            letter-spacing: .5px;
        }

        .custom-navbar .nav-right {
            min-width: 80px;
            /* muvozanat uchun */
        }

        @media(max-width: 768px) {
            .custom-navbar {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <nav class="custom-navbar">
        <div class="nav-left">
            <a href="index.php" class="back-btn">
                <i class="bi bi-arrow-left-circle"></i> Asosiy
            </a>
        </div>

        <div class="nav-center">
            <i class="bi bi-geo-alt-fill"></i>
            <span class="title">
                <?= htmlspecialchars($mahalla['nomi']) ?> —
                <?= htmlspecialchars($mahalla['tuman_nomi']) ?>,
                <?= htmlspecialchars($mahalla['viloyat_nomi']) ?>
            </span>
        </div>

        <div class="nav-right">
            <!-- bo‘sh qoldirish mumkin -->
        </div>
    </nav>


    <div class="container">
        <div class="header">
            <h1>Nizokash oilani ro‘yxatga olish</h1>
        </div>
        <div class="info">
            <b>Viloyat:</b> <?= htmlspecialchars($mahalla['viloyat_nomi']) ?> |
            <b>Tuman:</b> <?= htmlspecialchars($mahalla['tuman_nomi']) ?> |
            <b>Mahalla:</b> <?= htmlspecialchars($mahalla['nomi']) ?>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="success">Nizokash oila muvaffaqiyatli qo‘shildi!</div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-left">
                <label>Oilaboshlining F.I.Sh</label>
                <input type="text" name="fish" required placeholder="Masalan: To‘rayev Botirjon Sobirovich">

                <label>Oiladagi azolar soni</label>
                <input type="number" name="azolar_soni" min="1" max="20" value="1" required>

                <label>Nizolashishga tushish sababi</label>
                <textarea name="sabab" rows="4" required placeholder="Iqtisodiy qiyinchilik, ishsizlik, kasallik..."></textarea>

                <label>Ro‘yxatga olingan sana</label>
                <input type="date" name="sana" value="<?= date('Y-m-d') ?>" required>

                <label>To‘liq manzil (ixtiyoriy)</label>
                <input type="text" name="manzil" placeholder="Masalan: 45-uy, 12-xonadon">

                <label>Qo'shimcha izoh</label>
                <textarea name="izoh" rows="3" placeholder="Qo‘shimcha ma'lumotlar..."></textarea>
            </div>

            <div class="form-right">
                <div id="map"></div>
                <small style="color:#721c24; text-align:center; display:block; margin-top:12px; font-weight:600;">
                    Xaritadan uy joyini bosing → qizil marker paydo bo‘ladi
                </small>
            </div>

            <input type="hidden" name="lat" id="lat">
            <input type="hidden" name="lng" id="lng">
            <button type="submit">Saqlash</button>
        </form>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const centerLat = <?= $mahalla['markaz_lat'] ?? 41.3111 ?>;
        const centerLng = <?= $mahalla['markaz_lng'] ?? 69.2797 ?>;

        const map = L.map('map').setView([centerLat, centerLng], 14);
        L.tileLayer('https://core-renderer-tiles.maps.yandex.net/tiles?l=map&x={x}&y={y}&z={z}&scale=1&lang=ru_RU').addTo(map);

        let marker = null;
        const redIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize: [30, 46],
            iconAnchor: [15, 46],
            popupAnchor: [0, -40]
        });

        const polygonJson = <?= json_encode($mahalla['polygon'] ?? null) ?>;
        if (polygonJson) {
            try {
                const geo = typeof polygonJson === 'string' ? JSON.parse(polygonJson) : polygonJson;
                L.geoJSON(geo, {
                    style: {
                        color: "#171bffff",
                        weight: 5,
                        fillOpacity: 0.15
                    }
                }).addTo(map);
                map.fitBounds(L.geoJSON(geo).getBounds(), {
                    padding: [50, 50]
                });
            } catch (e) {
                console.warn("Poligon yuklanmadi:", e);
            }
        }

        map.on('click', e => {
            const {
                lat,
                lng
            } = e.latlng;
            document.getElementById('lat').value = lat.toFixed(8);
            document.getElementById('lng').value = lng.toFixed(8);

            if (marker) marker.setLatLng(e.latlng);
            else marker = L.marker(e.latlng, {
                draggable: true,
                icon: redIcon
            }).addTo(map);

            marker.bindPopup("<b style='color:#dc3545'>Nizokash oila</b><br>Bu yerda yashaydi").openPopup();

            marker.on('dragend', ev => {
                const pos = ev.target.getLatLng();
                document.getElementById('lat').value = pos.lat.toFixed(8);
                document.getElementById('lng').value = pos.lng.toFixed(8);
            });

            marker.on('dblclick', () => {
                map.removeLayer(marker);
                marker = null;
                document.getElementById('lat').value = '';
                document.getElementById('lng').value = '';
            });
        });

        document.querySelector('form').addEventListener('submit', e => {
            if (!document.getElementById('lat').value) {
                e.preventDefault();
                alert("Iltimos, xaritadan oila joylashgan uyini belgilang!");
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>