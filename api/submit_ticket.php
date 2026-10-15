<?php

/**
 * ============================================================
 *  HelpMe — Destek Talebi Oluşturma API Endpoint'i
 * ============================================================
 *
 *  POST  /api/submit_ticket.php
 *
 *  JSON Body:
 *  {
 *      "fullname":     "Ad Soyad",
 *      "email":        "kullanici@ornek.com",
 *      "service_type": "Hata Bildirimi",
 *      "message":      "Detaylı açıklama (min 10 karakter)"
 *  }
 *
 *  Başarılı Yanıt (201):
 *  {
 *      "status":        "success",
 *      "message":       "Talebiniz başarıyla oluşturuldu.",
 *      "tracking_code": "HM-A3F7K"
 *  }
 *
 *  Hata Yanıtı (400 | 405 | 500):
 *  {
 *      "status":  "error",
 *      "message": "Hata detayı..."
 *  }
 *
 *  @package  HelpMe
 *  @file     api/submit_ticket.php
 *  @requires PHP 8.0+
 * ============================================================
 */

declare(strict_types=1);

/* ──────────────────────────────────────────────
 *  1) Ortak Ayarlar
 * ────────────────────────────────────────────── */

// JSON yanıt başlığı
header('Content-Type: application/json; charset=utf-8');

// CORS — frontend aynı sunucudaysa bile tarayıcı preflight
// istekleri için gereklidir.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight (OPTIONS) isteğini erken yanıtla
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ──────────────────────────────────────────────
 *  2) HTTP Metod Kontrolü
 * ────────────────────────────────────────────── */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Yalnızca POST istekleri kabul edilmektedir.', 405);
}

/* ──────────────────────────────────────────────
 *  3) Veritabanı Bağlantısı
 * ────────────────────────────────────────────── */

require_once __DIR__ . '/../includes/db.php';

/* ──────────────────────────────────────────────
 *  4) Gelen JSON Verisini Oku ve Parse Et
 * ────────────────────────────────────────────── */

$rawBody = file_get_contents('php://input');

if ($rawBody === false || $rawBody === '') {
    sendError('İstek gövdesi boş. JSON verisi gönderin.', 400);
}

$data = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendError('Geçersiz JSON formatı: ' . json_last_error_msg(), 400);
}

/* ──────────────────────────────────────────────
 *  5) Doğrulama (Validation) ve Sanitizasyon
 * ────────────────────────────────────────────── */

$errors = [];

// — fullname —
$fullname = trim(sanitize($data['fullname'] ?? ''));
if ($fullname === '') {
    $errors[] = 'Ad Soyad alanı boş bırakılamaz.';
}

// — email —
$email = trim($data['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Geçerli bir e-posta adresi giriniz.';
}
$email = filter_var($email, FILTER_SANITIZE_EMAIL);

// — service_type —
$serviceType = trim(sanitize($data['service_type'] ?? ''));
if ($serviceType === '') {
    $errors[] = 'Talep türü seçilmelidir.';
}

// — message —
$message = trim(sanitize($data['message'] ?? ''));
if ($message === '') {
    $errors[] = 'Mesaj alanı boş bırakılamaz.';
} elseif (mb_strlen($message, 'UTF-8') < 10) {
    $errors[] = 'Mesaj en az 10 karakter olmalıdır.';
}

// Hatalar varsa toplu döndür
if (!empty($errors)) {
    sendError(implode(' ', $errors), 400);
}

/* ──────────────────────────────────────────────
 *  6) Takip Kodu Üret
 * ────────────────────────────────────────────── */

$trackingCode = generateTrackingCode();

/* ──────────────────────────────────────────────
 *  7) Veritabanına Kaydet (Prepared Statement)
 * ────────────────────────────────────────────── */

try {
    $pdo = Database::connect();

    $sql = <<<'SQL'
        INSERT INTO tickets
            (tracking_code, fullname, email, service_type, message)
        VALUES
            (:tracking_code, :fullname, :email, :service_type, :message)
    SQL;

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':tracking_code' => $trackingCode,
        ':fullname'      => $fullname,
        ':email'         => $email,
        ':service_type'  => $serviceType,
        ':message'       => $message,
    ]);

    // ── Başarılı yanıt (201 Created) ──
    http_response_code(201);
    echo json_encode([
        'status'        => 'success',
        'message'       => 'Talebiniz başarıyla oluşturuldu.',
        'tracking_code' => $trackingCode,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

} catch (PDOException $e) {
    /*
     * Benzersiz takip kodu çakışması durumunda bir kez daha dene.
     * Çok düşük bir olasılık ama savunmacı yaklaşım.
     */
    if ($e->getCode() === '23000') {
        retryInsert($fullname, $email, $serviceType, $message);
        return;
    }

    error_log('[HelpMe API] Veritabanı hatası: ' . $e->getMessage());
    sendError('Veritabanı kayıt hatası: ' . $e->getMessage(), 500);

} catch (RuntimeException $e) {
    // Database::connect() içinden gelen bağlantı hatası
    sendError($e->getMessage(), 500);
} catch (Throwable $e) {
    error_log('[HelpMe API] Beklenmeyen hata: ' . $e->getMessage());
    sendError('Sistem hatası: ' . $e->getMessage(), 500);
}

/* ══════════════════════════════════════════════
 *  Yardımcı Fonksiyonlar (Helper Functions)
 * ══════════════════════════════════════════════ */

/**
 * XSS koruması için gelen metni sanitize eder.
 *
 * @param  string $value  Ham metin
 * @return string         Temizlenmiş metin
 */
function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Benzersiz takip kodu üretir.
 * Format: HM-XXXXX (5 haneli, büyük harf + rakam)
 *
 * @return string  Örn: "HM-A3F7K"
 */
function generateTrackingCode(): string
{
    $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // karıştırılabilecek
    $length = 5;                                   // 0/O/1/I hariç
    $code   = '';

    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return 'HM-' . $code;
}

/**
 * JSON hata yanıtı gönderir ve scripti sonlandırır.
 *
 * @param  string $message    Kullanıcıya gösterilecek mesaj
 * @param  int    $httpCode   HTTP durum kodu (400, 405, 500 vb.)
 * @return void
 */
function sendError(string $message, int $httpCode = 400): void
{
    http_response_code($httpCode);

    echo json_encode([
        'status'  => 'error',
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/**
 * Takip kodu çakışması (UNIQUE constraint) durumunda
 * yeni bir kodla tekrar kayıt dener.
 *
 * @param  string $fullname     Sanitize edilmiş ad soyad
 * @param  string $email        Sanitize edilmiş e-posta
 * @param  string $serviceType  Sanitize edilmiş talep türü
 * @param  string $message      Sanitize edilmiş mesaj
 * @return void
 */
function retryInsert(
    string $fullname,
    string $email,
    string $serviceType,
    string $message
): void {
    try {
        $pdo          = Database::connect();
        $trackingCode = generateTrackingCode();

        $sql = <<<'SQL'
            INSERT INTO tickets
                (tracking_code, fullname, email, service_type, message)
            VALUES
                (:tracking_code, :fullname, :email, :service_type, :message)
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tracking_code' => $trackingCode,
            ':fullname'      => $fullname,
            ':email'         => $email,
            ':service_type'  => $serviceType,
            ':message'       => $message,
        ]);

        http_response_code(201);
        echo json_encode([
            'status'        => 'success',
            'message'       => 'Talebiniz başarıyla oluşturuldu.',
            'tracking_code' => $trackingCode,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    } catch (\Throwable $e) {
        error_log('[HelpMe API] Retry hatası: ' . $e->getMessage());
        sendError(
            'Talebiniz kaydedilirken bir hata oluştu. Lütfen tekrar deneyin.',
            500
        );
    }
}
