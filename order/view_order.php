<?php
require_once dirname(__DIR__) . '/connection/config.php';
$db = new Database();

$id = intval($_GET['id'] ?? 0);
if (!$id) die("<h3 class='text-danger text-center'>Mahalla ID kerak!</h3>");

$mahalla = $db->queryRow("
    SELECT m.*, v.nomi as viloyat_nomi, t.nomi as tuman_nomi 
    FROM mahallelar m
    JOIN viloyatlar v ON m.viloyat_id = v.id
    JOIN tumanlar t ON m.tuman_id = t.id
    WHERE m.id = ?
", [$id]);

if (!$mahalla) die("<h3 class='text-danger text-center'>Mahalla topilmadi!</h3>");

$orders = $db->query("
    SELECT o.*, u.username as operator_name 
    FROM order_olganlar o
    LEFT JOIN users u ON o.operator_id = u.id
    WHERE o.mahalla_id = ? 
    ORDER BY o.berilgan_sana DESC
", [$id]);

// Statistika
$stats = $db->queryRow("
    SELECT 
        COUNT(*) as jami,
        SUM(order_darajasi = '1-daraja') as birinchi_daraja,
        SUM(order_darajasi = '2-daraja') as ikkinchi_daraja,
        SUM(order_darajasi = '' OR order_darajasi IS NULL) as oddiy
    FROM order_olganlar 
    WHERE mahalla_id = ?
", [$id]);

$polygonData = !empty($mahalla['polygon']) ? json_decode($mahalla['polygon'], true) : null;
if ($polygonData && json_last_error() !== JSON_ERROR_NONE) $polygonData = null;
?>

<!DOCTYPE html>
<html lang="uz" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($mahalla['nomi']) ?> | Order olganlar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #0011ffff;
            --gold: #ffb800;
            --darkgold: #b8860b;
        }

        body {
            background: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }

        #map {
            height: 89.5vh;
            border-radius: 16px;
            border: 3px solid var(--primary);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(90deg, #000314ff, var(--primary), #000314ff);
            color: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
        }

        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
            margin-bottom: 20px;
        }

        #ddr{
            padding-top: 30px;
        }

        [data-bs-theme="dark"] .chart-container {
            background: #1e1e1e;
        }

        .navbar {
            border-bottom: 3px solid var(--primary);
            background: #1a1a1a !important;
        }

        .gold-badge {
            background: linear-gradient(45deg, #ffd700, #ffb800);
            color: #000;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-dark sticky-top shadow-lg">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <a href="index.php" class="btn btn-light btn-sm me-3">Asosiy</a>
                <h5 class="navbar-brand mb-0 d-flex align-items-center gap-2">
                    Order olganlar — <?= htmlspecialchars($mahalla['nomi']) ?>
                </h5>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row g-4">
            <!-- Chap panel -->
            <div class="col-lg-4">
                <div class="card mb-4 shadow">
                    <div class="card-body">
                        <h5 class="card-title text-primary mb-3">Mahalla ma'lumotlari</h5>
                        <p><strong>Viloyat:</strong> <?= htmlspecialchars($mahalla['viloyat_nomi']) ?></p>
                        <p><strong>Tuman:</strong> <?= htmlspecialchars($mahalla['tuman_nomi']) ?></p>
                        <p><strong>Mahalla:</strong> <?= htmlspecialchars($mahalla['nomi']) ?></p>
                        <hr>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="stat-card"><b><small>Jami: </small><?= $stats['jami'] ?? 0 ?></b></div>
                            </div>
                        </div>
                        <button onclick="handleClick(<?= $id ?>)" class="btn btn-primary w-100 mt-3">+ Yangi qo‘shish</button>
                    </div>
                </div>

                <!-- Filtrlash -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title text-primary mb-3">Filtrlash</h5>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small text-muted">Yil</label>
                                <select id="filterYear" class="form-select form-select-sm rounded-pill">
                                    <option value="">Barcha yillar</option>
                                    <?php
                                    $years = array_unique(array_map(fn($o) => date('Y', strtotime($o['berilgan_sana'] ?? '')), $orders));
                                    rsort($years);
                                    foreach ($years as $y): ?>
                                        <option value="<?= $y ?>"><?= $y ?> yil</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3 text-end">
                            <button onclick="applyFilters()" class="btn btn-primary btn-sm">Qo‘llash</button>
                            <button onclick="resetFilters()" class="btn btn-outline-secondary btn-sm">Tozalash</button>
                        </div>
                    </div>
                </div>

                <!-- <div class="chart-container">
                    <canvas id="chartDaraja"></canvas>
                </div> -->
                <div class="chart-container">
                    <canvas id="chartTrend"></canvas>
                </div>
            </div>
            
            <!-- Xarita -->
            <div class="col-lg-8">
                <div id="map"></div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const orders = <?= json_encode($orders, JSON_UNESCAPED_UNICODE) ?>;
        let activeDaraja = '';
        let activeYear = '';

        const map = L.map('map').setView([<?= $mahalla['markaz_lat'] ?? 39.77 ?>, <?= $mahalla['markaz_lng'] ?? 64.43 ?>], 15);
        L.tileLayer('https://core-renderer-tiles.maps.yandex.net/tiles?l=map&x={x}&y={y}&z={z}&scale=1&lang=ru_RU').addTo(map);

        const markers = L.layerGroup().addTo(map);

        const goldIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-gold.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize: [30, 46],
            iconAnchor: [15, 46],
            popupAnchor: [0, -40]
        });

        function updateMap() {
            markers.clearLayers();
            orders.forEach(o => {
                if (!o.lat || !o.lng) return;

                const year = new Date(o.berilgan_sana).getFullYear();
                const daraja = o.order_darajasi === '1-daraja' ? '1-daraja' :
                    o.order_darajasi === '2-daraja' ? '2-daraja' : 'oddiy';

                if (activeDaraja && activeDaraja !== daraja) return;
                if (activeYear && year != activeYear) return;

                L.marker([o.lat, o.lng], {
                        icon: goldIcon
                    }).addTo(markers)
                    .bindPopup(`
                        <div style="
                            max-width: 340px; 
                            max-height: 70vh; 
                            overflow-y: auto; 
                            padding-right: 8px;
                            font-family: system-ui, sans-serif;
                            line-height: 1.5;
                        ">
                            <div class="p-3 bg-white rounded shadow-sm">
                                <h6 class="fw-bold text-primary mb-2">${o.fish}</h6>
                                
                                <div class="mb-3">
                                    <span class="badge gold-badge fs-6 px-3 py-2">${o.order_nomi}</span>
                                    ${o.order_darajasi ? 
                                        `<span class="badge bg-dark ms-2">${o.order_darajasi}</span>` : ''
                                    }
                                </div>
                    
                                <small class="text-muted d-block mb-1">
                                    <i class="bi bi-calendar-check"></i> 
                                    ${new Date(o.berilgan_sana).toLocaleDateString('uz-UZ', {
                                        day: '2-digit', month: 'long', year: 'numeric'
                                    })}
                                </small>
                    
                                ${o.berilgan_joy ? `
                                    <small class="text-muted d-block mb-2">
                                        <i class="bi bi-geo-alt"></i> ${o.berilgan_joy}
                                    </small>` : ''
                                }
                    
                                ${o.operator_name ? `
                                    <small class="text-muted d-block">
                                        <i class="bi bi-person-check"></i> Operator: ${o.operator_name}
                                    </small>` : ''
                                }
                    
                                ${o.izoh ? `
                                    <hr class="my-3">
                                    <div class="text-secondary small">
                                        <strong>Izoh:</strong><br>
                                        <div style="max-height: 200px; overflow-y: auto; padding: 8px; background: #f8f9fa; border-radius: 8px;">
                                            ${o.izoh.replace(/\n/g, '<br>')}
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                        `, {                    
                        maxWidth: 500,
                        minWidth: 300
                    })
            });
        }

        // Mahalla chegarasi
        <?php if ($polygonData): ?>
            L.geoJSON(<?= json_encode($polygonData) ?>, {
                style: {
                    color: '#0400ffff',
                    weight: 5,
                    fillOpacity: 0.12
                }
            }).addTo(map).bringToBack();
            map.fitBounds(L.geoJSON(<?= json_encode($polygonData) ?>).getBounds());
        <?php endif; ?>

        // Diagrammalar
        new Chart(document.getElementById('chartDaraja'), {
            type: 'doughnut',
            data: {
                labels: ['1-daraja', '2-daraja', 'Oddiy'],
                datasets: [{
                    data: [<?= $stats['birinchi_daraja'] ?? 0 ?>, <?= $stats['ikkinchi_daraja'] ?? 0 ?>, <?= $stats['oddiy'] ?? 0 ?>],
                    backgroundColor: ['#b8860b', '#ffd700', '#ffec8b'],
                    borderWidth: 4,
                    borderColor: '#fff',
                    hoverOffset: 25
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    title: {
                        display: true,
                        text: 'Daraja bo‘yicha'
                    }
                },
                onClick: (_, el) => {
                    activeDaraja = el[0] ? ['1-daraja', '2-daraja', 'oddiy'][el[0].index] : '';
                    updateMap();
                }
            }
        });

        const yearData = {};
        orders.forEach(o => {
            const y = new Date(o.berilgan_sana).getFullYear();
            yearData[y] = (yearData[y] || 0) + 1;
        });
        const years = Object.keys(yearData).sort();

        new Chart(document.getElementById('chartTrend'), {
            type: 'line',
            data: {
                labels: years,
                datasets: [{
                    label: 'Order berilganlar',
                    data: years.map(y => yearData[y]),
                    borderColor: '#ffd700',
                    backgroundColor: 'rgba(255,215,0,0.2)',
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
            const darajaVal = document.getElementById('filterDaraja').value;
            activeDaraja = darajaVal === 'oddiy' ? 'oddiy' : darajaVal;
            activeYear = document.getElementById('filterYear').value;
            updateMap();
        }

        function resetFilters() {
            activeDaraja = activeYear = '';
            document.getElementById('filterDaraja').value = '';
            document.getElementById('filterYear').value = '';
            updateMap();
        }

        function handleClick(id) {
            location.href = `../order/add_order.php?id=${id}&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>`;
        }

        updateMap(); // Dastlabki yuklash
    </script>
</body>

</html>