Bu belge, HelpMe SaaS destek ve müşteri hizmetleri platformunun geliştirme sürecindeki yapay zeka destekli prompt döngülerini, karşılaşılan teknik zorlukları, alınan mimari kararları ve uygulanan çözümleri şeffaf bir şekilde kaydetmek amacıyla oluşturulmuştur.

📅 Geliştirme Kronolojisi ve Süreç Günlüğü
1. Aşama: Mimari Planlama ve Proje Kapsamı
Karar: Karmaşık ve şişkin modern framework'ler yerine hız, güvenlik ve sürdürülebilirlik odaklı Saf PHP 8+ (Pure PHP), PDO MySQL, Bootstrap 5 ve Vanilla JavaScript teknolojileri seçildi.

Veritabanı Tasarımı: Kullanıcıların üyelik oluşturmadan talep açabilmesi ve güvenli takip kodlarıyla (HM-XXXXX) verilerine ulaşabilmesi için tickets tablosu kurgulandı.

2. Aşama: Arayüz ve Asenkron Form Mimarisi
Frontend & UX: Mobil ve masaüstü uyumlu modern bir landing page ve talep formu (public/index.php) tasarlandı.

API Entegrasyonu: Form verilerinin sayfa yenilenmeden asenkron olarak iletilmesi için api/submit_ticket.php endpoint'i yazıldı. Çakışmasız benzersiz takip kodu üretimi bu aşamada entegre edildi.

3. Aşama: Karşılıklı Mesajlaşma (Chat Thread) ve Talep Takip Modülü
Yeni Tablo Gereksinimi: Tek bir talebin altındaki yazışmaların veritabanını şişirmeden tutulabilmesi için ticket_replies tablosu eklendi (database/sql_v3.sql).

Takip Ekranı (public/track.php): Kullanıcının takip kodu ile giriş yaparak geçmiş mesajlarını ve destek ekibinin yanıtlarını kronolojik olarak görebilmesi ve aynı ekrandan yanıt yazabilmesi sağlandı. Çözülen taleplerde input alanının kilitlenmesi mekanizması kuruldu.

4. Aşama: Güvenlik, PRG Mimarisi ve Form Resubmission Çözümü
Problem: Form gönderildikten sonra tarayıcıda "Geri" tuşuna basıldığında veya sayfa yenilendiğinde formun mükerrer gönderilmesine (Form Resubmission / Back-Forward Cache) yol açan durum gözlemlendi.

Çözüm (PRG Pattern): Hem kullanıcı takip sayfasında hem de admin panelinde POST işlemlerinden sonra header('Location: ...') ile GET yönlendirmesi yapılarak tarayıcı geçmişi temizlendi ve güvenli akış sağlandı.

SQL Enjeksiyonu Koruması: Tüm veritabanı sorguları PDO Prepared Statements (prepare() / execute()) yapısına geçirildi. XSS önlemleri için htmlspecialchars süzgeci uygulandı.

5. Aşama: Dinamik Admin Paneli ve 2FA (İki Aşamalı Doğrulama) Entegrasyonu
Hardcoded Şifrelerin Kaldırılması: Kod içine gömülü sabit admin şifreleri kaldırılarak veritabanı tabanlı admins tablosu oluşturuldu. Bcrypt (password_hash / password_verify) mekanizması kuruldu.

RFC 6238 TOTP Standardı: Harici ağır kütüphanelere bağımlı kalmaksızın, saf PHP ile Google Authenticator uyumlu zaman bazlı 2FA (İki Aşamalı Doğrulama) altyapısı geliştirildi.

Yönetim Modalları: Admin panelinin içerisinden şifre değiştirme ve 2FA özelliğini açıp kapama pop-up modülleri eklendi.

Sayaç Düzeltmeleri: Admin panelindeki istatistik kartlarının (Toplam Talep, Beklemede, Yanıtlananlar, Çözülen) veritabanındaki status kolonlarıyla tam uyumlu çalışması sağlandı.

🛠️ Kullanılan Teknolojiler ve Araçlar
Backend: PHP 8.0+ (PDO, Sessions, OpenSSL)

Veritabanı: MySQL / MariaDB (utf8mb4 karakter seti)

Frontend: Bootstrap 5, Vanilla JavaScript (fetch API), CSS3

Güvenlik Standartları: Prepared Statements, PRG Pattern, Bcrypt Hashing, RFC 6238 TOTP 2FA, XSS Sanitization