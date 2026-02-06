<?php
session_start();
// ======= Session va xavfsizlik ======
if (empty($_SESSION['loggedin']) || empty($_SESSION['username'])) {
    header("Location: ./login/");
    exit;
}
// Regenerate session ID once
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}
// ======= Database ulanishi =======
require_once __DIR__ . '/connection/mysqli.php';
// ======= Foydalanuvchi ma'lumotlarini olish =======
$username = $_SESSION['username'];
$stmt = $mysqli->prepare("SELECT id, username, role FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user_result = $stmt->get_result();
if ($user_result->num_rows === 0) {
    session_destroy();
    header("Location: ./login/");
    exit;
}
$user = $user_result->fetch_assoc();
$_SESSION['user'] = $user;
// ======= Viloyatlar ma'lumotlarini olish =======
$viloyatlar_stmt = $mysqli->prepare("SELECT id, nomi FROM viloyatlar ORDER BY nomi ASC");
$viloyatlar_stmt->execute();
$viloyatlar_result = $viloyatlar_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Huquqiy Statistika GeoTizimi</title>
    <!-- Bootstrap, FontAwesome va Leaflet -->
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
        /* Sidebar */
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
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: #00e5ff;
            border-radius: 4px;
        }
        .sidebar h2 {
            text-align: center;
            color: #00e5ff;
            font-size: 1.4rem;
            margin-bottom: 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
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
        .nav-link:hover {
            background: rgba(0, 229, 255, 0.1);
            color: #fff;
            border-left-color: #00e5ff;
            transform: translateX(5px);
        }
        .nav-link.active {
            background: rgba(0, 229, 255, 0.15);
            color: #fff;
            border-left-color: #00e5ff;
        }
        .nav-link i {
            width: 28px;
            color: #00e5ff;
            font-size: 1.1rem;
            text-align: center;
        }
        .nav-link span {
            margin-left: 12px;
        }
        /* Dropdown tugma */
        .dropdown-toggle {
            width: 100%;
            justify-content: space-between;
            background: none;
            border: none;
            cursor: pointer;
        }
        .arrow {
            transition: transform 0.3s ease;
        }
        .has-submenu.open .arrow {
            transform: rotate(180deg);
        }
        /* Submenu */
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.4s ease, opacity 0.3s ease;
            opacity: 0;
            background: rgba(0, 0, 0, 0.3);
            margin-left: 20px;
            border-left: 2px solid #00e5ff40;
        }
        .has-submenu.open .submenu {
            max-height: 500px;
            padding: 8px 0;
            opacity: 1;
        }
        .submenu .nav-link {
            padding-left: 40px;
        }
        .submenu .nav-link:hover {
            padding-left: 45px;
        }
        /* Main content */
        main {
            margin-left: 280px;
            padding: 40px;
            text-align: center;
        }
        main h1 {
            font-weight: 700;
            color: #00e5ff;
        }
        #map {
            width: 80%;
            height: 500px;
            margin: 40px auto;
            border-radius: 15px;
            border: 2px solid white;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.8);
        }
        /* Canvas fon */
        #network-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -1;
        }
        /* SVG xarita */
        #uz-map {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40vw;
            stroke: #00e5ff;
            stroke-width: 2;
            fill: none;
            z-index: 0;
            animation: drawMap 5s ease-in-out forwards;
        }
        @keyframes drawMap {
            from { stroke-dasharray: 1000; stroke-dashoffset: 1000; }
            to { stroke-dasharray: 1000; stroke-dashoffset: 0; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2><i class="fa-solid fa-shield-halved"></i> GeoTizim</h2>
        <ul class="nav-list">
            <!-- Asosiy menyular -->
            <li>
                <a href="./" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Asosiy sahifa</span>
                </a>
            </li>
            <li>
                <a href="./jinoyatlar/" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/jinoyatlar/') !== false ? 'active' : '' ?>">
                    <i class="fa-solid fa-handcuffs"></i>
                    <span>Jinoyatlar</span>
                </a>
            </li>
            <li>
                <a href="./nizokash/" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/nizokash/') !== false ? 'active' : '' ?>">
                    <i class="fa-solid fa-users-slash"></i>
                    <span>Nizoli oilalar</span>
                </a>
            </li>
            <li>
                <a href="./order/" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/order/') !== false ? 'active' : '' ?>">
                    <i class="fa-solid fa-medal"></i>
                    <span>Order</span>
                </a>
            </li>
            <li>
                <a href="./dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-database"></i>
                    <span>Ma'lumotlar</span>
                </a>
            </li>

            <!-- Admin panel (admin yoki operator uchun) -->
            <?php if ($user['role'] === 'admin' || $user['role'] === 'operator'): ?>
                <li class="has-submenu">
                    <button type="button" class="nav-link dropdown-toggle">
                        <i class="fa-solid fa-user-shield"></i>
                        <span>Admin panel</span>
                        <i class="fa-solid fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu">
                        <a href="./admin/creat_mfy.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'creat_mfy.php' ? 'active' : '' ?>">
                            <i class="fa-solid fa-map-marked-alt"></i>
                            <span>Yangi MFY qo'shish</span>
                        </a>
                        <a href="./admin/edit_mfy.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'edit_mfy.php' ? 'active' : '' ?>">
                            <i class="fa-solid fa-edit"></i>
                            <span>MFY tahrirlash</span>
                        </a>
                        <!-- Boshqa admin sahifalarini shu yerga qo'shishingiz mumkin -->
                    </div>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <canvas id="network-bg"></canvas>
    <svg id="uz-map" viewBox="0 0 800 400">
        <path d="M100,250 L180,200 L300,220 L380,160 L460,180 L520,140 L600,160 L680,200 L720,260 L680,300 L560,320 L440,300 L300,320 L200,280 Z" />
    </svg>

    <!-- Main content -->
    <main>
        <h1>O‘zbekiston Respublikasi IIV Huquqiy Statistika GeoTizimi</h1>
        <p>Mahallalar bo‘yicha jinoyat holatlarini interaktiv xaritada kuzating</p>
        <div id="map"></div>
    </main>

    <!-- JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Xarita
        const map = L.map('map').setView([41.0, 64.0], 6);
        const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        const googleRoad = L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { attribution: '© Google Roadmap' });
        const googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { attribution: '© Google Hybrid' });

        L.control.layers({
            "OpenStreetMap": osm,
            "Google Roadmap": googleRoad,
            "Google Hybrid": googleHybrid
        }, null, { position: 'topright' }).addTo(map);

        // Submenu ochish/yopish
        document.querySelectorAll('.has-submenu .dropdown-toggle').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.has-submenu').classList.toggle('open');
            });
        });

        // Hozirgi sahifani active qilish
        const currentPath = window.location.pathname;
        document.querySelectorAll('.nav-link').forEach(link => {
            if (link.getAttribute('href') === 
                (link.getAttribute('href') + '/').includes(currentPath)) {
                link.classList.add('active');
            }
        });

        // Network animatsiya
        const canvas = document.getElementById('network-bg');
        const ctx = canvas.getContext('2d');
        let w, h, dots = [];

        function init() {
            w = canvas.width = window.innerWidth;
            h = canvas.height = window.innerHeight;
            dots = Array.from({ length: 80 }, () => ({
                x: Math.random() * w,
                y: Math.random() * h,
                vx: (Math.random() - 0.5) * 0.7,
                vy: (Math.random() - 0.5) * 0.7
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
                ctx.arc(d.x, d.y, 1.6, 0, Math.PI * 2);
                ctx.fill();
            });

            for (let i = 0; i < dots.length; i++) {
                for (let j = i + 1; j < dots.length; j++) {
                    const dx = dots[i].x - dots[j].x;
                    const dy = dots[i].y - dots[j].y;
                    const dist = Math.hypot(dx, dy);
                    if (dist < 120) {
                        ctx.strokeStyle = `rgba(0,229,255,${1 - dist/120})`;
                        ctx.lineWidth = 0.4;
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