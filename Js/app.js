/**
 * ============================================================
 *  HelpMe — Frontend Form → API Entegrasyonu & İstemci Doğrulaması
 * ============================================================
 *
 *  Destek formunu submit_ticket.php API endpoint'ine bağlar.
 *  İstemci tarafında (Client-side) Bootstrap 5 standartlarında
 *  validasyon uygular, hatalı girişlerde fetch isteğini engeller
 *  ve arayüzde anlık hata geri bildirimleri sunar.
 *
 *  @file  Js/app.js
 * ============================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    const form   = document.getElementById('supportForm');
    const status = document.getElementById('formStatus');

    if (!form || !status) return;

    // API endpoint URL'i (Yerel ve Canlı sunucu ortamlarına otomatik uyum sağlar)
    const getApiUrl = () => {
        const path = window.location.pathname;
        if (path.includes('/public/')) {
            return '../api/submit_ticket.php';
        }
        if (path.includes('/HelpMeProject/')) {
            return '/HelpMeProject/api/submit_ticket.php';
        }
        return '/api/submit_ticket.php';
    };
    const API_URL = getApiUrl();

    // Form Elemanları
    const nameInput    = document.getElementById('name');
    const emailInput   = document.getElementById('email');
    const typeSelect   = document.getElementById('type');
    const detailInput  = document.getElementById('detail');

    // E-posta Doğrulama Regex'i (RFC uyumlu standart desen)
    const EMAIL_REGEX = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

    // Minimum mesaj uzunluğu kuralı
    const MIN_MESSAGE_LENGTH = 10;

    /**
     * Bir form elemanına hata durumu ve mesajı atar.
     *
     * @param {HTMLElement} element 
     * @param {string} message 
     */
    function setFieldInvalid(element, message) {
        if (!element) return;
        element.classList.add('is-invalid');
        element.classList.remove('is-valid');

        // İlgili invalid-feedback kapsayıcısını bul ve mesajı güncelle
        const feedback = element.parentElement ? element.parentElement.querySelector('.invalid-feedback') : null;
        if (feedback) {
            feedback.textContent = message;
        }
    }

    /**
     * Bir form elemanındaki hata durumunu temizler.
     *
     * @param {HTMLElement} element 
     */
    function setFieldValid(element) {
        if (!element) return;
        element.classList.remove('is-invalid');
    }

    /**
     * Tüm form elemanlarındaki doğrulama durumlarını temizler.
     */
    function clearValidationStates() {
        [nameInput, emailInput, typeSelect, detailInput].forEach(el => {
            if (el) {
                el.classList.remove('is-invalid');
                el.classList.remove('is-valid');
            }
        });
    }

    // Kullanıcı alanlarda değişiklik yaptıkça hatayı anlık olarak temizle
    [nameInput, emailInput, detailInput].forEach(el => {
        if (el) {
            el.addEventListener('input', function () {
                if (el.classList.contains('is-invalid')) {
                    setFieldValid(el);
                }
            });
        }
    });

    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            if (typeSelect.classList.contains('is-invalid')) {
                setFieldValid(typeSelect);
            }
        });
    }

    /**
     * Formu istemci tarafında kurallara göre doğrular.
     *
     * @returns {boolean} - Form geçerli ise true, hata varsa false
     */
    function validateForm() {
        let isValid = true;
        let firstInvalidField = null;

        // 1. Ad Soyad Kontrolü (fullname)
        const fullnameValue = nameInput ? nameInput.value.trim() : '';
        if (fullnameValue === '') {
            setFieldInvalid(nameInput, 'Lütfen adınızı ve soyadınızı giriniz.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = nameInput;
        } else if (fullnameValue.length < 3) {
            setFieldInvalid(nameInput, 'Ad Soyad en az 3 karakter olmalıdır.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = nameInput;
        } else {
            setFieldValid(nameInput);
        }

        // 2. E-posta Kontrolü (email regex)
        const emailValue = emailInput ? emailInput.value.trim() : '';
        if (emailValue === '') {
            setFieldInvalid(emailInput, 'Lütfen e-posta adresinizi giriniz.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = emailInput;
        } else if (!EMAIL_REGEX.test(emailValue)) {
            setFieldInvalid(emailInput, 'Lütfen geçerli bir e-posta formatı giriniz (örn: ornek@eposta.com).');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = emailInput;
        } else {
            setFieldValid(emailInput);
        }

        // 3. Talep Türü Kontrolü (service_type)
        const typeValue = typeSelect ? typeSelect.value.trim() : '';
        if (!typeValue || typeValue === '') {
            setFieldInvalid(typeSelect, 'Lütfen bir talep türü seçiniz.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = typeSelect;
        } else {
            setFieldValid(typeSelect);
        }

        // 4. Detaylı Açıklama / Mesaj Kontrolü (message)
        const messageValue = detailInput ? detailInput.value.trim() : '';
        if (messageValue === '') {
            setFieldInvalid(detailInput, 'Lütfen talebinizi açıklayan bir mesaj yazınız.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = detailInput;
        } else if (messageValue.length < MIN_MESSAGE_LENGTH) {
            setFieldInvalid(
                detailInput,
                `Mesajınız en az ${MIN_MESSAGE_LENGTH} karakter olmalıdır (Şu an: ${messageValue.length} karakter).`
            );
            isValid = false;
            if (!firstInvalidField) firstInvalidField = detailInput;
        } else {
            setFieldValid(detailInput);
        }

        // Hata varsa ilk hatalı alana odaklan
        if (!isValid && firstInvalidField) {
            firstInvalidField.focus();
        }

        return isValid;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        // — Önceki durum mesajını temizle —
        status.innerHTML = '';

        // — İstemci Tarafı Doğrulaması (Client-Side Validation) —
        const isFormValid = validateForm();

        if (!isFormValid) {
            showStatus('Lütfen formdaki işaretli alanları kontrol edip eksiksiz doldurunuz.', 'danger');
            return; // Asenkron fetch gönderimini engelle
        }

        // — Buton durumu —
        const button       = form.querySelector('button[type="submit"]');
        const originalText = button.innerHTML;
        button.disabled    = true;
        button.innerHTML   = '<span class="spinner-border spinner-border-sm me-2"></span>Gönderiliyor...';

        // — Form verilerini topla —
        const payload = {
            fullname:     nameInput.value.trim(),
            email:        emailInput.value.trim(),
            service_type: typeSelect.value.trim(),
            message:      detailInput.value.trim(),
        };

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });

            let result;
            try {
                result = await response.json();
            } catch (jsonErr) {
                throw new Error(`Sunucu beklenmeyen bir yanıt verdi (HTTP ${response.status}). Endpoint: ${API_URL}`);
            }

            if (response.ok && result.status === 'success') {
                // ── Başarılı ──
                showStatus(
                    `<div class="d-flex align-items-center gap-2 mb-2">` +
                    `<i class="bi bi-check-circle-fill text-success fs-5"></i>` +
                    `<strong class="fs-6 text-dark">${result.message}</strong>` +
                    `</div>` +
                    `<div class="p-3 bg-white rounded-3 border border-success-subtle shadow-sm my-2 text-dark">` +
                    `<div class="d-flex flex-wrap align-items-center justify-content-between gap-2">` +
                    `<div>` +
                    `<span class="text-muted small d-block">Takip Kodunuz:</span>` +
                    `<code class="fs-5 fw-bold text-primary">${result.tracking_code}</code>` +
                    `</div>` +
                    `<button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1 fw-semibold" title="Takip Kodunu Kopyala" onclick="navigator.clipboard.writeText('${result.tracking_code}'); this.innerHTML='<svg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'14\\' height=\\'14\\' fill=\\'currentColor\\' viewBox=\\'0 0 16 16\\'><path d=\\'M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0\\'/></svg> Kopyalandı!';">` +
                    `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1z"/></svg> Kodu Kopyala` +
                    `</button>` +
                    `</div>` +
                    `<div class="small text-danger fw-semibold mt-2 pt-2 border-top">` +
                    `<i class="bi bi-exclamation-triangle-fill me-1"></i>Lütfen takip kodunuzu kaybetmeyin!` +
                    `</div>` +
                    `</div>` +
                    `<div class="mt-2">` +
                    `<a href="track.php?code=${encodeURIComponent(result.tracking_code)}" class="btn btn-sm btn-success rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1">` +
                    `<i class="bi bi-chat-dots-fill"></i> Talebi ve Mesajları Takip Et &rarr;` +
                    `</a>` +
                    `</div>`,
                    'success'
                );
                form.reset();
                clearValidationStates();
            } else {
                // ── API hata yanıtı ──
                showStatus(result.message || 'Bir hata oluştu.', 'danger');
            }
        } catch (error) {
            // ── Ağ / bağlantı hatası ──
            console.error('[HelpMe]', error);
            showStatus(
                error.message || 'Sunucuya bağlanılamadı. Lütfen internet bağlantınızı kontrol edip tekrar deneyin.',
                'danger'
            );
        } finally {
            // — Butonu eski haline getir —
            button.disabled  = false;
            button.innerHTML = originalText;
        }
    });

    /**
     * Kullanıcıya Bootstrap alert gösterir.
     *
     * @param {string} message  - Gösterilecek mesaj (HTML olabilir)
     * @param {string} type     - Bootstrap alert tipi (success, danger, info)
     */
    function showStatus(message, type) {
        status.innerHTML = `<div class="alert alert-${type} mb-0">${message}</div>`;
    }
});
