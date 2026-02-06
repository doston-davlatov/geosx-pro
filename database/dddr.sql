-- ----------------------------
-- 1. DATABASE YARATISH
-- ----------------------------
CREATE DATABASE IF NOT EXISTS uzb_gis CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE uzb_gis;

-- 1. USERS TABLE
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(30) NOT NULL,
    last_name VARCHAR(30) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    username VARCHAR(30) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user','operator','admin','super_admin') NOT NULL DEFAULT 'user',
    mahalla_id INT NULL,  -- operator faqat o‘z MFY sini biladi
    profile_picture VARCHAR(255) DEFAULT 'default.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. ACTIVE SESSIONS TABLE
CREATE TABLE active_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    device_name VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. FAILED LOGINS TABLE
CREATE TABLE failed_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL ,
    attempts INT NOT NULL DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address)
);
-- ----------------------------
-- 3. VILOYATLAR JADVALI
-- ----------------------------
CREATE TABLE viloyatlar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nomi VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

INSERT INTO viloyatlar (nomi) VALUES
('Qoraqalpog‘iston Respublikasi'),
('Andijon viloyati'),
('Buxoro viloyati'),
('Farg‘ona viloyati'),
('Jizzax viloyati'),
('Namangan viloyati'),
('Navoiy viloyati'),
('Qashqadaryo viloyati'),
('Samarqand viloyati'),
('Sirdaryo viloyati'),
('Surxondaryo viloyati'),
('Toshkent viloyati'),
('Toshkent shahri'),
('Xorazm viloyati');

-- ----------------------------
-- 4. TUMANLAR JADVALI
-- ----------------------------
CREATE TABLE tumanlar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  viloyat_id INT NOT NULL,
  nomi VARCHAR(100) NOT NULL,
  FOREIGN KEY (viloyat_id) REFERENCES viloyatlar(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO tumanlar (viloyat_id, nomi) VALUES
(1, 'Amudaryo tumani'),
(1, 'Beruniy tumani'),
(1, 'Chimboy tumani'),
(1, 'Ellikqal’a tumani'),
(1, 'Kegeyli tumani'),
(1, 'Mo‘ynoq tumani'),
(1, 'Nukus tumani'),
(1, 'Nukus shahri'),
(1, 'Qanliko‘l tumani'),
(1, 'Qo‘ng‘irot tumani'),
(1, 'Qorauzak tumani'),
(1, 'Shumanay tumani'),
(1, 'Taxtako‘pir tumani'),
(1, 'To‘rtko‘l tumani'),
(1, 'Xo‘jayli tumani');

INSERT INTO tumanlar (viloyat_id, nomi) VALUES
(2, 'Andijon shahri'),
(2, 'Andijon tumani'),
(2, 'Asaka tumani'),
(2, 'Baliqchi tumani'),
(2, 'Bo‘z tumani'),
(2, 'Buloqboshi tumani'),
(2, 'Izboskan tumani'),
(2, 'Jalaquduq tumani'),
(2, 'Marhamat tumani'),
(2, 'Oltinko‘l tumani'),
(2, 'Paxtaobod tumani'),
(2, 'Qo‘rg‘ontepa tumani'),
(2, 'Shahrixon tumani'),
(2, 'Ulug‘nor tumani'),
(2, 'Xo‘jaobod tumani');

INSERT INTO tumanlar (viloyat_id, nomi) VALUES
(3, 'Buxoro shahri'),
(3, 'Buxoro tumani'),
(3, 'G‘ijduvon tumani'),
(3, 'Jondor tumani'),
(3, 'Kogon tumani'),
(3, 'Kogon shahri'),
(3, 'Olot tumani'),
(3, 'Peshku tumani'),
(3, 'Qorako‘l tumani'),
(3, 'Qorovulbozor tumani'),
(3, 'Romitan tumani'),
(3, 'Shofirkon tumani'),
(3, 'Vobkent tumani');

INSERT INTO tumanlar (viloyat_id, nomi) VALUES
(4, 'Farg‘ona shahri'),
(4, 'Bag‘dod tumani'),
(4, 'Beshariq tumani'),
(4, 'Buvayda tumani'),
(4, 'Dang‘ara tumani'),
(4, 'Farg‘ona tumani'),
(4, 'Furqat tumani'),
(4, 'Qo‘qon shahri'),
(4, 'Quva tumani'),
(4, 'Quvasoy shahri'),
(4, 'Oltiariq tumani'),
(4, 'Rishton tumani'),
(4, 'So‘x tumani'),
(4, 'Toshloq tumani'),
(4, 'Uchko‘prik tumani'),
(4, 'Yozyovon tumani');

INSERT INTO tumanlar (viloyat_id, nomi) VALUES
(5, 'Arnasoy tumani'),
(5, 'Baxmal tumani'),
(5, 'Do‘stlik tumani'),
(5, 'Forish tumani'),
(5, 'G‘allaorol tumani'),
(5, 'Jizzax shahri'),
(5, 'Mirzacho‘l tumani'),
(5, 'Paxtakor tumani'),
(5, 'Yangiobod tumani'),
(5, 'Zomin tumani'),
(5, 'Zafarobod tumani'),
(5, 'Zarbdor tumani');

INSERT INTO tumanlar (viloyat_id, nomi) VALUES
(6, 'Chortoq tumani'),
(6, 'Chust tumani'),
(6, 'Kosonsoy tumani'),
(6, 'Mingbuloq tumani'),
(6, 'Namangan shahri'),
(6, 'Namangan tumani'),
(6, 'Norin tumani'),
(6, 'Pop tumani'),
(6, 'To‘raqo‘rg‘on tumani'),
(6, 'Uchqo‘rg‘on tumani'),
(6, 'Yangiqo‘rg‘on tumani');

INSERT INTO tumanlar (viloyat_id, nomi) VALUES
(7, 'Karmana tumani'),
(7, 'Konimex tumani'),
(7, 'Navbahor tumani'),
(7, 'Navoiy shahri'),
(7, 'Nurota tumani'),
(7, 'Qiziltepa tumani'),
(7, 'Tomdi tumani'),
(7, 'Uchquduq tumani'),
(7, 'Xatirchi tumani');

INSERT INTO tumanlar (viloyat_id, nomi) VALUES
(8, 'Chiroqchi tumani'),
(8, 'Dehqonobod tumani'),
(8, 'G‘uzor tumani'),
(8, 'Kasbi tumani'),
(8, 'Kitob tumani'),
(8, 'Koson tumani'),
(8, 'Mirishkor tumani'),
(8, 'Muborak tumani'),
(8, 'Nishon tumani'),
(8, 'Qamashi tumani'),
(8, 'Qarshi shahri'),
(8, 'Qarshi tumani'),
(8, 'Shahrisabz tumani'),
(8, 'Yakkabog‘ tumani');

INSERT INTO tumanlar (viloyat_id, nomi) VALUES
(9, 'Bulung‘ur tumani'),
(9, 'Ishtixon tumani'),
(9, 'Jomboy tumani'),
(9, 'Kattaqo‘rg‘on tumani'),
(9, 'Kattaqo‘rg‘on shahri'),
(9, 'Narpay tumani'),
(9, 'Nurobod tumani'),
(9, 'Oqdaryo tumani'),
(9, 'Pastdarg‘om tumani'),
(9, 'Payariq tumani'),
(9, 'Samarqand shahri'),
(9, 'Samarqand tumani'),
(9, 'Toyloq tumani'),
(9, 'Urgut tumani');

INSERT INTO tumanlar (viloyat_id, nomi) VALUES
(10, 'Boyovut tumani'),
(10, 'Guliston tumani'),
(10, 'Guliston shahri'),
(10, 'Mirzaobod tumani'),
(10, 'Oqoltin tumani'),
(10, 'Sardoba tumani'),
(10, 'Sayxunobod tumani'),
(10, 'Shirin shahri'),
(10, 'Sirdaryo tumani'),
(10, 'Xovos tumani'),
(10, 'Yangiyer shahri');

INSERT INTO tumanlar (viloyat_id, nomi) VALUES
(11, 'Angor tumani'),
(11, 'Boysun tumani'),
(11, 'Denov tumani'),
(11, 'Jarqo‘rg‘on tumani'),
(11, 'Qiziriq tumani'),
(11, 'Qumqo‘rg‘on tumani'),
(11, 'Muzrabot tumani'),
(11, 'Oltinsoy tumani'),
(11, 'Sariosiyo tumani'),
(11, 'Sherobod tumani'),
(11, 'Sho‘rchi tumani'),
(11, 'Termiz shahri'),
(11, 'Termiz tumani'),
(11, 'Uzun tumani');

INSERT INTO tumanlar (viloyat_id, nomi) VALUES
(12, 'Angren shahri'),
(12, 'Bekobod shahri'),
(12, 'Bekobod tumani'),
(12, 'Bo‘ka tumani'),
(12, 'Chinoz tumani'),
(12, 'Ohangaron tumani'),
(12, 'Parkent tumani'),
(12, 'Piskent tumani'),
(12, 'Qibray tumani'),
(12, 'Quyi Chirchiq tumani'),
(12, 'Oqqo‘rg‘on tumani'),
(12, 'O‘rta Chirchiq tumani'),
(12, 'Yangiyo‘l tumani'),
(12, 'Yuqori Chirchiq tumani'),
(12, 'Zangiota tumani'),
(12, 'Nurafshon shahri');

INSERT INTO tumanlar (viloyat_id, nomi) VALUES
(13, 'Bektemir tumani'),
(13, 'Chilonzor tumani'),
(13, 'Mirobod tumani'),
(13, 'Mirzo Ulug‘bek tumani'),
(13, 'Olmazor tumani'),
(13, 'Sergeli tumani'),
(13, 'Shayxontohur tumani'),
(13, 'Uchtepa tumani'),
(13, 'Yakkasaroy tumani'),
(13, 'Yashnobod tumani'),
(13, 'Yunusobod tumani');

INSERT INTO tumanlar (viloyat_id, nomi) VALUES
(14, 'Bog‘ot tumani'),
(14, 'Gurlan tumani'),
(14, 'Hazorasp tumani'),
(14, 'Khiva shahri'),
(14, 'Qo‘shko‘pir tumani'),
(14, 'Shovot tumani'),
(14, 'Urganch shahri'),
(14, 'Urganch tumani'),
(14, 'Xiva tumani'),
(14, 'Yangiariq tumani'),
(14, 'Yangibozor tumani');

-- ===============================
-- 5. MAHALLELAR (MFY) JADVALI
-- ===============================

CREATE TABLE mahallelar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  viloyat_id INT NOT NULL,
  tuman_id INT NOT NULL,
  nomi VARCHAR(150) NOT NULL,
  polygon JSON,
  operator_id INT NULL,
  markaz_lat DOUBLE,
  markaz_lng DOUBLE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (viloyat_id) REFERENCES viloyatlar(id) ON DELETE CASCADE,
  FOREIGN KEY (tuman_id) REFERENCES tumanlar(id) ON DELETE CASCADE,
  FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------
-- 6. CRIMES (JINOYATLAR) JADVALI
-- ----------------------------
CREATE TABLE crimes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  jk_modda VARCHAR(100),
  qismi VARCHAR(50),
  bandi VARCHAR(50),
  ogrilik_turi VARCHAR(150),
  sodir_vaqti DATETIME,
  viloyat_id INT,
  tuman_id INT,
  mahalla_id INT,
  jinoyat_matni TEXT,
  lat DOUBLE,
  lng DOUBLE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (viloyat_id) REFERENCES viloyatlar(id) ON DELETE SET NULL,
  FOREIGN KEY (tuman_id) REFERENCES tumanlar(id) ON DELETE SET NULL,
  FOREIGN KEY (mahalla_id) REFERENCES mahallelar(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------
-- 7. Nizokas oilalar
-- ------------------------------
CREATE TABLE nizokash_oilalar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fish VARCHAR(150) NOT NULL COMMENT 'Oilaviy boshliqning F.I.Sh.',
    azolar_soni TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Oiladagi odamlar soni',
    sabab TEXT COMMENT 'Nizokashga tushish sababi (ijtimoiy, iqtisodiy va h.k.)',
    sana DATE NOT NULL COMMENT 'Ro‘yxatga olingan sana',
    
    -- Joylashuv
    viloyat_id INT NULL,
    tuman_id INT NULL,
    mahalla_id INT NULL,
    
    manzil TEXT COMMENT 'To‘liq manzil matni (agar kerak bo‘lsa)',
    lat DECIMAL(10, 8) NULL COMMENT 'Latitude (xaritada ko‘rsatish uchun)',
    lng DECIMAL(11, 8) NULL COMMENT 'Longitude',
    
    -- Qo‘shimcha
    operator_id INT NULL COMMENT 'Ma‘lumotni kiritgan inspektor',
    status ENUM('faol', 'chiqarilgan', 'vaqtincha') DEFAULT 'faol',
    izoh TEXT COMMENT 'Qo‘shimcha izohlar',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Tashqi kalitlar
    FOREIGN KEY (viloyat_id) REFERENCES viloyatlar(id) ON DELETE SET NULL,
    FOREIGN KEY (tuman_id) REFERENCES tumanlar(id) ON DELETE SET NULL,
    FOREIGN KEY (mahalla_id) REFERENCES mahallelar(id) ON DELETE SET NULL,
    FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Tez qidiruv uchun indekslar
    INDEX idx_viloyat (viloyat_id),
    INDEX idx_tuman (tuman_id),
    INDEX idx_mahalla (mahalla_id),
    INDEX idx_sana (sana),
    INDEX idx_status (status),
    FULLTEXT INDEX ft_fish_sabab (fish, sabab)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------
-- 8. Order olganlar
-- --------------------------------------
CREATE TABLE order_olganlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fish VARCHAR(150) NOT NULL COMMENT 'Shaxsning F.I.Sh.',
    order_nomi VARCHAR(200) NOT NULL COMMENT 'Order nomi (masalan: "O‘zbekiston Qahramoni", "Shuhrat" medali)',
    order_darajasi ENUM('') DEFAULT '',
    
    berilgan_sana DATE NOT NULL COMMENT 'Order topshirilgan sana',
    berilgan_joy VARCHAR(200) NULL COMMENT 'Topshirilgan joy (Toshkent, Samarqand va h.k.)',
    
    -- Joylashuv
    viloyat_id INT NULL,
    tuman_id INT NULL,
    mahalla_id INT NULL,
    
    lat DECIMAL(10, 8) NULL,
    lng DECIMAL(11, 8) NULL,
    
    -- Qo‘shimcha
    operator_id INT NULL COMMENT 'Ma‘lumotni kiritgan foydalanuvchi',
    rasmiy_hujjat VARCHAR(255) NULL COMMENT 'Farmon raqami yoki hujjat nomi',
    izoh TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Tashqi kalitlar
    FOREIGN KEY (viloyat_id) REFERENCES viloyatlar(id) ON DELETE SET NULL,
    FOREIGN KEY (tuman_id) REFERENCES tumanlar(id) ON DELETE SET NULL,
    FOREIGN KEY (mahalla_id) REFERENCES mahallelar(id) ON DELETE SET NULL,
    FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indekslar
    INDEX idx_viloyat (viloyat_id),
    INDEX idx_sana (berilgan_sana),
    INDEX idx_order (order_nomi),
    FULLTEXT INDEX ft_fish_order (fish, order_nomi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------
-- Tez statistika uchun
CREATE OR REPLACE VIEW v_nizokash_stats AS
SELECT 
    COUNT(*) as jami_oila,
    SUM(azolar_soni) as jami_odam,
    viloyat_id,
    v.nomi as viloyat_nomi
FROM nizokash_oilalar n
LEFT JOIN viloyatlar v ON n.viloyat_id = v.id
WHERE n.status = 'faol'
GROUP BY viloyat_id;

-- Orderlar bo‘yicha yillar statistikasi
CREATE OR REPLACE VIEW v_orders_by_year AS
SELECT 
    YEAR(berilgan_sana) as yil,
    COUNT(*) as soni
FROM order_olganlar
WHERE berilgan_sana IS NOT NULL
GROUP BY YEAR(berilgan_sana)
ORDER BY yil DESC;