-- ============================================================
--  HelpMe SaaS Destek Platformu — Tek ve Tam Veritabanı Kurulum Scripti (v3)
-- ============================================================
--  Bu scripti phpMyAdmin'de SQL sekmesine yapıştırıp "Git" (Go) 
--  butonuna basmanız yeterlidir. Sıfırdan tüm tabloları,
--  ilişkileri, indeksleri ve ilk admin hesabını eksiksiz kurar.
-- ============================================================

-- 1) Veritabanını oluştur ve seç
CREATE DATABASE IF NOT EXISTS helpme_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE helpme_db;

-- 2) tickets Tablosu (Destek Talepleri)
CREATE TABLE IF NOT EXISTS tickets (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tracking_code VARCHAR(32)  NOT NULL UNIQUE,
    fullname      VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    service_type  VARCHAR(50)  NOT NULL,
    message       TEXT         NOT NULL,
    status        ENUM('waiting', 'open', 'answered', 'closed') NOT NULL DEFAULT 'waiting',
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) ticket_replies Tablosu (Mesajlaşma Thread'i)
CREATE TABLE IF NOT EXISTS ticket_replies (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT NOT NULL,
    sender_type ENUM('user', 'admin') NOT NULL,
    message     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_replies_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) admins Tablosu (Yönetici Hesabı ve 2FA)
CREATE TABLE IF NOT EXISTS admins (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    username           VARCHAR(50)  NOT NULL UNIQUE,
    password_hash      VARCHAR(255) NOT NULL,
    two_factor_secret  VARCHAR(64)  NULL,
    two_factor_enabled TINYINT(1)   NOT NULL DEFAULT 0,
    created_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) İlk Yönetici Hesabı (Varsayılan şifre: admin123)
-- Not: Panelden dilediğiniz zaman Şifre Değiştir modalı ile güncelleyebilirsiniz.
INSERT IGNORE INTO admins (username, password_hash, two_factor_enabled)
VALUES (
    'admin',
    '$2y$10$tKqWFXn39m/NN5sHqu5a.uhSu0l9ZauDDrTyFa6Z515QYeOAFBrJ6',
    0
);

-- 6) Performans İndeksleri
CREATE INDEX IF NOT EXISTS idx_tickets_email      ON tickets (email);
CREATE INDEX IF NOT EXISTS idx_tickets_created_at ON tickets (created_at);
CREATE INDEX IF NOT EXISTS idx_tickets_status     ON tickets (status);
CREATE INDEX IF NOT EXISTS idx_replies_ticket_id  ON ticket_replies (ticket_id);
CREATE INDEX IF NOT EXISTS idx_replies_created_at ON ticket_replies (created_at);
