<?php

/**
 * ============================================================
 *  HelpMe — Güvenli Yönetici Paneli (Admin Panel)
 * ============================================================
 *
 *  - Veritabanı tabanlı dinamik admin yönetimi (admins tablosu)
 *  - Güvenli şifre değiştirme modülü
 *  - RFC 6238 Google Authenticator (2FA) entegrasyonu (Saf PHP)
 *  - Gelen taleplerin listelenmesi, filtrelenmesi ve sayaçları
 *  - Taleplerin chat geçmişi ve admin yanıt modülü (ticket_replies)
 *  - Talep durumu yönetimi (Beklemede, Yanıtlandı, Çözüldü)
 *  - Tam PRG (Post-Redirect-Get) mimarisi & Bootstrap 5
 *
 *  @package HelpMe
 *  @file    admin/admin.php
 *  @requires PHP 8.0+
 * ============================================================
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Veritabanı ve Güvenlik/Yetkilendirme katmanlarını dahil et
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = Database::connect();

$errorAlert   = '';
$successAlert = '';

// Session Flash mesajlarını oku ve temizle
if (!empty($_SESSION['flash_success'])) {
    $successAlert = (string)$_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $errorAlert = (string)$_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

/* ─────────────────────────────────────────────────────────────
 *  1) Çıkış İşlemi (Logout)
 * ───────────────────────────────────────────────────────────── */
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    adminLogout();
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

/* ─────────────────────────────────────────────────────────────
 *  2) Giriş Doğrulama (Login) & 2FA Kontrolü — PRG Mimarisi
 * ───────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $postAction = (string)$_POST['action'];

    // — Adım 1: Kullanıcı Adı & Şifre Doğrulama —
    if ($postAction === 'login') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $admin = getAdminByUsername($pdo, $username);

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);

            // 2FA Aktif mi?
            if (!empty($admin['two_factor_enabled']) && !empty($admin['two_factor_secret'])) {
                $_SESSION['2fa_pending_admin_id'] = (int)$admin['id'];
                header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?step=2fa');
                exit;
            } else {
                // 2FA yok, doğrudan oturum aç
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = (int)$admin['id'];
                $_SESSION['admin_user']      = $admin['username'];
                $_SESSION['admin_login_at']  = time();
                $_SESSION['flash_success']   = 'Giriş başarılı. Hoş geldiniz!';
                header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
                exit;
            }
        } else {
            $_SESSION['flash_error'] = 'Kullanıcı adı veya şifre hatalı.';
            header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
            exit;
        }
    }

    // — Adım 2: 2FA TOTP Kod Doğrulama —
    if ($postAction === 'verify_2fa_login') {
        $pendingId = (int)($_SESSION['2fa_pending_admin_id'] ?? 0);
        $totpCode  = trim((string)($_POST['totp_code'] ?? ''));

        if ($pendingId <= 0) {
            $_SESSION['flash_error'] = 'Oturum süresi doldu. Lütfen tekrar giriş yapın.';
            header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
            exit;
        }

        $admin = getAdminById($pdo, $pendingId);
        if ($admin && !empty($admin['two_factor_secret']) && verifyTotpCode($admin['two_factor_secret'], $totpCode)) {
            unset($_SESSION['2fa_pending_admin_id']);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id']        = (int)$admin['id'];
            $_SESSION['admin_user']      = $admin['username'];
            $_SESSION['admin_login_at']  = time();
            $_SESSION['flash_success']   = '2FA doğrulaması başarılı. Hoş geldiniz!';
            header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
            exit;
        } else {
            $_SESSION['flash_error'] = 'Geçersiz 2FA doğrulama kodu. Lütfen tekrar deneyiniz.';
            header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?step=2fa');
            exit;
        }
    }
}

// Oturum durumu
$isLoggedIn = !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id']);
$currentAdmin = null;

if ($isLoggedIn) {
    $currentAdmin = getAdminById($pdo, (int)$_SESSION['admin_id']);
    if (!$currentAdmin) {
        adminLogout();
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

/* ─────────────────────────────────────────────────────────────
 *  3) Oturum Açıksa: Yönetim & Ayarlar İşlemleri — PRG
 * ───────────────────────────────────────────────────────────── */
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string)($_POST['action'] ?? '');
    $cleanUri   = strtok($_SERVER["REQUEST_URI"], '?');

    // — Şifre Değiştirme —
    if ($postAction === 'change_password') {
        $currentPass = (string)($_POST['current_password'] ?? '');
        $newPass     = (string)($_POST['new_password'] ?? '');
        $confirmPass = (string)($_POST['confirm_password'] ?? '');

        if (!password_verify($currentPass, $currentAdmin['password_hash'])) {
            $_SESSION['flash_error'] = 'Mevcut şifrenizi yanlış girdiniz.';
        } elseif (strlen($newPass) < 6) {
            $_SESSION['flash_error'] = 'Yeni şifreniz en az 6 karakter olmalıdır.';
        } elseif ($newPass !== $confirmPass) {
            $_SESSION['flash_error'] = 'Yeni şifreler birbiriyle eşleşmiyor.';
        } else {
            updateAdminPassword($pdo, (int)$currentAdmin['id'], $newPass);
            $_SESSION['flash_success'] = 'Şifreniz başarıyla güncellendi!';
        }

        header('Location: ' . $cleanUri);
        exit;
    }

    // — 2FA'yı Aktif Etme —
    if ($postAction === 'enable_2fa') {
        $secretCode = trim((string)($_POST['secret_code'] ?? ''));
        $verifyCode = trim((string)($_POST['verify_code'] ?? ''));

        if ($secretCode === '' || !verifyTotpCode($secretCode, $verifyCode)) {
            $_SESSION['flash_error'] = 'Hatalı 2FA kodu girdiniz. Lütfen authenticator uygulamasındaki 6 haneli kodu kontrol edin.';
        } else {
            updateAdmin2FA($pdo, (int)$currentAdmin['id'], true, $secretCode);
            $_SESSION['flash_success'] = 'Google Authenticator (2FA) koruması başarıyla aktif edildi!';
        }

        header('Location: ' . $cleanUri);
        exit;
    }

    // — 2FA'yı Devre Dışı Bırakma —
    if ($postAction === 'disable_2fa') {
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (!password_verify($confirmPassword, $currentAdmin['password_hash'])) {
            $_SESSION['flash_error'] = '2FA kapatılamadı: Yönetici şifreniz hatalı.';
        } else {
            updateAdmin2FA($pdo, (int)$currentAdmin['id'], false, null);
            $_SESSION['flash_success'] = 'Google Authenticator (2FA) koruması kapatıldı.';
        }

        header('Location: ' . $cleanUri);
        exit;
    }

    // — Admin Cevabı Gönderme —
    if ($postAction === 'admin_reply') {
        $ticketId   = (int)($_POST['ticket_id'] ?? 0);
        $replyText  = trim((string)($_POST['message'] ?? ''));
        $newStatus  = (string)($_POST['status'] ?? 'answered');

        if ($ticketId <= 0 || $replyText === '') {
            $_SESSION['flash_error'] = 'Lütfen geçerli bir cevap mesajı yazınız.';
        } else {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO ticket_replies (ticket_id, sender_type, message, created_at)
                    VALUES (:ticket_id, "admin", :message, NOW())
                ');
                $stmt->execute([
                    ':ticket_id' => $ticketId,
                    ':message'   => $replyText,
                ]);

                $validStatuses = ['waiting', 'open', 'answered', 'closed'];
                if (!in_array($newStatus, $validStatuses, true)) {
                    $newStatus = 'answered';
                }

                $statusStmt = $pdo->prepare('UPDATE tickets SET status = :status WHERE id = :id');
                $statusStmt->execute([':status' => $newStatus, ':id' => $ticketId]);

                $_SESSION['flash_success'] = 'Admin cevabınız başarıyla iletildi ve talep durumu güncellendi.';
            } catch (Throwable $e) {
                error_log('[Admin Reply Error] ' . $e->getMessage());
                $_SESSION['flash_error'] = 'Cevap gönderilirken bir hata oluştu.';
            }
        }

        header('Location: ' . $cleanUri . ($ticketId > 0 ? '?ticket_id=' . $ticketId : ''));
        exit;
    }

    // — Talep Durumu Güncelleme —
    if ($postAction === 'update_status') {
        $ticketId  = (int)($_POST['ticket_id'] ?? 0);
        $newStatus = (string)($_POST['status'] ?? '');

        $validStatuses = ['waiting', 'open', 'answered', 'closed'];
        if ($ticketId > 0 && in_array($newStatus, $validStatuses, true)) {
            try {
                $stmt = $pdo->prepare('UPDATE tickets SET status = :status WHERE id = :id');
                $stmt->execute([':status' => $newStatus, ':id' => $ticketId]);
                $_SESSION['flash_success'] = 'Talep durumu başarıyla güncellendi.';
            } catch (Throwable $e) {
                error_log('[Admin Status Update Error] ' . $e->getMessage());
                $_SESSION['flash_error'] = 'Durum güncellenemedi.';
            }
        } else {
            $_SESSION['flash_error'] = 'Geçersiz talep veya durum değeri.';
        }

        header('Location: ' . $cleanUri . ($ticketId > 0 ? '?ticket_id=' . $ticketId : ''));
        exit;
    }
}

/* ─────────────────────────────────────────────────────────────
 *  4) Talep Listesi & İstatistiklerin Çekilmesi (Kusursuz Eşleşme)
 * ───────────────────────────────────────────────────────────── */
$activeTicket  = null;
$activeReplies = [];
$ticketsList   = [];
$stats         = ['total' => 0, 'waiting' => 0, 'answered' => 0, 'closed' => 0];

if ($isLoggedIn) {
    try {
        // İstatistikleri hesapla
        // Beklemede = 'waiting' / 'open' / NULL
        // Yanıtlananlar = 'answered' ve 'closed' (çözülen talepler de yanıtlanmıştır)
        // Çözülen / Kapalı = 'closed'
        $statsStmt = $pdo->query('
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ("waiting", "open") OR status IS NULL THEN 1 ELSE 0 END) AS waiting,
                SUM(CASE WHEN status IN ("answered", "closed") THEN 1 ELSE 0 END) AS answered,
                SUM(CASE WHEN status = "closed" THEN 1 ELSE 0 END) AS closed
            FROM tickets
        ');
        $statRow = $statsStmt->fetch(PDO::FETCH_ASSOC);
        if ($statRow) {
            $stats['total']    = (int)($statRow['total'] ?? 0);
            $stats['waiting']  = (int)($statRow['waiting'] ?? 0);
            $stats['answered'] = (int)($statRow['answered'] ?? 0);
            $stats['closed']   = (int)($statRow['closed'] ?? 0);
        }

        // Tekil Talep Detayı mı?
        $ticketIdParam = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
        if ($ticketIdParam > 0) {
            $stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $ticketIdParam]);
            $activeTicket = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($activeTicket) {
                $rStmt = $pdo->prepare('
                    SELECT * FROM ticket_replies 
                    WHERE ticket_id = :ticket_id 
                    ORDER BY created_at ASC, id ASC
                ');
                $rStmt->execute([':ticket_id' => $activeTicket['id']]);
                $activeReplies = $rStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        // Talep Listesi ve Filtreleme
        if (!$activeTicket) {
            $filterStatus = trim((string)($_GET['status'] ?? ''));
            $searchQuery  = trim((string)($_GET['q'] ?? ''));

            $sql = 'SELECT * FROM tickets WHERE 1=1';
            $params = [];

            if ($filterStatus === 'waiting' || $filterStatus === 'open') {
                $sql .= ' AND (status IN ("waiting", "open") OR status IS NULL)';
            } elseif ($filterStatus === 'answered') {
                $sql .= ' AND status IN ("answered", "closed")';
            } elseif ($filterStatus === 'closed') {
                $sql .= ' AND status = "closed"';
            }

            if ($searchQuery !== '') {
                $sql .= ' AND (tracking_code LIKE :q OR fullname LIKE :q OR email LIKE :q OR message LIKE :q)';
                $params[':q'] = '%' . $searchQuery . '%';
            }

            $sql .= ' ORDER BY created_at DESC LIMIT 100';

            $listStmt = $pdo->prepare($sql);
            $listStmt->execute($params);
            $ticketsList = $listStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        error_log('[Admin Data Error] ' . $e->getMessage());
        $errorAlert = 'Veriler yüklenirken bir sorun oluştu.';
    }
}

/**
 * Rozet HTML üretici fonksiyon (Beklemede / Yanıtlandı / Çözüldü)
 */
function renderStatusBadge(string $status): string {
    switch ($status) {
        case 'answered':
            return '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>Yanıtlandı</span>';
        case 'closed':
            return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill fw-semibold"><i class="bi bi-lock-fill me-1"></i>Çözüldü</span>';
        default:
            return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill fw-semibold"><i class="bi bi-clock-history me-1"></i>Beklemede</span>';
    }
}

// 2FA Hazırlığı (Aktif değilse modalda göstermek için yeni secret üret)
$pending2faSecret = '';
$pending2faQrUrl  = '';
if ($isLoggedIn && empty($currentAdmin['two_factor_enabled'])) {
    if (empty($_SESSION['temp_2fa_secret'])) {
        $_SESSION['temp_2fa_secret'] = generateTotpSecret(16);
    }
    $pending2faSecret = $_SESSION['temp_2fa_secret'];
    $pending2faQrUrl  = getTotpQrUrl($currentAdmin['username'], $pending2faSecret, 'HelpMe');
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HelpMe — Yönetici Paneli</title>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS & Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../public/favicon.svg">

    <style>
        :root {
            --ink: #182027;
            --muted: #64748b;
            --blue: #2563eb;
            --blue-dark: #1d4ed8;
            --bg-page: #f8fafc;
            --card-border: #e2e8f0;
        }

        body {
            background-color: var(--bg-page);
            color: var(--ink);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
        }

        .admin-nav {
            background: #ffffff;
            border-bottom: 1px solid var(--card-border);
            padding: 0.85rem 0;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            background: var(--blue);
            color: white;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .ui-card {
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 18px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }

        /* Chat Akışı */
        .chat-container {
            background: #f1f5f9;
            border-radius: 16px;
            padding: 1.5rem;
            max-height: 480px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .chat-row {
            display: flex;
            width: 100%;
        }
        .chat-row.is-user {
            justify-content: flex-start;
        }
        .chat-row.is-admin {
            justify-content: flex-end;
        }

        .chat-bubble-card {
            max-width: 82%;
            border-radius: 18px;
            padding: 1.1rem 1.35rem;
            position: relative;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }

        .chat-row.is-user .chat-bubble-card {
            background: #ffffff;
            color: var(--ink);
            border: 1px solid #cbd5e1;
            border-bottom-left-radius: 4px;
        }

        .chat-row.is-admin .chat-bubble-card {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            border: none;
            border-bottom-right-radius: 4px;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.28);
        }

        .chat-sender-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            font-size: 0.8rem;
            margin-bottom: 0.45rem;
            padding-bottom: 0.35rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        }
        .chat-row.is-admin .chat-sender-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .chat-body-text {
            font-size: 0.95rem;
            line-height: 1.6;
            word-break: break-word;
            margin: 0;
        }

        .chat-time-tag {
            font-size: 0.74rem;
            opacity: 0.75;
        }

        .reply-card {
            background: #f8fafc;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 1.25rem;
        }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
    
    <?php if (isset($_GET['step']) && $_GET['step'] === '2fa'): ?>
        <!-- ========================================================
             2FA DOĞRULAMA EKRANI (STEP 2)
             ======================================================== -->
        <div class="d-flex align-items-center justify-content-center min-vh-100 p-3">
            <div class="ui-card p-4 p-md-5" style="max-width: 420px; width: 100%;">
                <div class="text-center mb-4">
                    <div class="brand-icon mx-auto mb-3" style="width: 52px; height: 52px; font-size: 1.5rem; border-radius: 14px; background: #059669;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h3 class="fw-bold mb-1">İki Adımlı Doğrulama</h3>
                    <p class="text-muted small">Google Authenticator uygulamanızdaki 6 haneli doğrulama kodunu giriniz.</p>
                </div>

                <?php if ($errorAlert !== ''): ?>
                    <div class="alert alert-danger py-2 small border-0 rounded-3 mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <div><?= htmlspecialchars($errorAlert, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="verify_2fa_login">

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">6 Haneli TOTP Kodu</label>
                        <input type="text" 
                               name="totp_code" 
                               maxlength="6"
                               inputmode="numeric"
                               pattern="[0-9]*"
                               class="form-control form-control-lg text-center fs-3 tracking-widest bg-light fw-bold letter-spacing" 
                               placeholder="000000" 
                               required 
                               autofocus 
                               autocomplete="one-time-code">
                    </div>

                    <button type="submit" class="btn btn-success w-100 py-2 rounded-3 fw-semibold">
                        Kodu Doğrula & Giriş Yap <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </form>

                <div class="text-center mt-4 pt-3 border-top">
                    <a href="admin.php?action=logout" class="text-muted small text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i> Giriş Ekranına Geri Dön
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ========================================================
             GİRİŞ EKRANI (LOGIN SCREEN — TERTEMİZ & GÜVENLİ)
             ======================================================== -->
        <div class="d-flex align-items-center justify-content-center min-vh-100 p-3">
            <div class="ui-card p-4 p-md-5" style="max-width: 420px; width: 100%;">
                <div class="text-center mb-4">
                    <div class="brand-icon mx-auto mb-3" style="width: 48px; height: 48px; font-size: 1.4rem; border-radius: 12px;">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h3 class="fw-bold mb-1">HelpMe Admin</h3>
                    <p class="text-muted small">Yönetim paneline erişmek için oturum açın.</p>
                </div>

                <?php if ($errorAlert !== ''): ?>
                    <div class="alert alert-danger py-2 small border-0 rounded-3 mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <div><?= htmlspecialchars($errorAlert, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($successAlert !== ''): ?>
                    <div class="alert alert-success py-2 small border-0 rounded-3 mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill"></i>
                        <div><?= htmlspecialchars($successAlert, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="login">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Kullanıcı Adı</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted">
                                <i class="bi bi-person-fill"></i>
                            </span>
                            <input type="text" 
                                   name="username" 
                                   class="form-control bg-light border-start-0" 
                                   placeholder="Kullanıcı adınızı girin" 
                                   required 
                                   autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Yönetici Şifresi</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted">
                                <i class="bi bi-key-fill"></i>
                            </span>
                            <input type="password" 
                                   name="password" 
                                   class="form-control bg-light border-start-0" 
                                   placeholder="Şifrenizi girin" 
                                   required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                        Güvenli Giriş Yap <i class="bi bi-box-arrow-in-right ms-1"></i>
                    </button>
                </form>

                <div class="text-center mt-4 pt-3 border-top">
                    <a href="../public/index.php" class="text-muted small text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i> HelpMe Ana Sayfasına Dön
                    </a>
                </div>
            </div>
        </div>

    <?php endif; ?>

<?php else: ?>
    <!-- ========================================================
         ADMIN PANELİ ANA EKRANI
         ======================================================== -->
    
    <!-- Navbar -->
    <nav class="admin-nav sticky-top mb-4">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <a href="admin.php" class="text-decoration-none d-flex align-items-center gap-2 text-dark fw-bold fs-5">
                    <span class="brand-icon">
                        <i class="bi bi-chat-heart-fill"></i>
                    </span>
                    HelpMe <span class="badge bg-dark fw-normal fs-6">Admin Panel</span>
                </a>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- Güvenlik / Ayarlar Modalı Butonu -->
                <button type="button" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#settingsModal">
                    <i class="bi bi-gear-fill me-1"></i> Ayarlar & Güvenlik
                    <?php if (!empty($currentAdmin['two_factor_enabled'])): ?>
                        <span class="badge bg-success rounded-pill ms-1" title="2FA Aktif"><i class="bi bi-shield-check"></i> 2FA</span>
                    <?php endif; ?>
                </button>

                <a href="../public/track.php" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-search me-1"></i> Takip Ekranı
                </a>
                <a href="../public/index.php" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-globe me-1"></i> Siteye Git
                </a>
                <a href="admin.php?action=logout" class="btn btn-sm btn-danger rounded-pill px-3" onclick="return confirm('Çıkış yapmak istediğinize emin misiniz?');">
                    <i class="bi bi-power me-1"></i> Çıkış Yap
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 pb-5">

        <!-- Bildirimler -->
        <?php if ($errorAlert !== ''): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-2 p-3">
                <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                <div><?= htmlspecialchars($errorAlert, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php endif; ?>

        <?php if ($successAlert !== ''): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-2 p-3">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div class="fw-semibold"><?= htmlspecialchars($successAlert, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php endif; ?>

        <?php if ($activeTicket): ?>
            <!-- ========================================================
                 TALEP DETAY & CEVAPLAMA EKRANI
                 ======================================================== -->
            <div class="mb-3">
                <a href="admin.php" class="btn btn-sm btn-light border rounded-pill px-3 text-secondary fw-semibold">
                    <i class="bi bi-arrow-left me-1"></i> Talepler Listesine Dön
                </a>
            </div>

            <div class="row g-4">
                
                <!-- Sol Kolon: Talep Bilgileri ve Durum Yönetimi -->
                <div class="col-lg-4">
                    <div class="ui-card p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <div>
                                <small class="text-muted d-block">Talep Kodu</small>
                                <span class="fs-4 fw-bold text-dark">
                                    #<?= htmlspecialchars($activeTicket['tracking_code'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div>
                                <?= renderStatusBadge((string)($activeTicket['status'] ?? 'waiting')) ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="mb-3">
                                <label class="text-muted small d-block">Kullanıcı:</label>
                                <strong class="fs-6 text-dark"><?= htmlspecialchars($activeTicket['fullname'], ENT_QUOTES, 'UTF-8') ?></strong>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small d-block">E-posta:</label>
                                <a href="mailto:<?= htmlspecialchars($activeTicket['email'], ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none fw-semibold">
                                    <?= htmlspecialchars($activeTicket['email'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small d-block">Talep Türü:</label>
                                <span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($activeTicket['service_type'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div>
                                <label class="text-muted small d-block">Oluşturulma Tarihi:</label>
                                <span class="text-secondary"><?= date('d.m.Y H:i', strtotime($activeTicket['created_at'])) ?></span>
                            </div>
                        </div>

                        <!-- Durum Değiştirme Formu -->
                        <form method="POST" action="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?') . '?ticket_id=' . (int)$activeTicket['id'], ENT_QUOTES, 'UTF-8') ?>" class="border-top pt-3">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="ticket_id" value="<?= (int)$activeTicket['id'] ?>">

                            <label class="form-label fw-bold small text-dark mb-2">Talep Durumunu Değiştir:</label>
                            <div class="input-group">
                                <select name="status" class="form-select form-select-sm bg-light">
                                    <option value="waiting" <?= in_array($activeTicket['status'] ?? '', ['waiting', 'open'], true) ? 'selected' : '' ?>>⏳ Beklemede</option>
                                    <option value="answered" <?= ($activeTicket['status'] ?? '') === 'answered' ? 'selected' : '' ?>>✓ Yanıtlandı</option>
                                    <option value="closed" <?= ($activeTicket['status'] ?? '') === 'closed' ? 'selected' : '' ?>>🔒 Çözüldü / Kapatıldı</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    Güncelle
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Müşteri Linki Paylaşım Kartı -->
                    <div class="ui-card p-3">
                        <div class="fw-semibold text-dark small mb-2"><i class="bi bi-link-45deg me-1"></i>Kullanıcı Takip Linki:</div>
                        <div class="input-group input-group-sm">
                            <?php
                            $httpHost = $_SERVER['HTTP_HOST'] ?? '';
                            $isLocalEnv = (strpos($httpHost, 'localhost') !== false || strpos($httpHost, '127.0.0.1') !== false);
                            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                            $basePrefix = $isLocalEnv ? '/HelpMeProject' : '';
                            $trackingUrl = $scheme . '://' . $httpHost . $basePrefix . '/public/track.php?code=' . $activeTicket['tracking_code'];
                            ?>
                            <input type="text" id="clientLinkInput" class="form-control bg-light" readonly value="<?= htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8') ?>">
                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('clientLinkInput').value); this.innerText='Kopyalandı!';">
                                Kopyala
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sağ Kolon: Mesajlaşma Geçmişi (Chat Thread) ve Admin Yanıtlama Alanı -->
                <div class="col-lg-8">
                    <div class="ui-card p-4">
                        
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-chat-dots-fill text-primary"></i> Yazışma Geçmişi
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill fw-normal fs-6">
                                    <?= 1 + count($activeReplies) ?> Mesaj
                                </span>
                            </h5>
                            <small class="text-muted">Kronolojik Sıra</small>
                        </div>

                        <!-- Chat Akışı -->
                        <div class="chat-container mb-4" id="adminChatArea">
                            
                            <!-- 1. Orijinal Talep Mesajı (Kullanıcı) -->
                            <div class="chat-row is-user">
                                <div class="chat-bubble-card">
                                    <div class="chat-sender-header">
                                        <span class="fw-bold text-dark">
                                            <i class="bi bi-person-fill text-primary me-1"></i><?= htmlspecialchars($activeTicket['fullname'], ENT_QUOTES, 'UTF-8') ?> (İlk Başvuru)
                                        </span>
                                        <span class="chat-time-tag text-muted">
                                            <?= date('d.m.Y H:i', strtotime($activeTicket['created_at'])) ?>
                                        </span>
                                    </div>
                                    <p class="chat-body-text">
                                        <?= nl2br(htmlspecialchars($activeTicket['message'], ENT_QUOTES, 'UTF-8')) ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Karşılıklı Mesajlar -->
                            <?php foreach ($activeReplies as $reply): ?>
                                <?php if ($reply['sender_type'] === 'admin'): ?>
                                    <!-- Admin Mesajı (Mavi) -->
                                    <div class="chat-row is-admin">
                                        <div class="chat-bubble-card">
                                            <div class="chat-sender-header">
                                                <span class="fw-bold text-white">
                                                    <i class="bi bi-shield-check me-1"></i>HelpMe Destek Yetkilisi (Siz)
                                                </span>
                                                <span class="chat-time-tag">
                                                    <?= date('d.m.Y H:i', strtotime($reply['created_at'])) ?>
                                                </span>
                                            </div>
                                            <p class="chat-body-text">
                                                <?= nl2br(htmlspecialchars($reply['message'], ENT_QUOTES, 'UTF-8')) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Kullanıcı Yanıtı (Beyaz) -->
                                    <div class="chat-row is-user">
                                        <div class="chat-bubble-card">
                                            <div class="chat-sender-header">
                                                <span class="fw-bold text-dark">
                                                    <i class="bi bi-person-fill text-primary me-1"></i><?= htmlspecialchars($activeTicket['fullname'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <span class="chat-time-tag text-muted">
                                                    <?= date('d.m.Y H:i', strtotime($reply['created_at'])) ?>
                                                </span>
                                            </div>
                                            <p class="chat-body-text">
                                                <?= nl2br(htmlspecialchars($reply['message'], ENT_QUOTES, 'UTF-8')) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>

                        </div>

                        <!-- Admin Cevap Gönderme Formu -->
                        <div class="reply-card" id="replyArea">
                            <form method="POST" action="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?') . '?ticket_id=' . (int)$activeTicket['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="admin_reply">
                                <input type="hidden" name="ticket_id" value="<?= (int)$activeTicket['id'] ?>">

                                <div class="mb-3">
                                    <label class="form-label fw-bold text-dark small mb-2">
                                        <i class="bi bi-reply-fill text-primary me-1"></i>Kullanıcıya Admin Yanıtı Gönder:
                                    </label>
                                    <textarea name="message" 
                                              rows="4" 
                                              class="form-control rounded-3 bg-white" 
                                              placeholder="Müşteriye iletilecek profesyonel çözüm veya yanıt mesajını yazın..." 
                                              required></textarea>
                                </div>

                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="small text-muted fw-semibold">Yanıt sonrası durum:</label>
                                        <select name="status" class="form-select form-select-sm bg-white" style="width: auto;">
                                            <option value="answered" selected>✓ Yanıtlandı Yap</option>
                                            <option value="closed">🔒 Çözüldü & Kapat</option>
                                            <option value="waiting">⏳ Beklemede Bırak</option>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">
                                        <i class="bi bi-send-fill me-1"></i> Admin Cevabı Gönder
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>

            </div>

        <?php else: ?>
            <!-- ========================================================
                 TALEP LİSTESİ & İSTATİSTİKLER (DASHBOARD)
                 ======================================================== -->
            
            <!-- İstatistik Kartları (Kusursuz Sayım) -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold">Toplam Talep</span>
                            <div class="brand-icon" style="width: 28px; height: 28px; font-size: 0.85rem;">
                                <i class="bi bi-ticket-perforated-fill"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-0 text-dark"><?= $stats['total'] ?></h3>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold">Bekleyen Talepler</span>
                            <span class="badge bg-warning-subtle text-warning-emphasis rounded-circle p-2">
                                <i class="bi bi-clock-history"></i>
                            </span>
                        </div>
                        <h3 class="fw-bold mb-0 text-warning-emphasis"><?= $stats['waiting'] ?></h3>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold">Yanıtlananlar</span>
                            <span class="badge bg-success-subtle text-success rounded-circle p-2">
                                <i class="bi bi-check2-circle"></i>
                            </span>
                        </div>
                        <h3 class="fw-bold mb-0 text-success"><?= $stats['answered'] ?></h3>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold">Çözülen / Kapalı</span>
                            <span class="badge bg-secondary-subtle text-secondary rounded-circle p-2">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                        </div>
                        <h3 class="fw-bold mb-0 text-secondary"><?= $stats['closed'] ?></h3>
                    </div>
                </div>
            </div>

            <!-- Filtreleme ve Arama Çubuğu -->
            <div class="ui-card p-3 mb-4">
                <form method="GET" action="admin.php" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" 
                                   name="q" 
                                   class="form-control bg-light border-start-0" 
                                   placeholder="Takip kodu, ad soyad veya e-posta ara..." 
                                   value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <select name="status" class="form-select bg-light">
                            <option value="">Tüm Durumlar</option>
                            <option value="waiting" <?= in_array($_GET['status'] ?? '', ['waiting', 'open'], true) ? 'selected' : '' ?>>Beklemede</option>
                            <option value="answered" <?= ($_GET['status'] ?? '') === 'answered' ? 'selected' : '' ?>>Yanıtlandı</option>
                            <option value="closed" <?= ($_GET['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Çözüldü</option>
                        </select>
                    </div>

                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary rounded-3 flex-fill fw-semibold">
                            <i class="bi bi-funnel me-1"></i> Filtrele
                        </button>
                        <?php if (!empty($_GET['q']) || !empty($_GET['status'])): ?>
                            <a href="admin.php" class="btn btn-outline-secondary rounded-3" title="Filtreleri Temizle">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Talep Listesi Tablosu -->
            <div class="ui-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="py-3 px-3">Takip Kodu</th>
                                <th scope="col" class="py-3">Talep Eden</th>
                                <th scope="col" class="py-3">Departman</th>
                                <th scope="col" class="py-3">Mesaj Özeti</th>
                                <th scope="col" class="py-3">Durum</th>
                                <th scope="col" class="py-3">Tarih</th>
                                <th scope="col" class="py-3 px-3 text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ticketsList)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                        Kayıtlı destek talebi bulunamadı.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ticketsList as $t): ?>
                                    <tr>
                                        <td class="px-3">
                                            <a href="admin.php?ticket_id=<?= (int)$t['id'] ?>" class="fw-bold text-decoration-none text-primary">
                                                #<?= htmlspecialchars($t['tracking_code'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($t['fullname'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($t['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= htmlspecialchars($t['service_type'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="text-muted small text-truncate" style="max-width: 240px;">
                                                <?= htmlspecialchars($t['message'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?= renderStatusBadge((string)($t['status'] ?? 'waiting')) ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?= date('d.m.Y H:i', strtotime($t['created_at'])) ?>
                                        </td>
                                        <td class="px-3 text-end">
                                            <a href="admin.php?ticket_id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                                                İncele & Yanıtla <i class="bi bi-arrow-right ms-1"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <!-- ========================================================
         AYARLAR & GÜVENLİK MODALI (ŞİFRE DEĞİŞTİRME & 2FA)
         ======================================================== -->
    <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                
                <div class="modal-header border-bottom px-4 pt-4 pb-3">
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="settingsModalLabel">
                        <i class="bi bi-shield-lock-fill text-primary"></i> Yönetici Ayarları & Güvenlik
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Sekmeler (Tabs) -->
                    <ul class="nav nav-pills nav-fill mb-4 bg-light p-1 rounded-3" id="settingsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-3 py-2 small fw-semibold" id="pass-tab" data-bs-toggle="tab" data-bs-target="#pass-tab-pane" type="button" role="tab">
                                <i class="bi bi-key-fill me-1"></i> Şifre Değiştir
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-3 py-2 small fw-semibold" id="twofa-tab" data-bs-toggle="tab" data-bs-target="#twofa-tab-pane" type="button" role="tab">
                                <i class="bi bi-phone-fill me-1"></i> Google 2FA
                                <?php if (!empty($currentAdmin['two_factor_enabled'])): ?>
                                    <span class="badge bg-success ms-1">Aktif</span>
                                <?php endif; ?>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="settingsTabContent">
                        
                        <!-- 1. Sekme: Şifre Değiştirme Formu -->
                        <div class="tab-pane fade show active" id="pass-tab-pane" role="tabpanel">
                            <form method="POST" action="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="change_password">

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-muted">Mevcut Şifre</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-muted">Yeni Şifre (En az 6 karakter)</label>
                                    <input type="password" name="new_password" minlength="6" class="form-control" required>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-semibold text-muted">Yeni Şifre Tekrar</label>
                                    <input type="password" name="confirm_password" minlength="6" class="form-control" required>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 rounded-3 fw-semibold">
                                    <i class="bi bi-check-lg me-1"></i> Şifremi Güncelle
                                </button>
                            </form>
                        </div>

                        <!-- 2. Sekme: Google Authenticator (2FA) Entegrasyonu -->
                        <div class="tab-pane fade" id="twofa-tab-pane" role="tabpanel">
                            <?php if (!empty($currentAdmin['two_factor_enabled'])): ?>
                                <!-- 2FA Zaten Aktifse: Kapatma Seçeneği -->
                                <div class="text-center p-3">
                                    <div class="rounded-circle bg-success bg-opacity-10 text-success p-3 d-inline-flex mb-3" style="width: 56px; height: 56px; font-size: 1.5rem; align-items: center; justify-content: center;">
                                        <i class="bi bi-shield-check"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">İki Adımlı Doğrulama (2FA) Aktif</h6>
                                    <p class="text-muted small mb-4">
                                        Hesabınız Google Authenticator ile güvende. Giriş yaparken her seferinde 6 haneli kod istenir.
                                    </p>

                                    <form method="POST" action="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('2FA korumasını kapatmak istediğinize emin misiniz?');">
                                        <input type="hidden" name="action" value="disable_2fa">

                                        <div class="mb-3 text-start">
                                            <label class="form-label small fw-semibold text-muted">Onay İçin Yönetici Şifreniz:</label>
                                            <input type="password" name="confirm_password" class="form-control" placeholder="Mevcut şifrenizi girin" required>
                                        </div>

                                        <button type="submit" class="btn btn-outline-danger w-100 rounded-3 fw-semibold">
                                            <i class="bi bi-shield-x me-1"></i> 2FA Korumasını Kapat
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <!-- 2FA Aktif Değilse: QR Kod ve Kurulum Formu -->
                                <div>
                                    <div class="text-center mb-3">
                                        <p class="small text-muted mb-3">
                                            1. Telefonunuzdaki <strong>Google Authenticator</strong> (veya Authy) ile aşağıdaki QR kodu tarayın:
                                        </p>
                                        
                                        <div class="p-2 border rounded-3 d-inline-block bg-white shadow-sm mb-2">
                                            <img src="<?= htmlspecialchars($pending2faQrUrl, ENT_QUOTES, 'UTF-8') ?>" alt="2FA QR Code" width="160" height="160">
                                        </div>

                                        <div class="small text-muted">
                                            Manuel Giriş Anahtarı: <code class="user-select-all fw-bold"><?= htmlspecialchars($pending2faSecret, ENT_QUOTES, 'UTF-8') ?></code>
                                        </div>
                                    </div>

                                    <form method="POST" action="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES, 'UTF-8') ?>" class="border-top pt-3">
                                        <input type="hidden" name="action" value="enable_2fa">
                                        <input type="hidden" name="secret_code" value="<?= htmlspecialchars($pending2faSecret, ENT_QUOTES, 'UTF-8') ?>">

                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold text-muted">
                                                2. Uygulamadaki 6 Haneli Doğrulama Kodunu Girin:
                                            </label>
                                            <input type="text" 
                                                   name="verify_code" 
                                                   maxlength="6" 
                                                   inputmode="numeric" 
                                                   class="form-control text-center fs-5 fw-bold letter-spacing" 
                                                   placeholder="123456" 
                                                   required>
                                        </div>

                                        <button type="submit" class="btn btn-success w-100 rounded-3 fw-semibold">
                                            <i class="bi bi-shield-lock-fill me-1"></i> 2FA'yı Aktif Et & Doğrula
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

<?php endif; ?>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Admin chat alanını en alta kaydır
    const adminChat = document.getElementById('adminChatArea');
    if (adminChat) {
        adminChat.scrollTop = adminChat.scrollHeight;
    }
</script>
</body>
</html>
