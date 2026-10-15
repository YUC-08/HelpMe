# HelpMe — SaaS Destek ve Müşteri Hizmetleri Platformu

HelpMe, kullanıcıların herhangi bir üyelik zorunluluğu olmadan hızlıca destek talebi oluşturabildiği, kendilerine tahsis edilen benzersiz takip kodu (`HM-XXXXX`) ile taleplerini canlı mesajlaşma akışı (chat thread) şeklinde takip edebildiği; yöneticilerin ise 2FA (İki Aşamalı Doğrulama) korumalı panel üzerinden talepleri inceleyip anlık yanıtlayabildiği modern, hafif ve kurumsal bir destek platformudur.

Harici ağır framework veya hantal kütüphanelere ihtiyaç duymadan, **Saf PHP 8+ (Pure PHP)**, **PDO MySQL**, **Bootstrap 5** ve **Vanilla CSS** teknolojileriyle geliştirilmiştir.

---

## 🚀 Temel Özellikler ve Mimari

### 1. Kullanıcı Tarafı & Talep Yönetimi
- **Hızlı Talep Formu:** Kullanıcı ad, e-posta, departman ve mesajını girerek saniyeler içinde talep oluşturur (`public/index.php`).
- **REST-benzeri JSON API:** Talep gönderimleri arka planda `POST /api/submit_ticket.php` endpoint'ine `fetch` ile asenkron iletilir; sayfa yenilenmeden işlem tamamlanır.
- **Benzersiz Takip Kodu:** Her talebe rastgele üretilen ve çakışma kontrolü yapılan `HM-XXXXX` formatında kriptografik takip kodu atanır.
- **Tek Tıkla Kopyalama:** Başarılı form gönderiminde kullanıcıya takip kodu kopyalama butonu (SVG ikonlu) ve *"Lütfen takip kodunuzu kaybetmeyin!"* uyarısı sunulur.
- **İki Yönlü Mesajlaşma Akışı (Chat Thread):** Kullanıcı `public/track.php` sayfasından takip kodunu girerek destek ekibiyle karşılıklı alt alta mesajlaşabilir.
- **Çözülen Taleplerin Kilitlenmesi:** Durumu `closed` (Çözüldü/Kapalı) olan taleplerde mesaj yazma alanı otomatik olarak devre dışı kalır ve kullanıcı bilgilendirilir.

### 2. Güvenli Yönetici Paneli (`admin/admin.php`)
- **İki Aşamalı Doğrulama (2FA / Google Authenticator):** Harici kütüphane olmaksızın, saf PHP ile **RFC 6238 standardında TOTP** algoritması uygulanmıştır. Authenticator uygulamalarındaki 6 haneli zaman bazlı kod ile güvenli giriş yapılır.
- **Dinamik Veritabanı Tabanlı Yönetim:** Kod içinde hiçbir sabit şifre veya hash tutulmaz. Yönetici kimlikleri ve 2FA durumları `admins` tablosunda güvenle saklanır.
- **Şifre ve 2FA Ayar Modalları:** Yönetici, panel içi modal üzerinden şifresini değiştirebilir veya QR kod / gizli anahtar ile 2FA özelliğini dilediği zaman aktif/pasif hale getirebilir.
- **Canlı İstatistik Kartları:** Toplam Talep, Beklemede, Yanıtlananlar ve Çözülen talepler veritabanındaki durumlarla tam uyumlu olarak hesaplanır.
- **Gelişmiş Arama & Filtreleme:** Takip kodu, ad soyad veya e-posta ile anlık arama yapılabilir; duruma göre filtreleme uygulanabilir.
- **Kullanıcı Takip Linki Üretici:** Her talep detayında, müşteriye iletilmek üzere doğrudan takip linkini kopyalama aracı bulunur.

### 3. Yazılım Mimarisi & Güvenlik Önlemleri
- **PRG (Post-Redirect-Get) Mimarisi:** Hem kullanıcı takip ekranında hem admin panelinde form gönderildikten sonra sayfa `header('Location: ...')` ile GET isteğine yönlendirilir. Böylece tarayıcıda *"Geri"* tuşuna basıldığında veya F5 yapıldığında formun mükerrer gönderilmesi (Form Resubmission) engellenir.
- **PDO Prepared Statements:** SQL Enjeksiyonu (SQLi) açıklarını %100 engellemek için tüm veritabanı sorguları parametreli `prepare()` ve `execute()` fonksiyonlarıyla yürütülür.
- **XSS Koruması:** Kullanıcı ve yöneticiden gelen tüm girdiler arayüze basılırken `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` süzgecinden geçirilir.
- **Session Hijacking & Fixation Koruması:** Giriş işlemlerinde `session_regenerate_id(true)` çağrılarak oturum kimliği yenilenir; `httponly` session parametreleri kullanılır.
- **Bcrypt Şifreleme:** Parolalar PHP'nin native `password_hash($pass, PASSWORD_DEFAULT)` fonksiyonu ile hash'lenir, `password_verify()` ile doğrulanır.

---

## 📂 Dizin Yapısı

```text
HelpMeProject/
│
├── .htaccess                     # Kök istekleri public/index.php'ye yönlendiren kurallar
│
├── admin/
│   ├── .htaccess                 # Varsayılan açılış dosyasını admin.php yapan kural
│   └── admin.php                 # Yönetici paneli (Giriş, 2FA, Talep Yönetimi, İstatistikler)
│
├── api/
│   └── submit_ticket.php         # JSON formatında çalışan asenkron talep oluşturma API'si
│
├── database/
│   └── sql_v3.sql                # Tek ve tam MySQL veritabanı kurulum şeması
│
├── includes/
│   ├── auth.php                  # Dinamik admin auth & RFC 6238 TOTP 2FA çekirdeği
│   └── db.php                    # Singleton PDO veritabanı bağlantı katmanı (utf8mb4)
│
├── Js/
│   └── app.js                    # AJAX form gönderimi ve kopyalama animasyonları
│
├── public/
│   ├── helpme.css                # Tasarım ve modern responsive CSS stilleri
│   ├── index.php                 # Kullanıcı ana sayfası ve talep formu
│   └── track.php                 # Talep sorgulama ve karşılıklı mesajlaşma (thread) ekranı
│
├── AI_LOG.md                     # Yapay zeka geliştirme günlüğü ve mimari kararlar
└── README.md                     # Proje ana dokümantasyonu
```

---

## 🗄️ Veritabanı Şeması (`database/sql_v3.sql`)

Proje üç ana tablodan oluşur:

1. **`tickets`**: Açılan ana destek biletleri.
   - `id`, `tracking_code` (UNIQUE), `fullname`, `email`, `service_type`, `message`, `status` (`waiting`, `open`, `answered`, `closed`), `created_at`.
2. **`ticket_replies`**: Talebe bağlı alt mesajlar (Yazışma geçmişi).
   - `id`, `ticket_id` (FK -> `tickets.id` ON DELETE CASCADE), `sender_type` (`user`, `admin`), `message`, `created_at`.
3. **`admins`**: Yönetici kullanıcıları ve 2FA verileri.
   - `id`, `username` (UNIQUE), `password_hash`, `two_factor_secret`, `two_factor_enabled`, `created_at`, `updated_at`.

---

## ⚙️ Kurulum ve Çalıştırma Rehberi

### Gereksinimler
- **Web Sunucusu:** Apache (XAMPP / WampServer / LAMP)
- **PHP:** 8.0 veya üzeri (PDO ve OpenSSL aktif olmalı)
- **Veritabanı:** MySQL 5.7+ veya MariaDB 10.3+

### Adım 1: Dosyaları Yerleştirin
Projeyi web sunucunuzun kök dizinine taşıyın (XAMPP için):
```text
C:\xampp\htdocs\HelpMeProject
```

### Adım 2: Veritabanını Kurun
1. `http://localhost/phpmyadmin` adresini açın.
2. Üst menüden **SQL** sekmesine gelin.
3. [database/sql_v3.sql](file:///c:/xampp/htdocs/HelpMeProject/database/sql_v3.sql) dosyasının tüm içeriğini kopyalayıp buraya yapıştırın ve **Git (Go)** butonuna tıklayın.
4. Bu işlem `helpme_db` veritabanını, tüm tabloları, yabancı anahtarları, performans indekslerini ve varsayılan yönetici hesabını otomatik olarak oluşturacaktır.

### Adım 3: Veritabanı Bağlantısını Kontrol Edin
[includes/db.php](file:///c:/xampp/htdocs/HelpMeProject/includes/db.php) dosyasında varsayılan ayarlar XAMPP standartlarına göre tanımlanmıştır:
- `DB_HOST`: `127.0.0.1`
- `DB_NAME`: `helpme_db`
- `DB_USER`: `root`
- `DB_PASS`: `""` (boş)

Farklı bir MySQL şifresi veya portu kullanıyorsanız bu dosyayı güncelleyebilirsiniz.

### Adım 4: Projeyi Tarayıcıda Açın
- **Kullanıcı Ana Sayfası:** `http://localhost/HelpMeProject/` (veya `http://localhost/HelpMeProject/public/index.php`)
- **Talep Takip Sayfası:** `http://localhost/HelpMeProject/public/track.php`
- **Yönetici Paneli:** `http://localhost/HelpMeProject/admin/` (veya `http://localhost/HelpMeProject/admin/admin.php`)

---

## 🔐 Varsayılan Yönetici Bilgileri

| Kullanıcı Adı | Şifre | 2FA Durumu |
| :--- | :--- | :--- |
| `admin` | `auua123` | Başlangıçta Kapalı (Panelden aktif edilebilir) |

> [!IMPORTANT]
> Güvenliğiniz için sisteme ilk giriş yaptıktan sonra, üst bardaki **"Ayarlar & Güvenlik"** butonuna basarak parolanızı güncelleyin ve Google Authenticator ile İki Aşamalı Doğrulamayı (2FA) aktif hale getirin.
