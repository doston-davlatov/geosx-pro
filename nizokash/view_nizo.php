<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once dirname(__DIR__) . '/connection/pdo.php';

$mahalla_id = intval($_GET['id'] ?? 0);
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

// Faol nizokash oilalar
$stmt = $pdo->prepare("
    SELECT n.*, u.username as operator_name 
    FROM nizokash_oilalar n
    LEFT JOIN users u ON n.operator_id = u.id
    WHERE n.mahalla_id = ? AND n.status = 'faol'
    ORDER BY n.sana DESC
");
$stmt->execute([$mahalla_id]);
$nizolar = $stmt->fetchAll();

// Statistika
$stmt2 = $pdo->prepare("
    SELECT 
        COUNT(*) as jami_oila,
        SUM(azolar_soni) as jami_odam
    FROM nizokash_oilalar 
    WHERE mahalla_id = ? AND status = 'faol'
");
$stmt2->execute([$mahalla_id]);
$stats = $stmt2->fetch();

// Yillar ro‘yxati
$years = array_unique(array_column($nizolar, 'sana'));
$years = array_filter($years, fn($d) => $d);
$years = array_map(fn($d) => date('Y', strtotime($d)), $years);
$years = array_unique($years);
rsort($years);
?>

<!DOCTYPE html>
<html lang="uz" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($mahalla['nomi']) ?> – Nizokash oilalar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --danger: #3540dcff;
            --darkred: #2326c8ff;
        }

        body {
            background: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }

        #map {
            height: 92vh;
            border-radius: 16px;
            border: 4px solid var(--danger);
            box-shadow: 0 10px 30px rgba(220, 53, 69, 0.2);
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(90deg, var(--darkred), var(--danger));
            color: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            font-size: 1.3rem;
            font-weight: bold;
        }

        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
            margin-bottom: 20px;
        }

        .navbar {
            background: var(--danger) !important;
            border-bottom: 4px solid var(--darkred);
        }

        .btn-add {
            background: var(--danger);
            border: none;
        }

        .btn-add:hover {
            background: var(--darkred);
        }

        .red-badge {
            background: var(--danger);
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-dark sticky-top shadow-lg">
        <div class="container-fluid">
            <a href="../index.php" class="btn btn-light btn-sm me-3">Asosiy</a>
            <h5 class="navbar-brand mb-0 d-flex align-items-center gap-2">
                Nizokash oilalar — <?= htmlspecialchars($mahalla['nomi']) ?>
            </h5>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row g-4">
            <!-- Chap panel -->
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <h5 class="text-danger fw-bold mb-3">Mahalla ma'lumotlari</h5>
                        <p><strong>Viloyat:</strong> <?= htmlspecialchars($mahalla['viloyat_nomi']) ?></p>
                        <p><strong>Tuman:</strong> <?= htmlspecialchars($mahalla['tuman_nomi']) ?></p>
                        <p><strong>Mahalla:</strong> <?= htmlspecialchars($mahalla['nomi']) ?></p>
                        <hr>
                        <div class="row g-3 text-center">
                            <div class="col-6">
                                <div class="stat-card"><?= $stats['jami_oila'] ?? 0 ?><br><small>Oilalar</small></div>
                            </div>
                            <div class="col-6">
                                <div class="stat-card"><?= $stats['jami_odam'] ?? 0 ?><br><small>Barcha oila a'zolari</small></div>
                            </div>
                        </div>
                        <a href="add_nizo.php?id=<?= $mahalla_id ?>" class="btn btn-add text-white w-100 mt-3 fw-bold">
                            + Yangi nizokash oila
                        </a>
                    </div>
                </div>

                <!-- Filtrlash -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="text-danger fw-bold">Filtrlash</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <select id="filterYear" class="form-select form-select-sm rounded-pill">
                                    <option value="">Barcha yillar</option>
                                    <?php foreach ($years as $y): ?>
                                        <option value="<?= $y ?>"><?= $y ?> yil</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3 text-end">
                            <button onclick="applyFilters()" class="btn btn-danger btn-sm">Qo‘llash</button>
                            <button onclick="resetFilters()" class="btn btn-outline-secondary btn-sm">Tozalash</button>
                        </div>
                    </div>
                </div>

                <!-- Diagrammalar -->
                <div class="chart-container">
                    <canvas id="chartFamilySize"></canvas>
                </div>
            </div>

            <!-- Xarita -->
            <div class="col-lg-8">
                <div id="map"></div>
                <!-- <div class="chart-container">
                    <canvas id="chartTrend"></canvas>
                </div> -->
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const nizolar = <?= json_encode($nizolar, JSON_UNESCAPED_UNICODE) ?>;
        let activeYear = '';

        // Xarita yaratish
        const centerLat = <?= $mahalla['markaz_lat'] ?? 41.3111 ?>;
        const centerLng = <?= $mahalla['markaz_lng'] ?? 69.2797 ?>;

        const map = L.map('map').setView([centerLat, centerLng], 14);
        L.tileLayer('https://core-renderer-tiles.maps.yandex.net/tiles?l=map&x={x}&y={y}&z={z}&scale=1&lang=ru_RU').addTo(map);

        const markers = L.layerGroup().addTo(map);

        // Qizil marker
        const redIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize: [30, 46],
            iconAnchor: [15, 46],
            popupAnchor: [0, -40]
        });

        // Mahalla chegarasi (xavfsiz JSON.parse)
        const polygonJson = <?= json_encode($mahalla['polygon'] ?? null) ?>;
        if (polygonJson) {
            try {
                const geo = typeof polygonJson === 'string' ? JSON.parse(polygonJson) : polygonJson;
                L.geoJSON(geo, {
                    style: {
                        color: "#3546dcff",
                        weight: 6,
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

        function updateMap() {
            markers.clearLayers();
            nizolar.forEach(n => {
                if (!n.lat || !n.lng) return;
                if (activeYear && new Date(n.sana).getFullYear() != activeYear) return;

                L.marker([n.lat, n.lng], {
                        icon: redIcon
                    }).addTo(markers)
                    .bindPopup(`
                <div class="p-3" style="min-width:280px;">
                    <h6 class="fw-bold text-danger">${n.fish}</h6>
                    <small><b>A'zolar soni:</b> ${n.azolar_soni} nafar</small><br>
                    <small><b>Sabab:</b> ${n.sabab}</small><br>
                    <small><b>Sana:</b> ${new Date(n.sana).toLocaleDateString('uz-UZ')}</small>
                    ${n.izoh ? `<hr><small><em>${n.izoh}</em></small>` : ''}
                </div>
             `, {
                        maxWidth: 400
                    });
            });
        }

        // Diagrammalar
        const sizeData = {
            '1-3': 0,
            '4-6': 0,
            '7-10': 0,
            '10+': 0
        };
        nizolar.forEach(n => {
            const s = parseInt(n.azolar_soni);
            if (s <= 3) sizeData['1-3']++;
            else if (s <= 6) sizeData['4-6']++;
            else if (s <= 10) sizeData['7-10']++;
            else sizeData['10+']++;
        });

        new Chart(document.getElementById('chartFamilySize'), {
            type: 'doughnut',
            data: {
                labels: ['1-3 kishi', '4-6 kishi', '7-10 kishi', '10+ kishi'],
                datasets: [{
                    data: Object.values(sizeData),
                    backgroundColor: ['#ff6b6b', '#ee5a52', '#dc3545', '#c82333'],
                    borderWidth: 4,
                    borderColor: '#fff',
                    hoverOffset: 20
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Oiladagi odamlar soni bo‘yicha'
                    }
                }
            }
        });

        const yearData = {};
        nizolar.forEach(n => {
            const y = new Date(n.sana).getFullYear();
            yearData[y] = (yearData[y] || 0) + 1;
        });
        const years = Object.keys(yearData).sort();

        new Chart(document.getElementById('chartTrend'), {
            type: 'line',
            data: {
                labels: years,
                datasets: [{
                    label: 'Nizokash oilalar',
                    data: years.map(y => yearData[y]),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220,53,69,0.2)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Yillar bo‘yicha trend'
                    }
                },
                onClick: (_, el) => {
                    activeYear = el[0] ? parseInt(years[el[0].index]) : '';
                    updateMap();
                }
            }
        });

        function applyFilters() {
            activeYear = document.getElementById('filterYear').value;
            updateMap();
        }

        function resetFilters() {
            activeYear = '';
            document.getElementById('filterYear').value = '';
            updateMap();
        }

        updateMap(); // Dastlabki yuklash
    </script>
</body>

</html>