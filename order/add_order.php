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

// Ma'lumot saqlash
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        INSERT INTO order_olganlar 
        (fish, order_nomi, order_darajasi, berilgan_sana, berilgan_joy, 
         viloyat_id, tuman_id, mahalla_id, lat, lng, operator_id, rasmiy_hujjat, izoh)
        VALUES 
        (:fish, :order_nomi, :order_darajasi, :berilgan_sana, :berilgan_joy,
         :viloyat_id, :tuman_id, :mahalla_id, :lat, :lng, :operator_id, :rasmiy_hujjat, :izoh)
    ");
    $stmt->execute([
        ':fish' => $_POST['fish'],
        ':order_nomi' => $_POST['order_nomi'],
        ':order_darajasi' => $_POST['order_darajasi'] ?? '',
        ':berilgan_sana' => $_POST['berilgan_sana'],
        ':berilgan_joy' => $_POST['berilgan_joy'] ?? '',
        ':viloyat_id' => $mahalla['viloyat_id'],
        ':tuman_id' => $mahalla['tuman_id'],
        ':mahalla_id' => $mahalla_id,
        ':lat' => $_POST['lat'] ?? null,
        ':lng' => $_POST['lng'] ?? null,
        ':operator_id' => $_SESSION['user_id'],
        ':rasmiy_hujjat' => $_POST['rasmiy_hujjat'] ?? '',
        ':izoh' => $_POST['izoh'] ?? ''
    ]);

    header("Location: ?id=$mahalla_id&success=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <title>Order olgan ayol qo‘shish</title>
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
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .info {
            background: #f8f9fa;
            padding: 15px;
            text-align: center;
            font-weight: 600;
        }

        form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 30px;
        }

        .form-left,
        .form-right {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        label {
            font-weight: 600;
            color: #333;
        }

        input,
        select,
        textarea {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
        }

        button {
            grid-column: span 2;
            background: #667eea;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #5a6fd8;
        }

        #map {
            height: 500px;
            border-radius: 12px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 20px;
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
            <h1>Order olgan ayolni ro‘yxatga olish</h1>
        </div>
        <div class="info-box info">
            <b>Viloyat:</b> <?= htmlspecialchars($mahalla['viloyat_nomi']) ?> |
            <b>Tuman:</b> <?= htmlspecialchars($mahalla['tuman_nomi']) ?> |
            <b>Mahalla:</b> <?= htmlspecialchars($mahalla['nomi']) ?>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="success">Ma'lumot muvaffaqiyatli saqlandi!</div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-left">
                <label>F.I.Sh (to‘liq)</label>
                <input type="text" name="fish" required placeholder="Masalan: Karimova Gulbahor Xojayevna">

                <label>Order nomi</label>
                <input type="text" name="order_nomi" required placeholder="Masalan: «O‘zbekiston Qahramoni»">

                <label>Darajasi (agar mavjud bo‘lsa)</label>
                <select name="order_darajasi">
                    <option value="">Yo‘q</option>
                    <option value="1-daraja">1-daraja</option>
                    <option value="2-daraja">2-daraja</option>
                </select>

                <label>Topshirilgan sana</label>
                <input type="date" name="berilgan_sana" required>

                <label>Topshirilgan joy (ixtiyoriy)</label>
                <input type="text" name="berilgan_joy" placeholder="Masalan: Toshkent sh., Mustaqillik maydoni">

                <label>Farmon / hujjat raqami (ixtiyoriy)</label>
                <input type="text" name="rasmiy_hujjat" placeholder="Masalan: PF-6789">

                <label>Izoh (ixtiyoriy)</label>
                <textarea name="izoh" rows="3" placeholder="Qo‘shimcha ma'lumotlar..."></textarea>
            </div>

            <div class="form-right">
                <div id="map"></div>
                <small style="color:#666; text-align:center; display:block; margin-top:10px;">
                    Xaritadan uy joyini bosing yoki marker ni sudrab joylashtiring
                </small>
            </div>

            <input type="hidden" name="lat" id="lat">
            <input type="hidden" name="lng" id="lng">
            <button type="submit">Saqlash</button>
        </form>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Xarita yaratish – mahalla markazidan boshlanadi, agar mavjud bo‘lsa
        const centerLat = <?= $mahalla['markaz_lat'] ?? 41.3111 ?>;
        const centerLng = <?= $mahalla['markaz_lng'] ?? 69.2797 ?>;

        const map = L.map('map').setView([centerLat, centerLng], 14);

        L.tileLayer('https://core-renderer-tiles.maps.yandex.net/tiles?l=map&x={x}&y={y}&z={z}&scale=1&lang=ru_RU', {
            attribution: '&copy; Yandex'
        }).addTo(map);

        let marker = null;

        // Oltin marker ikonkasi
        const goldIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-gold.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize: [30, 46],
            iconAnchor: [15, 46],
            popupAnchor: [0, -40],
            shadowSize: [41, 41]
        });

        // Mahalla chegarasini chizish (xavfsiz usul)
        const polygonJson = <?= json_encode($mahalla['polygon'] ?? null) ?>;

        if (polygonJson) {
            try {
                const geoJsonData = typeof polygonJson === 'string' ? JSON.parse(polygonJson) : polygonJson;

                const polygonLayer = L.geoJSON(geoJsonData, {
                    style: {
                        color: "#0011ffff",
                        weight: 5,
                        opacity: 0.9,
                        fillColor: "#0400ffff",
                        fillOpacity: 0.15
                    }
                }).addTo(map);

                // Avtomatik zoom – mahalla chegarasiga moslashadi
                map.fitBounds(polygonLayer.getBounds(), {
                    padding: [50, 50]
                });

            } catch (e) {
                console.warn("Poligon yuklanmadi:", e);
            }
        }

        // Xaritaga bosilganda marker qo‘yish
        map.on('click', function(e) {
            const {
                lat,
                lng
            } = e.latlng;

            // Koordinatalarni yozish
            document.getElementById('lat').value = lat.toFixed(8);
            document.getElementById('lng').value = lng.toFixed(8);

            // Agar marker allaqachon bo‘lsa – faqat joyini o‘zgartirish
            if (marker) {
                marker.setLatLng(e.latlng);
            } else {
                // Yangi marker yaratish
                marker = L.marker(e.latlng, {
                    draggable: true,
                    icon: goldIcon
                }).addTo(map);

                // Drag tugaganda yangi koordinatalarni saqlash
                marker.on('dragend', function(ev) {
                    const pos = ev.target.getLatLng();
                    document.getElementById('lat').value = pos.lat.toFixed(8);
                    document.getElementById('lng').value = pos.lng.toFixed(8);
                });

                // Double-click bilan marker o‘chirish
                marker.on('dblclick', function() {
                    map.removeLayer(marker);
                    marker = null;
                    document.getElementById('lat').value = '';
                    document.getElementById('lng').value = '';
                });
            }

            // Popup bilan ko‘rsatish
            marker.bindPopup("<b>Order olgan ayol joylashuvi</b><br>Bu yerda yashaydi").openPopup();
        });

        // Forma yuborilganda – joy tanlanganligini tekshirish
        document.querySelector('form').addEventListener('submit', function(e) {
            const lat = document.getElementById('lat').value;
            const lng = document.getElementById('lng').value;

            if (!lat || !lng) {
                e.preventDefault();
                alert("Iltimos, xaritadan uy joyini belgilang!");
                return false;
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>