<footer class="footer-section" aria-label="Site footer">
    <div class="footer-top-border"></div>
    <div class="container">
        <div class="row g-5">
            <!-- Brand Info -->
            <div class="col-lg-4 col-md-6">
                <div class="footer-brand">
                    <h5 class="footer-heading">
                        <?php $footerLogo = setting('site_logo_icon', ''); if ($footerLogo): ?>
                            <img src="<?= SITE_URL ?>uploads/settings/<?= htmlspecialchars($footerLogo) ?>"
                                 alt="<?= SITE_NAME ?>" class="footer-logo-img">
                        <?php else: ?>
                            <i class="bi bi-shop"></i>
                        <?php endif; ?>
                        <?= SITE_NAME ?>
                    </h5>
                    <p class="footer-about"><?= lang('footer_about') ?></p>
                    <div class="footer-social-row">
                        <a href="#" class="footer-social-icon" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="footer-social-icon" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="footer-social-icon" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        <a href="#" class="footer-social-icon" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6">
                <h6 class="footer-heading-sm"><?= lang('quick_links') ?></h6>
                <ul class="footer-links">
                    <li><a href="<?= SITE_URL ?>"><?= lang('home') ?></a></li>
                    <li><a href="<?= SITE_URL ?>pages/shop.php"><?= lang('shop') ?></a></li>
                    <li><a href="<?= SITE_URL ?>pages/about.php"><?= lang('about') ?></a></li>
                    <li><a href="<?= SITE_URL ?>pages/contact.php"><?= lang('contact') ?></a></li>
                </ul>
            </div>

            <!-- Customer Service -->
            <div class="col-lg-2 col-md-6">
                <h6 class="footer-heading-sm"><?= lang('customer_service') ?></h6>
                <ul class="footer-links">
                    <li><a href="#"><?= lang('faq') ?></a></li>
                    <li><a href="#"><?= lang('privacy_policy') ?></a></li>
                    <li><a href="#"><?= lang('terms_conditions') ?></a></li>
                    <li><a href="#"><?= lang('help_center') ?></a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div class="col-lg-4 col-md-6">
                <h6 class="footer-heading-sm"><?= lang('contact') ?></h6>
                <ul class="footer-contact">
                    <li><i class="bi bi-telephone"></i> <?= htmlspecialchars(setting('company_phone', '+250 784 266 545')) ?></li>
                    <li><i class="bi bi-envelope"></i> <?= htmlspecialchars(setting('company_email', 'info@championliquorstore.com')) ?></li>
                    <li><i class="bi bi-geo-alt"></i> <?= htmlspecialchars(setting('company_address', 'Kicukiro-Niboyi, Kigali, Rwanda')) ?></li>
                    <li><i class="bi bi-clock"></i> Mon–Sat: 8:00 AM – 9:00 PM</li>
                </ul>
            </div>
        </div>

        <div class="footer-divider"></div>

        <div class="footer-bottom">
            <p class="footer-copy"><?= lang('footer_copyright', ['year' => date('Y')]) ?></p>
            <div class="footer-payments">
                <span class="footer-payment-badge"><i class="bi bi-credit-card"></i> Visa</span>
                <span class="footer-payment-badge"><i class="bi bi-credit-card-2-front"></i> Mastercard</span>
                <span class="footer-payment-badge"><i class="bi bi-currency-dollar"></i> Mobile Money</span>
                <span class="footer-payment-badge"><i class="bi bi-paypal"></i> PayPal</span>
            </div>
        </div>
    </div>

    <button class="footer-back-top" id="footerBackTop" aria-label="Back to top">
        <i class="bi bi-chevron-up"></i>
    </button>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
window.SITE_URL = '<?= SITE_URL ?>';
window.CSRF_TOKEN = '<?= generateCSRFToken() ?>';
window.CURRENT_LANG = '<?= getCurrentLanguage() ?>';
</script>
<script src="<?= SITE_URL ?>assets/js/script.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.barcode-display').forEach(function (el) {
        var barcode = el.getAttribute('data-barcode');
        var svg = el.querySelector('.barcode-svg');
        if (barcode && svg && typeof JsBarcode !== 'undefined') {
            try {
                JsBarcode(svg, barcode, {
                    format: 'CODE128',
                    width: 1.5,
                    height: 40,
                    displayValue: false,
                    background: 'transparent',
                    margin: 0
                });
            } catch (e) {}
        }
    });

    // Flash sale countdown timer
    var flashCountdown = document.querySelector('.flash-countdown');
    if (flashCountdown) {
        var endTime = new Date(flashCountdown.getAttribute('data-end')).getTime();
        function updateCountdown() {
            var now = new Date().getTime();
            var diff = endTime - now;
            if (diff <= 0) {
                flashCountdown.innerHTML = '<span class="flash-sale-ended">Sale Ended</span>';
                return;
            }
            var days = Math.floor(diff / (1000 * 60 * 60 * 24));
            var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            var secs = Math.floor((diff % (1000 * 60)) / 1000);
            document.getElementById('flash-days').textContent = String(days).padStart(2, '0');
            document.getElementById('flash-hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('flash-mins').textContent = String(mins).padStart(2, '0');
            document.getElementById('flash-secs').textContent = String(secs).padStart(2, '0');
        }
        updateCountdown();
        setInterval(updateCountdown, 1000);
    }

    // Back to top button
    var backTop = document.getElementById('footerBackTop');
    if (backTop) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 400) {
                backTop.classList.add('show');
            } else {
                backTop.classList.remove('show');
            }
        });
        backTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
});
</script>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?= SITE_URL ?>service-worker.js').then(function(reg) {
            console.log('SW registered: ' + reg.scope);
        }).catch(function(err) {
            console.log('SW registration failed: ' + err);
        });
    });
}
</script>
</body>
</html>
