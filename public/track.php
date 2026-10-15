<?php

/**
 * ============================================================
 *  HelpMe — Talep Takip ve Karşılıklı Mesajlaşma Modülü
 * ============================================================
 *
 *  Kullanıcıların takip kodu (tracking_code) ile taleplerini
 *  sorgulamalarını, geçmiş mesajlaşmaları (chat thread)
 *  görüntülemelerini ve yeni mesaj göndermelerini sağlar.
 *
 *  @package HelpMe
 *  @file    track.php
 *  @requires PHP 8.0+
 * ============================================================
 */

declare(strict_types=1);

session_start();

// Veritabanı bağlantı katmanını dahil et
require_once __DIR__ . '/../includes/db.php';

// Değişkenler
$trackingCode = '';
$ticket       = null;
$replies      = [];
$errorMessage = '';
$successAlert = '';

// Session Flash mesajlarını oku ve temizle
if (!empty($_SESSION['flash_success'])) {
    $successAlert = (string)$_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $errorMessage = (string)$_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Kod URL parametresinden veya POST'tan alınır
if (isset($_GET['code'])) {
    $trackingCode = strtoupper(trim((string)$_GET['code']));
} elseif (isset($_POST['tracking_code'])) {
    $trackingCode = strtoupper(trim((string)$_POST['tracking_code']));
}

// ─────────────────────────────────────────────────────────────
// 1) Yeni Cevap Gönderme İşlemi (POST) — Tam PRG Mimarisi
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_reply') {
    $replyMessage = trim((string)($_POST['message'] ?? ''));
    $ticketId     = (int)($_POST['ticket_id'] ?? 0);
    $trackingCode = strtoupper(trim((string)($_POST['tracking_code'] ?? '')));

    if ($ticketId <= 0 || $trackingCode === '') {
        $_SESSION['flash_error'] = 'Geçersiz talep referansı.';
    } elseif ($replyMessage === '') {
        $_SESSION['flash_error'] = 'Lütfen göndermek için bir mesaj yazınız.';
    } elseif (mb_strlen($replyMessage, 'UTF-8') < 3) {
        $_SESSION['flash_error'] = 'Mesajınız en az 3 karakter olmalıdır.';
    } else {
        try {
            $pdo = Database::connect();

            // Talebin varlığını ve durumunu doğrula
            $checkStmt = $pdo->prepare('SELECT id, status FROM tickets WHERE id = :id AND tracking_code = :code LIMIT 1');
            $checkStmt->execute([':id' => $ticketId, ':code' => $trackingCode]);
            $validTicket = $checkStmt->fetch();

            if (!$validTicket) {
                $_SESSION['flash_error'] = 'Talep doğrulaması başarısız oldu.';
            } elseif (($validTicket['status'] ?? '') === 'closed') {
                // ÇÖZÜLDÜ / KAPALI DURUMUNDA KULLANICI MESAJ GÖNDEREMEZ!
                $_SESSION['flash_error'] = 'Bu destek talebi çözüme kavuşturulduğu için kapatılmıştır. Kapatılmış taleplere yeni mesaj gönderilemez.';
            } else {
                // Cevabı ticket_replies tablosuna ekle
                $insertStmt = $pdo->prepare('
                    INSERT INTO ticket_replies (ticket_id, sender_type, message, created_at)
                    VALUES (:ticket_id, :sender_type, :message, NOW())
                ');
                $insertStmt->execute([
                    ':ticket_id'   => $ticketId,
                    ':sender_type' => 'user',
                    ':message'     => $replyMessage,
                ]);

                // Kullanıcı cevap yazınca talep durumunu tekrar 'waiting' (Beklemede) yap
                $updateStmt = $pdo->prepare('UPDATE tickets SET status = "waiting" WHERE id = :id');
                $updateStmt->execute([':id' => $ticketId]);

                $_SESSION['flash_success'] = 'Cevabınız başarıyla iletildi. Destek ekibimiz en kısa sürede yanıt verecektir.';
            }
        } catch (Throwable $e) {
            error_log('[HelpMe Track Reply Error] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Mesajınız kaydedilirken bir hata oluştu. Lütfen tekrar deneyiniz.';
        }
    }

    // Evrensel PRG Yönlendirmesi (Asla doğrudan çıktı verme, her zaman temiz GET'e yönlendir)
    $cleanUri = strtok($_SERVER["REQUEST_URI"], '?');
    $redirectUrl = $cleanUri . ($trackingCode !== '' ? '?code=' . urlencode($trackingCode) : '');
    header("Location: " . $redirectUrl);
    exit();
}

// ─────────────────────────────────────────────────────────────
// 2) Talep ve Yanıtları Getirme (Sorgulama — GET)
// ─────────────────────────────────────────────────────────────
if ($trackingCode !== '') {
    try {
        $pdo = Database::connect();

        $ticketStmt = $pdo->prepare('
            SELECT id, tracking_code, fullname, email, service_type, message, status, created_at
            FROM tickets
            WHERE tracking_code = :code
            LIMIT 1
        ');
        $ticketStmt->execute([':code' => $trackingCode]);
        $ticket = $ticketStmt->fetch();

        if ($ticket) {
            // Yanıtları kronolojik sıra ile çek
            $repliesStmt = $pdo->prepare('
                SELECT id, sender_type, message, created_at
                FROM ticket_replies
                WHERE ticket_id = :ticket_id
                ORDER BY created_at ASC, id ASC
            ');
            $repliesStmt->execute([':ticket_id' => $ticket['id']]);
            $replies = $repliesStmt->fetchAll();
        } else {
            $errorMessage = '<strong>"' . htmlspecialchars($trackingCode, ENT_QUOTES, 'UTF-8') . '"</strong> koduna ait bir destek talebi bulunamadı. Lütfen takip kodunuzu kontrol ediniz.';
        }
    } catch (Throwable $e) {
        error_log('[HelpMe Track Query Error] ' . $e->getMessage());
        $errorMessage = 'Veritabanı sorgusu sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.';
    }
}

/**
 * Durum metnini ve Bootstrap rozet sınıfını döndürür
 */
function getStatusBadge(string $status): string {
    switch ($status) {
        case 'answered':
            return '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>Yanıtlandı</span>';
        case 'closed':
            return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill fw-semibold"><i class="bi bi-lock-fill me-1"></i>Çözüldü / Kapalı</span>';
        default:
            return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill fw-semibold"><i class="bi bi-clock-history me-1"></i>Beklemede</span>';
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Talep Takip & Mesajlaşma — HelpMe</title>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS & Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

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
            padding-top: 76px;
            min-height: 100vh;
        }

        /* Navbar */
        .helpme-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--card-border);
        }
        .brand-logo {
            font-weight: 800;
            font-size: 1.35rem;
            color: var(--ink);
            text-decoration: none;
            letter-spacing: -0.03em;
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

        /* Kartlar */
        .ui-card {
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }

        /* Meta Bilgi Kutuları */
        .meta-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.85rem 1rem;
        }
        .meta-box .label {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 0.2rem;
        }
        .meta-box .val {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Chat Akışı */
        .chat-container {
            background: #f1f5f9;
            border-radius: 16px;
            padding: 1.5rem;
            max-height: 520px;
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
            justify-content: flex-end;
        }
        .chat-row.is-admin {
            justify-content: flex-start;
        }

        .chat-bubble-card {
            max-width: 82%;
            border-radius: 18px;
            padding: 1.1rem 1.35rem;
            position: relative;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }

        /* Kullanıcı Balonu */
        .chat-row.is-user .chat-bubble-card {
            background: #ffffff;
            color: var(--ink);
            border: 1px solid #cbd5e1;
            border-bottom-right-radius: 4px;
        }

        /* Destek Ekibi Balonu */
        .chat-row.is-admin .chat-bubble-card {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            border: none;
            border-bottom-left-radius: 4px;
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

        /* Yanıt Alanı */
        .reply-card {
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 1.25rem;
        }
    </style>
</head>
<body>

    <!-- ========== NAVBAR ========== -->
    <nav class="navbar navbar-expand-lg fixed-top helpme-navbar py-2">
        <div class="container">
            <a class="brand-logo d-flex align-items-center gap-2" href="index.php">
                <span class="brand-icon">
                    <i class="bi bi-chat-heart-fill"></i>
                </span>
                HelpMe
            </a>

            <div class="d-flex align-items-center gap-2">
                <a href="index.php#destek-talebi" class="btn btn-outline-dark rounded-pill px-3 py-2 btn-sm fw-semibold">
                    <i class="bi bi-plus-lg me-1"></i> Yeni Talep Aç
                </a>
                <a href="../admin/admin.php" class="btn btn-light rounded-pill px-3 py-2 btn-sm border fw-semibold text-secondary">
                    <i class="bi bi-shield-lock me-1"></i> Yönetici Paneli
                </a>
            </div>
        </div>
    </nav>

    <!-- ========== ANA İÇERİK ========== -->
    <main class="container py-4">

        <!-- 1) ARAMA / SORGULAMA KARTI -->
        <div class="row justify-content-center mb-4">
            <div class="col-lg-9">
                <div class="ui-card p-3 p-md-4">
                    <form method="GET" action="track.php" class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center gap-2 text-dark">
                                <i class="bi bi-search fs-5 text-primary"></i>
                                <span class="fw-bold">Talep Sorgula:</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted">
                                    <i class="bi bi-ticket-perforated"></i>
                                </span>
                                <input type="text" 
                                       name="code" 
                                       class="form-control bg-light border-start-0" 
                                       placeholder="Takip Kodu (Örn: HM-8ZN3D)" 
                                       value="<?= htmlspecialchars($trackingCode, ENT_QUOTES, 'UTF-8') ?>" 
                                       required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100 rounded-3 fw-semibold">
                                Sorgula <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Bildirimler -->
        <?php if ($errorMessage !== ''): ?>
            <div class="row justify-content-center mb-4">
                <div class="col-lg-9">
                    <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex align-items-center gap-3 p-3">
                        <i class="bi bi-exclamation-triangle-fill fs-4 text-warning"></i>
                        <div><?= $errorMessage ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($successAlert !== ''): ?>
            <div class="row justify-content-center mb-4">
                <div class="col-lg-9">
                    <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center gap-3 p-3">
                        <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                        <div class="fw-semibold"><?= htmlspecialchars($successAlert, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($ticket): ?>
            <!-- 2) TALEP DETAYI VE CHAT AKIŞI -->
            <div class="row justify-content-center">
                <div class="col-lg-9">

                    <!-- Talep Üst Bilgi Kartı -->
                    <div class="ui-card p-4 mb-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pb-3 border-bottom mb-3">
                            <div>
                                <span class="text-muted small">Takip Kodu:</span>
                                <h3 class="fw-bold mb-0 text-dark">
                                    #<?= htmlspecialchars($ticket['tracking_code'], ENT_QUOTES, 'UTF-8') ?>
                                </h3>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <?= getStatusBadge((string)($ticket['status'] ?? 'waiting')) ?>
                                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" 
                                        onclick="navigator.clipboard.writeText('<?= htmlspecialchars($ticket['tracking_code'], ENT_QUOTES, 'UTF-8') ?>'); this.innerHTML='<i class=\'bi bi-check2\'></i> Kopyalandı';">
                                    <i class="bi bi-copy me-1"></i> Kodu Kopyala
                                </button>
                            </div>
                        </div>

                        <!-- 4'lü Meta Bilgi Kutuları -->
                        <div class="row g-2">
                            <div class="col-sm-6 col-md-3">
                                <div class="meta-box">
                                    <div class="label"><i class="bi bi-person me-1"></i>Talep Sahibi</div>
                                    <div class="val" title="<?= htmlspecialchars($ticket['fullname'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($ticket['fullname'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="meta-box">
                                    <div class="label"><i class="bi bi-envelope me-1"></i>E-Posta</div>
                                    <div class="val" title="<?= htmlspecialchars($ticket['email'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($ticket['email'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="meta-box">
                                    <div class="label"><i class="bi bi-tag me-1"></i>Hizmet / Tür</div>
                                    <div class="val">
                                        <?= htmlspecialchars($ticket['service_type'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="meta-box">
                                    <div class="label"><i class="bi bi-calendar3 me-1"></i>Tarih</div>
                                    <div class="val">
                                        <?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Thread (Yazışma Kartı) -->
                    <div class="ui-card p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-chat-dots-fill text-primary"></i> Mesajlaşma Geçmişi
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill fw-normal fs-6">
                                    <?= 1 + count($replies) ?> Mesaj
                                </span>
                            </h5>
                            <small class="text-muted"><i class="bi bi-arrow-down-up me-1"></i>Kronolojik Sıra</small>
                        </div>

                        <!-- Chat Mesajları Kutusu -->
                        <div class="chat-container mb-4" id="chatWindow">
                            
                            <!-- 1. Mesaj: Kullanıcının Orijinal Talep Mesajı -->
                            <div class="chat-row is-user">
                                <div class="chat-bubble-card">
                                    <div class="chat-sender-header">
                                        <span class="fw-bold text-primary">
                                            <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($ticket['fullname'], ENT_QUOTES, 'UTF-8') ?> (İlk Talep)
                                        </span>
                                        <span class="chat-time-tag text-muted">
                                            <?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?>
                                        </span>
                                    </div>
                                    <p class="chat-body-text">
                                        <?= nl2br(htmlspecialchars($ticket['message'], ENT_QUOTES, 'UTF-8')) ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Karşılıklı Mesajlar (ticket_replies) -->
                            <?php foreach ($replies as $reply): ?>
                                <?php if ($reply['sender_type'] === 'admin'): ?>
                                    <!-- DESTEK EKİBİ (SOLDA / MAVİ BALON) -->
                                    <div class="chat-row is-admin">
                                        <div class="chat-bubble-card">
                                            <div class="chat-sender-header">
                                                <span class="fw-bold text-white">
                                                    <i class="bi bi-patch-check-fill me-1"></i>HelpMe Destek Yetkilisi
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
                                    <!-- KULLANICI YANITI (SAĞDA / BEYAZ BALON) -->
                                    <div class="chat-row is-user">
                                        <div class="chat-bubble-card">
                                            <div class="chat-sender-header">
                                                <span class="fw-bold text-dark">
                                                    <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($ticket['fullname'], ENT_QUOTES, 'UTF-8') ?>
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

                        <!-- Kullanıcı Yanıt Formu / Kapalı Talep Bildirimi -->
                        <?php if (($ticket['status'] ?? '') === 'closed'): ?>
                            <div class="alert alert-secondary border-0 shadow-sm rounded-4 p-4 d-flex align-items-center gap-3 mb-0" id="replyArea">
                                <div class="rounded-circle bg-secondary bg-opacity-10 text-secondary p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; min-width: 52px;">
                                    <i class="bi bi-lock-fill fs-3"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">Bu Destek Talebi Çözüldü / Kapatıldı</h6>
                                    <p class="mb-0 text-muted small">
                                        Bu talep sonuçlandırıldığı için yeni mesaj gönderimine kapatılmıştır. Yeni bir konuda destek almak isterseniz lütfen <a href="index.php#destek-talebi" class="fw-semibold text-primary text-decoration-none">yeni bir destek talebi oluşturun</a>.
                                    </p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="reply-card" id="replyArea">
                                <form method="POST" action="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?') . '?code=' . urlencode($ticket['tracking_code']), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="send_reply">
                                    <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                                    <input type="hidden" name="tracking_code" value="<?= htmlspecialchars($ticket['tracking_code'], ENT_QUOTES, 'UTF-8') ?>">

                                    <label class="form-label fw-bold text-dark mb-2">
                                        <i class="bi bi-reply-fill text-primary me-1"></i>Bu Talebe Cevap Yaz:
                                    </label>
                                    <div class="mb-3">
                                        <textarea name="message" 
                                                  class="form-control rounded-3 bg-light" 
                                                  rows="3" 
                                                  placeholder="Ek bilgi iletmek veya destek yetkilisine cevap vermek için buraya yazın..." 
                                                  required></textarea>
                                    </div>
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <small class="text-muted">
                                            <i class="bi bi-shield-check text-success me-1"></i>Mesajınız doğrudan destek yetkilisine iletilir.
                                        </small>
                                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">
                                            <i class="bi bi-send-fill me-1"></i> Cevap Gönder
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>

                    </div>

                </div>
            </div>
        <?php elseif ($trackingCode === '' && $errorMessage === ''): ?>
            <!-- Henüz sorgu yapılmadıysa -->
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="ui-card p-5 text-center">
                        <div class="brand-icon mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.6rem; border-radius: 14px;">
                            <i class="bi bi-ticket-detailed"></i>
                        </div>
                        <h4 class="fw-bold mb-2">Takip Kodunuzu Girin</h4>
                        <p class="text-muted mb-4" style="max-width: 480px; margin: 0 auto;">
                            Talebinizi oluştururken size verilen <code>HM-XXXXX</code> formatındaki takip kodunu yukarıdaki alana girerek yazışma geçmişinizi görüntüleyebilir ve yeni mesaj gönderebilirsiniz.
                        </p>
                        <a href="index.php#destek-talebi" class="btn btn-outline-primary rounded-pill px-4 fw-semibold">
                            Yeni Bir Talep Oluştur
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chat penceresini en alta kaydır
        const chatWindow = document.getElementById('chatWindow');
        if (chatWindow) {
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }
    </script>
</body>
</html>
