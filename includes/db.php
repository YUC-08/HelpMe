<?php

/**
 * ============================================================
 *  HelpMe — Veritabanı Bağlantı Katmanı
 * ============================================================
 *
 *  Singleton deseni ile tek bir PDO bağlantısı üretir.
 *  Türkçe karakter desteği için utf8mb4 kullanır.
 *  Hata detaylarını loglar ama kullanıcıya göstermez.
 *
 *  @package  HelpMe
 *  @file     includes/db.php
 *  @requires PHP 8.0+
 * ============================================================
 */

declare(strict_types=1);

/**
 * Veritabanı bağlantı bilgileri.
 *
 * Sunucu adresine göre (Localhost vs Canlı Sunucu) otomatik belirlenir.
 */
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = (
    strpos($httpHost, 'localhost') !== false ||
    strpos($httpHost, '127.0.0.1') !== false ||
    php_sapi_name() === 'cli'
);

if ($isLocal) {
    // ── Yerel Geliştirme (Localhost / XAMPP) ───────────────
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3306');
    define('DB_NAME', 'helpme_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // ── Canlı Sunucu (helpmenteksis.fun) ───────────────────
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', 'helpment_helpme');
    define('DB_USER', 'helpment_helpme');
    define('DB_PASS', 'Hk9mQ2pVz7Lt4Rw8X');
}
define('DB_CHARSET', 'utf8mb4');

final class Database
{
    // ── Singleton örneği (PHP 7.3 uyumlu) ───────────────────
    private static $instance = null;

    /**
     * Dışarıdan örneklemeyi engelle (Singleton).
     */
    private function __construct() {}
    private function __clone() {}

    /**
     * Tek PDO bağlantısını döndürür.
     */
    public static function connect(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            /** PDO seçenekleri */
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('[HelpMe DB] Bağlantı hatası: ' . $e->getMessage());
                throw new RuntimeException('Veritabanı bağlantı hatası: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }
}
