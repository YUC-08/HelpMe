<?php

/**
 * ============================================================
 *  HelpMe — Dinamik Yönetici Kimlik Doğrulama & 2FA Modülü
 * ============================================================
 *
 *  - Veritabanı tabanlı dinamik admin yönetimi (admins tablosu)
 *  - password_hash() ve password_verify() ile güvenli şifreleme
 *  - RFC 6238 Google Authenticator (TOTP) 2FA entegrasyonu (Harici kütüphanesiz)
 *  - Güvenli oturum (session fixation ve hijacking önleme)
 *
 *  @package HelpMe
 *  @file    includes/auth.php
 *  @requires PHP 8.0+
 * ============================================================
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Kullanıcı adına göre admin kaydını getirir.
 */
function getAdminByUsername(PDO $pdo, string $username): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    return $admin ?: null;
}

/**
 * ID'ye göre admin kaydını getirir.
 */
function getAdminById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    return $admin ?: null;
}

/**
 * Admin şifresini güvenle günceller.
 */
function updateAdminPassword(PDO $pdo, int $adminId, string $newPassword): bool
{
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE admins SET password_hash = :hash WHERE id = :id");
    return $stmt->execute([':hash' => $hash, ':id' => $adminId]);
}

/**
 * Admin 2FA durumunu ve secret anahtarını günceller.
 */
function updateAdmin2FA(PDO $pdo, int $adminId, bool $enabled, ?string $secret): bool
{
    $stmt = $pdo->prepare("
        UPDATE admins 
        SET two_factor_enabled = :enabled, two_factor_secret = :secret 
        WHERE id = :id
    ");
    return $stmt->execute([
        ':enabled' => $enabled ? 1 : 0,
        ':secret'  => $secret,
        ':id'      => $adminId,
    ]);
}

/* ============================================================
 *  RFC 6238 TOTP (Google Authenticator) Fonksiyonları
 * ============================================================ */

/**
 * Base32 kodlanmış rastgele 16 karakterlik gizli anahtar (Secret Key) üretir.
 */
function generateTotpSecret(int $length = 16): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}

/**
 * Base32 dizesini ikili (binary) veriye dönüştürür.
 */
function base32Decode(string $b32): string
{
    $b32 = strtoupper(trim($b32));
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    for ($i = 0; $i < strlen($b32); $i++) {
        $pos = strpos($chars, $b32[$i]);
        if ($pos === false) continue;
        $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    for ($i = 0; $i + 8 <= strlen($binary); $i += 8) {
        $bytes .= chr(bindec(substr($binary, $i, 8)));
    }
    return $bytes;
}

/**
 * 6 haneli TOTP kodunu doğrular (Saat kayması için +- 1 zaman dilimi toleransı).
 */
function verifyTotpCode(string $secret, string $code, int $discrepancy = 1): bool
{
    $code = trim($code);
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        return false;
    }
    $currentTimeSlice = (int) floor(time() / 30);
    $key = base32Decode($secret);
    if ($key === '') return false;

    for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
        $timeSlice = $currentTimeSlice + $i;
        $packedTime = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $packedTime, $key, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashPart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashPart)[1] & 0x7FFFFFFF;
        $calculatedCode = str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
        if (hash_equals($calculatedCode, $code)) {
            return true;
        }
    }
    return false;
}

/**
 * Google Authenticator QR kod görsel URL'ini üretir.
 */
function getTotpQrUrl(string $username, string $secret, string $issuer = 'HelpMe'): string
{
    $otpauth = sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s', urlencode($issuer), urlencode($username), $secret, urlencode($issuer));
    return 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($otpauth);
}

/**
 * Oturum çıkışı
 */
function adminLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}
