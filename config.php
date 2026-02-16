<?php
declare(strict_types=1);

/* ==========================
   共通設定
========================== */
date_default_timezone_set('Asia/Tokyo');

/* ==========================
   DB設定
========================== */
$config = [
    'db' => [
        'host'     => 'localhost',
        'dbname'   => 'ss911157_ana',
        'user'     => 'ss911157_sedo',
        'password' => 'sedorisedori',
        'charset'  => 'utf8mb4',
    ],

    /* ==========================
       アプリ設定
    ========================== */
    'app' => [
        'name' => 'ノリ打ち収支',
    ],
];

/* ==========================
   PDO生成用ヘルパ（任意）
========================== */
function db(): PDO
{
    global $config;

    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = $config['db'];

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['dbname'],
        $db['charset']
    );

    $pdo = new PDO(
        $dsn,
        $db['user'],
        $db['password'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    return $pdo;
}
