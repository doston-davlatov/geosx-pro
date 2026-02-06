<?php
// connection/mysqli.php

$config = require __DIR__ . '/../connection/db-con.php';

$mysqli = new mysqli(
    $config['host'],
    $config['user'],
    $config['pass'],
    $config['db']
);

if ($mysqli->connect_errno) {
    die("MySQL ulanmadi: " . $mysqli->connect_error);
}
