<!doctype html>
<html lang="tr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
        content="HelpMe ile destek taleplerinizi tek bir yerde toplayın ve daha hızlı çözümler bulun.">
    <title>HelpMe — Daha iyi destek, daha iyi deneyim</title>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="favicon.svg">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="./helpme.css">
</head>

<body>
    <div class="helpme-site">

        <!-- ========== NAVBAR ========== -->
        <nav class="navbar navbar-expand-lg fixed-top helpme-nav" aria-label="Ana navigasyon">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center gap-2" href="#top">
                    <span class="brand-mark">
                        <i class="bi bi-chat-heart-fill"></i>
                    </span>
                    HelpMe
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Menüyü aç">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav mx-auto gap-lg-2">
                        <li class="nav-item">
                            <a class="nav-link" href="#ozellikler">Özellikler</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#nasil-calisir">Nasıl Çalışır</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#sss">SSS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-primary fw-semibold" href="track.php">
                                <i class="bi bi-search me-1"></i>Talep Sorgula
                            </a>
                        </li>
                    </ul>
                    <div class="d-flex align-items-center gap-2">
                        <a class="btn btn-outline-dark rounded-pill px-3" href="track.php">
                            <i class="bi bi-ticket-perforated me-1"></i> Talep Sorgula
                        </a>
                        <a class="btn btn-primary rounded-pill px-4" href="#destek-talebi">
                            Destek Talebi Oluştur <i class="bi bi-arrow-up-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- ========== MAIN CONTENT ========== -->
        <main id="top">

            <!-- ===== HERO SECTION ===== -->
            <section class="hero-section">
                <div class="hero-glow hero-glow-one"></div>
                <div class="hero-glow hero-glow-two"></div>
                <div class="container position-relative">
                    <div class="row align-items-center g-5">

                        <!-- Hero Text -->
                        <div class="col-lg-7">
                            <div class="eyebrow">
                                <span class="eyebrow-dot"></span> Destek artık daha kolay
                            </div>
                            <h1>Sorunlarınızı <span>çözüme</span> dönüştürün.</h1>
                            <p class="hero-copy">
                                HelpMe ile destek taleplerinizi tek bir yerde toplayın, ekibinizle daha
                                hızlı iletişim kurun ve işinizi kesintisiz sürdürün.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="#destek-talebi" class="btn btn-primary btn-lg rounded-pill px-4">
                                    Talep oluştur <i class="bi bi-arrow-right ms-2"></i>
                                </a>
                                <a href="#nasil-calisir" class="btn btn-outline-dark btn-lg rounded-pill px-4">
                                    Nasıl çalışır? <i class="bi bi-play-circle ms-2"></i>
                                </a>
                            </div>
                            <div class="hero-proof">
                                <div class="avatar-stack">
                                    <span>AK</span>
                                    <span>ED</span>
                                    <span>MK</span>
                                    <span class="avatar-more">+2k</span>
                                </div>
                                <div>
                                    <strong>2.000+ kişi</strong>
                                    <small>HelpMe ile daha iyi destek alıyor</small>
                                </div>
                            </div>
                        </div>

                        <!-- Hero Card -->
                        <div class="col-lg-5">
                            <div class="hero-card">
                                <div class="hero-card-top">
                                    <div>
                                        <span class="mini-label">Destek merkezi</span>
                                        <h2>Size nasıl yardımcı olabiliriz?</h2>
                                    </div>
                                    <span class="online-dot"></span>
                                </div>
                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <span>Bir konu arayın...</span>
                                    <kbd>⌘ K</kbd>
                                </div>
                                <div class="quick-grid">
                                    <div>
                                        <i class="bi bi-bug-fill"></i>
                                        <span>Hata bildir</span>
                                        <small>Ortalama 2 saatte</small>
                                    </div>
                                    <div>
                                        <i class="bi bi-lightbulb-fill"></i>
                                        <span>Fikir paylaş</span>
                                        <small>Ürünümüzü geliştirin</small>
                                    </div>
                                    <div>
                                        <i class="bi bi-question-circle-fill"></i>
                                        <span>Soru sor</span>
                                        <small>Uzmanlara ulaşın</small>
                                    </div>
                                    <div>
                                        <i class="bi bi-book-fill"></i>
                                        <span>Bilgi bankası</span>
                                        <small>Yanıtları keşfedin</small>
                                    </div>
                                </div>
                                <div class="response-badge">
                                    <span class="pulse-dot"></span>
                                    <span>Destek ekibi çevrimiçi</span>
                                    <strong>~ 5 dk</strong>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </section>

            <!-- ===== ÖZELLİKLER SECTION ===== -->
            <section class="section-padding" id="ozellikler">
                <div class="container">
                    <div class="section-heading text-center">
                        <div class="eyebrow justify-content-center">Neden HelpMe?</div>
                        <h2>Destek sürecinin her adımında <em>yanınızdayız.</em></h2>
                        <p>İyi bir destek deneyimi, iyi bir ürün deneyiminin temelidir.</p>
                    </div>
                    <div class="row g-4 mt-3">
                        <div class="col-md-4">
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                </div>
                                <h3>Hızlı çözüm</h3>
                                <p>Ekibinizin ihtiyaç duyduğu cevaplara ve desteğe tek bir yerden ulaşın.</p>
                                <a href="#destek-talebi">Daha fazla keşfet <i class="bi bi-arrow-up-right"></i></a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="bi bi-diagram-3-fill"></i>
                                </div>
                                <h3>Düzenli iş akışı</h3>
                                <p>Tüm talepleri kategorilere ayırın, önceliklendirin ve kolayca takip edin.</p>
                                <a href="#destek-talebi">Daha fazla keşfet <i class="bi bi-arrow-up-right"></i></a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="bi bi-bar-chart-fill"></i>
                                </div>
                                <h3>Daha iyi içgörüler</h3>
                                <p>Destek deneyiminizi gerçek veriler ve net raporlarla sürekli geliştirin.</p>
                                <a href="#destek-talebi">Daha fazla keşfet <i class="bi bi-arrow-up-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== NASIL ÇALIŞIR SECTION ===== -->
            <section class="process-section section-padding" id="nasil-calisir">
                <div class="container">
                    <div class="row align-items-end mb-5">
                        <div class="col-lg-7">
                            <div class="eyebrow">Basit ve şeffaf</div>
                            <h2>Üç adımda <em>yardım</em> elinizin altında.</h2>
                        </div>
                        <div class="col-lg-4 ms-auto">
                            <p class="mb-0 muted-copy">
                                İhtiyacınız olan desteğe ulaşmak için karmaşık süreçlerle uğraşmanıza gerek yok.
                            </p>
                        </div>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="step-card">
                                <div class="step-number">01</div>
                                <div class="step-line"></div>
                                <h3>Talebinizi oluşturun</h3>
                                <p>Sorununuzu veya fikrinizi birkaç cümleyle bize iletin.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="step-card">
                                <div class="step-number">02</div>
                                <div class="step-line"></div>
                                <h3>Ekibimiz incelesin</h3>
                                <p>Talebiniz doğru ekibe yönlendirilir ve hızla değerlendirilir.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="step-card">
                                <div class="step-number">03</div>
                                <div class="step-line"></div>
                                <h3>Çözümü birlikte bulun</h3>
                                <p>Gelişmeleri takip edin, sorularınızı yanıtlayın ve sonuca ulaşın.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== DESTEK TALEBİ FORMU SECTION ===== -->
            <section class="request-section section-padding" id="destek-talebi">
                <div class="container">
                    <div class="row g-5 align-items-start">

                        <!-- Sol: Açıklama -->
                        <div class="col-lg-5">
                            <div class="eyebrow">Buradayız</div>
                            <h2>Birlikte <em>çözelim.</em></h2>
                            <p class="muted-copy">
                                Sorunuz, öneriniz veya karşılaştığınız bir hata mı var? Bize yazın, en
                                kısa sürede size dönüş yapalım.
                            </p>
                            <div class="contact-note">
                                <i class="bi bi-clock-history"></i>
                                <div>
                                    <strong>Genellikle 2 saat içinde yanıtlıyoruz.</strong>
                                    <span>Pazartesi - Cuma, 09:00 - 18:00</span>
                                </div>
                            </div>
                        </div>

                        <!-- Sağ: Form -->
                        <div class="col-lg-6 ms-auto">
                            <form class="request-form" id="supportForm" novalidate>

                                <!-- Ad Soyad -->
                                <div class="mb-3">
                                    <label for="name" class="form-label">Ad Soyad</label>
                                    <input id="name" type="text" class="form-control" required
                                        placeholder="Adınızı yazın">
                                    <div class="invalid-feedback">Lütfen adınızı ve soyadınızı giriniz.</div>
                                </div>

                                <!-- E-posta -->
                                <div class="mb-3">
                                    <label for="email" class="form-label">E-posta</label>
                                    <input id="email" type="email" class="form-control" required
                                        placeholder="ornek@eposta.com">
                                    <div class="invalid-feedback">Lütfen geçerli bir e-posta adresi giriniz.</div>
                                </div>

                                <!-- Talep Türü -->
                                <div class="mb-3">
                                    <label for="type" class="form-label">Talep Türü</label>
                                    <select id="type" class="form-select" required>
                                        <option value="" selected disabled>Talep türünü seçin</option>
                                        <option>Hata Bildirimi</option>
                                        <option>Özellik İsteği</option>
                                        <option>Teknik Soru</option>
                                    </select>
                                    <div class="invalid-feedback">Lütfen bir talep türü seçiniz.</div>
                                </div>

                                <!-- Detaylı Açıklama -->
                                <div class="mb-3">
                                    <label for="detail" class="form-label">Detaylı Açıklama</label>
                                    <textarea id="detail" class="form-control" required rows="5"
                                        placeholder="Size nasıl yardımcı olabiliriz?"></textarea>
                                    <div class="invalid-feedback">Lütfen en az 10 karakterden oluşan bir mesaj yazınız.</div>
                                </div>

                                <!-- Gönder Butonu -->
                                <div>
                                    <button class="btn btn-primary rounded-pill w-100 py-3" type="submit">
                                        Gönder <i class="bi bi-arrow-right ms-2"></i>
                                    </button>
                                </div>

                                <div id="formStatus" class="mt-3" role="status" aria-live="polite"></div>
                            </form>
                        </div>

                    </div>
                </div>
            </section>

            <!-- ===== SSS SECTION ===== -->
            <section class="faq-section section-padding" id="sss">
                <div class="container">
                    <div class="section-heading text-center">
                        <div class="eyebrow justify-content-center">Merak ettikleriniz</div>
                        <h2>Sıkça sorulan <em>sorular.</em></h2>
                    </div>
                    <div class="accordion accordion-flush faq-list" id="faqAccordion">
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faqOne">
                                    Destek talebime ne kadar sürede yanıt alırım?
                                </button>
                            </h3>
                            <div id="faqOne" class="accordion-collapse collapse show"
                                data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Taleplerin çoğuna çalışma saatleri içinde ortalama 2 saat içerisinde
                                    yanıt veriyoruz. Acil durumlarda talebinizi önceliklendiriyoruz.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#faqTwo">
                                    Talebimin durumunu nasıl takip edebilirim?
                                </button>
                            </h3>
                            <div id="faqTwo" class="accordion-collapse collapse"
                                data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Talebiniz oluşturulduktan sonra e-posta adresinize bir takip
                                    bağlantısı gönderilir.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#faqThree">
                                    HelpMe'yi kullanmak ücretli mi?
                                </button>
                            </h3>
                            <div id="faqThree" class="accordion-collapse collapse"
                                data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Destek talebi oluşturmak tüm kullanıcılarımız için ücretsizdir.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        </main>

        <!-- ========== FOOTER ========== -->
        <footer class="site-footer">
            <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <a class="navbar-brand d-flex align-items-center gap-2" href="#top">
                    <span class="brand-mark">
                        <i class="bi bi-chat-heart-fill"></i>
                    </span>
                    HelpMe
                </a>
                <p class="mb-0">© 2026 HelpMe. Designed by <a href="https://instagram.com/yigit.calgan" target="_blank">YUC</a></p>
                <div class="footer-links">
                    <a href="track.php"><i class="bi bi-search me-1"></i>Talep Sorgula</a>
                    <a href="../admin/admin.php"><i class="bi bi-shield-lock me-1"></i>Yönetici Paneli</a>
                    <a href="#sss">SSS</a>
                    <a href="#destek-talebi">İletişim</a>
                </div>
            </div>
        </footer>

    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../Js/app.js?v=3.5"></script>
</body>

</html>
