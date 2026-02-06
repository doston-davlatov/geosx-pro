<?php
require_once "../connection/config.php";
$db = new Database();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) die("<h3 class='text-center mt-5 text-danger'>MFY ID ko‘rsatilmagan</h3>");

$sql = "SELECT m.*, v.nomi AS viloyat_nomi, t.nomi AS tuman_nomi, u.username AS operator_nomi
        FROM mahallelar m
        LEFT JOIN viloyatlar v ON m.viloyat_id = v.id
        LEFT JOIN tumanlar t ON m.tuman_id = t.id
        LEFT JOIN users u ON m.operator_id = u.id
        WHERE m.id = ?";
$mahalla = $db->executeQuery($sql, [$id])->get_result()->fetch_assoc() ?: die("<h3 class='text-center mt-5 text-danger'>MFY topilmadi</h3>");

$crimes = $db->executeQuery("SELECT c.*, v.nomi AS viloyat_nomi, t.nomi AS tuman_nomi, m.nomi AS mahalla_nomi
        FROM crimes c
        LEFT JOIN viloyatlar v ON c.viloyat_id = v.id
        LEFT JOIN tumanlar t ON c.tuman_id = t.id
        LEFT JOIN mahallelar m ON c.mahalla_id = m.id
        WHERE c.mahalla_id = ? ORDER BY c.sodir_vaqti DESC", [$id])
    ->get_result()->fetch_all(MYSQLI_ASSOC);

$stats = $db->executeQuery("SELECT 
        COUNT(*) AS jami,
        SUM(ogrilik_turi='ijtimoiy xavfi katta bo‘lmagan') AS ijtimoiy,
        SUM(ogrilik_turi='uncha og‘ir bo‘lmagan') AS uncha,
        SUM(ogrilik_turi='og‘ir') AS ogir,
        SUM(ogrilik_turi='o‘ta og‘ir') AS otag
    FROM crimes WHERE mahalla_id = ?", [$id])->get_result()->fetch_assoc();

$polygonData = !empty($mahalla['polygon']) ? json_decode($mahalla['polygon'], true) : null;
if ($polygonData && json_last_error() !== JSON_ERROR_NONE) $polygonData = null;
?>

<!DOCTYPE html>
<html lang="uz" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($mahalla['nomi']) ?> | MFY Analitika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@latest/dist/jspdf.umd.min.js"></script>

    <style>
        :root {
            --primary: #4361ee;
            --success: #06d6a0;
            --warning: #ffbe0b;
            --danger: #ef476f;
            --dark: #073b4c;
            --light: #f8f9fa;
        }

        body {
            background: var(--light);
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        [data-bs-theme="dark"] body {
            background: #121212;
            color: #e0e0e0;
        }

        #map {
            height: 92vh;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            border: 3px solid var(--primary);
            margin-bottom: 32px;
        }

        .card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .stat-card {
            background: linear-gradient(90deg, #000314ff, var(--primary), #000314ff);
            color: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
        }

        /* 
        .stat-card:hover {
            transform: scale(1.05);
        } */

        .btn-primary {
            background: var(--primary);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
        }

        .btn-outline-primary {
            border-radius: 12px;
        }

        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
            margin-bottom: 20px;
        }

        [data-bs-theme="dark"] .chart-container {
            background: #1e1e1e;
        }

        .navbar {
            border-bottom: 3px solid var(--primary);
        }

        .theme-toggle {
            cursor: pointer;
            font-size: 1.4rem;
        }

        .marker-cluster-custom {
            background: rgba(67, 97, 238, 0.8);
            border: 3px solid white;
            border-radius: 50%;
            width: 40px !important;
            height: 40px !important;
            text-align: center;
            line-height: 34px;
            color: white;
            font-weight: bold;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.4);
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>

    <nav class="navbar navbar-dark bg-primary sticky-top shadow-lg">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <a href="index.php" class="btn btn-light btn-sm me-3">Asosiy</a>
                <h5 class="navbar-brand mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-geo-alt-fill"></i>
                    <?= htmlspecialchars($mahalla['nomi']) ?> — <?= htmlspecialchars($mahalla['tuman_nomi']) ?>, <?= htmlspecialchars($mahalla['viloyat_nomi']) ?>
                </h5>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row g-4">
            <!-- Chap panel -->
            <div class="col-lg-4">
                <!-- MFY info -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title text-primary mb-3">MFY Ma'lumotlari</h5>
                        <div class="row g-3">
                            <div class="col-12"><strong>Viloyat:</strong> <?= htmlspecialchars($mahalla['viloyat_nomi']) ?></div>
                            <div class="col-12"><strong>Tuman:</strong> <?= htmlspecialchars($mahalla['tuman_nomi']) ?></div>
                            <div class="col-12"><strong>Mahalla:</strong> <?= htmlspecialchars($mahalla['nomi']) ?></div>
                            <div class="col-12"><strong>Inspektor:</strong> <?= htmlspecialchars($mahalla['operator_nomi'] ?? 'Belgilanmagan') ?></div>
                        </div>

                        <div class="row g-3 mt-3">
                            <div class="col-6">
                                <div class="stat-card bg-warning"><b><?= $stats['ijtimoiy'] ?? 0 ?></b><small>ta Ijtimoiy xavfi katta bo‘lmagan</small></div>
                            </div>
                            <div class="col-6">
                                <div class="stat-card bg-success"><b><?= $stats['uncha'] ?? 0 ?></b><small>ta Uncha og‘ir bo'lmagan</small></div>
                            </div>
                            <div class="col-6">
                                <div class="stat-card bg-danger"><b><?= $stats['ogir'] ?? 0 ?></b><small>ta Og‘ir</small></div>
                            </div>
                            <div class="col-6">
                                <div class="stat-card bg-dark"><b><?= $stats['otag'] ?? 0 ?></b><small>ta O‘ta og‘ir</small></div>
                            </div>
                            <div class="col-12">
                                <div class="stat-card"><small>Jami jinoyat </small><b><?= $stats['jami'] ?? 0 ?></b></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtirlash -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title text-primary mb-3">
                            <i class="bi bi-funnel"></i> Filtrlash
                        </h5>

                        <div class="row g-3">
                            <!-- Modda bo‘yicha filtr -->
                            <div class="col-12">
                                <label class="form-label small text-muted">Jinoyat moddasi</label>
                                <input
                                    list="moddalar-list"
                                    id="filterModda"
                                    class="form-control form-control-sm rounded-pill"
                                    placeholder="Modda kiriting (masalan: 169)"
                                    autocomplete="off">
                                <datalist id="moddalar-list">
                                    <?php
                                    $uniqueModdalar = array_values(array_unique(array_filter(array_column($crimes, 'jk_modda'))));
                                    sort($uniqueModdalar);
                                    foreach ($uniqueModdalar as $modda):
                                        if (!empty(trim($modda))):
                                    ?>
                                            <option value="<?= htmlspecialchars($modda) ?>">
                                        <?php
                                        endif;
                                    endforeach;
                                        ?>
                                </datalist>
                            </div>

                            <!-- Og‘irlik turi -->
                            <div class="col-12">
                                <label class="form-label small text-muted">Og‘irlik turi</label>
                                <select id="filterType" class="form-select form-select-sm rounded-pill">
                                    <option value="">Barcha turlar</option>
                                    <option value="ijtimoiy xavfi katta bo‘lmagan">Ijtimoiy xavfi katta bo‘lmagan</option>
                                    <option value="uncha og‘ir bo‘lmagan">Uncha og‘ir bo‘lmagan</option>
                                    <option value="og‘ir">Og‘ir</option>
                                    <option value="o‘ta og‘ir">O‘ta og‘ir</option>
                                </select>
                            </div>

                            <!-- Yil bo‘yicha -->
                            <div class="col-12">
                                <label class="form-label small text-muted">Yil</label>
                                <select id="filterYear" class="form-select form-select-sm rounded-pill">
                                    <option value="">Barcha yillar</option>
                                    <?php
                                    $years = array_unique(array_map(fn($c) => date('Y', strtotime($c['sodir_vaqti'] ?? '')), $crimes));
                                    rsort($years);
                                    foreach ($years as $y):
                                    ?>
                                        <option value="<?= $y ?>"><?= $y ?> yil</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3 text-end">
                            <button onclick="applyFilters()" class="btn btn-primary btn-sm px-4 stat-card">
                                <i class="bi bi-search"></i> Qo‘llash
                            </button>
                            <button onclick="resetFilters()" class="btn btn-outline-secondary btn-sm ">
                                <i class="bi bi-arrow-repeat"></i> Tozalash
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Kontroller -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 text-primary">Analitika</h5>
                    <div>
                        <button onclick="handleClick(<?= $id ?>)" class="btn btn-primary me-2">+ Yangi</button>
                        <button onclick="resetFilters()" class="btn btn-outline-secondary btn-sm">Tozalash</button>
                    </div>
                </div>

                <!-- <div class="row g-2 mb-3">
                    <div class="col-6">
                        <select id="filterType" class="form-select form-select-sm rounded-pill">
                            <option value="">Barcha turlar</option>
                            <option value="ijtimoiy xavfi katta bo‘lmagan">Ijtimoiy</option>
                            <option value="uncha og‘ir bo‘lmagan">Uncha og‘ir</option>
                            <option value="og‘ir">Og‘ir</option>
                            <option value="o‘ta og‘ir">O‘ta og‘ir</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <select id="filterYear" class="form-select form-select-sm rounded-pill">
                            <option value="">Barcha yillar</option>
                            <?php foreach (array_unique(array_column($crimes, 'sodir_vaqti') ? array_map(fn($c) => date('Y', strtotime($c['sodir_vaqti'])), $crimes) : []) as $y): ?>
                                <option value="<?= $y ?>"><?= $y ?> yil</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div> -->
                <div class="chart-container">
                    <canvas id="chartSeverity"></canvas>
                </div>
            </div>

            <!-- Xarita -->
            <div class="col-lg-8">
                <div id="map"></div>
                <div class="chart-container">
                    <canvas id="chartTrend"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const crimes = <?= json_encode($crimes, JSON_UNESCAPED_UNICODE) ?>;
        let activeModda = '';
        let activeType = '';
        let activeYear = '';
        let activeMonth = '';

        // Xaritani yaratish
        const map = L.map('map').setView([<?= $mahalla['markaz_lat'] ?? 39.77 ?>, <?= $mahalla['markaz_lng'] ?? 64.43 ?>], 14);

        L.tileLayer('https://core-renderer-tiles.maps.yandex.net/tiles?l=map&x={x}&y={y}&z={z}&scale=1&lang=ru_RU', {
            attribution: '&copy; Yandex'
        }).addTo(map);

        const markers = L.layerGroup().addTo(map);

        // Rang bo‘yicha marker ikonka URL[](https://github.com/pointhi/leaflet-color-markers)
        function getMarkerIcon(color) {
            const icons = {
                'ijtimoiy xavfi katta bo‘lmagan': 'yellow',
                'uncha og‘ir bo‘lmagan': 'green',
                'og‘ir': 'red',
                'o‘ta og‘ir': 'black', //  qora
                'default': 'blue'
            };

            const iconColor = icons[color] || 'grey';

            return L.icon({
                iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-${iconColor}.png`,
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });
        }

        // Popup badge rangi uchun yordamchi funksiya
        function getBadgeClass(type) {
            const map = {
                'ijtimoiy xavfi katta bo‘lmagan': 'warning',
                'uncha og‘ir bo‘lmagan': 'success',
                'og‘ir': 'danger',
                'o‘ta og‘ir': 'dark'
            };
            return map[type] || 'secondary';
        }

        // Xaritani yangilash funksiyasi
        function updateMap() {
            markers.clearLayers();

            crimes.forEach(c => {
                if (!c.lat || !c.lng) return;

                const d = new Date(c.sodir_vaqti);

                // Modda filtri
                if (activeModda && !c.jk_modda?.includes(activeModda)) return;

                // Turi filtri
                if (activeType && c.ogrilik_turi !== activeType) return;

                // Yil filtri
                if (activeYear && d.getFullYear() != activeYear) return;

                // Qolgan kod (marker qo‘shish)
                const icon = getMarkerIcon(c.ogrilik_turi);

                L.marker([c.lat, c.lng], {
                        icon
                    })
                    .bindPopup(`
                    <div class="p-3" style="min-width: 260px; max-width: 340px;">
                        <h6 class="fw-bold mb-2">${c.jk_modda || 'Modda belgilanmagan'}</h6>
                        <span class="badge bg-${getBadgeClass(c.ogrilik_turi)} text-white fs-7">
                            ${c.ogrilik_turi || 'Turi belgilanmagan'}
                        </span>
                        <br><small class="text-muted">
                            <i class="bi bi-calendar"></i> ${new Date(c.sodir_vaqti).toLocaleDateString('uz-UZ')}
                        </small>
                        <hr class="my-2">
                        <div style="
                            max-height: 280px; 
                            overflow-y: auto; 
                            padding-right: 8px; 
                            font-size: 0.9rem; 
                            line-height: 1.5;
                            background: #f8f9fa; 
                            border-radius: 8px; 
                            padding: 10px;
                            border: 1px solid #dee2e6;
                        ">
                            <small class="text-secondary">
                                ${c.jinoyat_matni ? c.jinoyat_matni.replace(/\n/g, '<br>') : '<em>Tavsif mavjud emas</em>'}
                            </small>
                        </div>
                    </div>
                    `, {
                        maxWidth: 380
                    })
                    .addTo(markers);
            });
        }

        // Dastlabki yuklash
        updateMap();

        // Poligon (MFY chegarasi) chizish
        <?php if ($polygonData): ?>
            const polygonLayer = L.geoJSON(<?= json_encode($polygonData) ?>, {
                style: {
                    color: '#4361ee',
                    weight: 5,
                    opacity: 0.8,
                    fillColor: '#2f4fdaff',
                    fillOpacity: 0.08
                }
            }).addTo(map);

            map.fitBounds(polygonLayer.getBounds());
        <?php endif; ?>

        // Diagrammalar (o‘zgarmadi, faqat updateAll chaqirish qo‘shildi)
        new Chart(document.getElementById('chartSeverity'), {
            type: 'doughnut',
            data: {
                labels: ['Ijtimoiy xavfi katta bo‘lmagan', 'Uncha og‘ir bo‘lmagan', 'Og‘ir', 'O‘ta og‘ir'],
                datasets: [{
                    data: [<?= $stats['ijtimoiy'] ?? 0 ?>, <?= $stats['uncha'] ?? 0 ?>, <?= $stats['ogir'] ?? 0 ?>, <?= $stats['otag'] ?? 0 ?>],
                    backgroundColor: ['#ffc107', '#28a745', '#dc3545', '#000000ff'],
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
                        text: 'Og‘irlik turi bo‘yicha',
                        font: {
                            size: 16
                        }
                    }
                },
                onClick: (_, el) => {
                    activeType = el[0] ? ['ijtimoiy xavfi katta bo‘lmagan', 'uncha og‘ir bo‘lmagan', 'og‘ir', 'o‘ta og‘ir'][el[0].index] : '';
                    updateAll();
                }
            }
        });

        // Trend diagrammasi
        const currentYear = new Date().getFullYear();
        const yearData = {};
        crimes.forEach(c => {
            const y = new Date(c.sodir_vaqti).getFullYear();
            yearData[y] = (yearData[y] || 0) + 1;
        });

        const years = Object.keys(yearData).sort();
        new Chart(document.getElementById('chartTrend'), {
            type: 'line',
            data: {
                labels: years,
                datasets: [{
                    label: 'Jinoyatlar soni',
                    data: years.map(y => yearData[y]),
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 8,
                    pointHoverRadius: 10
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Yillar bo‘yicha trend (Bos → Filtr)',
                        font: {
                            size: 16
                        }
                    }
                },
                onClick: (_, el) => {
                    activeYear = el[0] ? parseInt(years[el[0].index]) : '';
                    activeMonth = '';
                    updateAll();
                }
            }
        });

        function applyFilters() {
            activeModda = document.getElementById('filterModda').value.trim();
            activeType = document.getElementById('filterType').value;
            activeYear = document.getElementById('filterYear').value;
            activeMonth = '';

            // Vizual belgi qo‘yish
            document.getElementById('filterModda').style.borderColor = activeModda ? '#4361ee' : '';
            document.getElementById('filterType').style.borderColor = activeType ? '#4361ee' : '';
            document.getElementById('filterYear').style.borderColor = activeYear ? '#4361ee' : '';

            updateAll();
        }

        // Barcha filtr va xaritani yangilash
        function updateAll() {
            updateMap();

            // Agar filter select lar ochiq bo‘lsa (kelajakda qo‘shsangiz)
            const typeSelect = document.getElementById('filterType');
            const yearSelect = document.getElementById('filterYear');
            if (typeSelect) typeSelect.style.borderColor = activeType ? '#4361ee' : '';
            if (yearSelect) yearSelect.style.borderColor = activeYear ? '#4361ee' : '';
        }

        function resetFilters() {
            activeModda = activeType = activeYear = activeMonth = '';
            document.getElementById('filterModda').value = '';
            document.getElementById('filterType').value = '';
            document.getElementById('filterYear').value = '';

            // Borderlarni tozalash
            document.getElementById('filterModda').style.borderColor = '';
            document.getElementById('filterType').style.borderColor = '';
            document.getElementById('filterYear').style.borderColor = '';

            updateAll();
        }


        // Enter tugmasi bilan ham filtrlash
        document.getElementById('filterModda').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') applyFilters();
        });

        function handleClick(id) {
            location.href = `../jinoyatlar/add_crime.php?id=${id}&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>`;
        }

        function toggleTheme() {
            const theme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', theme);
        }
    </script>

</body>

</html>