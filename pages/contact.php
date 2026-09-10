<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
    }

    if (!$error) {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)');
            $stmt->execute([$name, $email, $message]);
            $sent = true;
        } catch (\Throwable $e) {
            error_log('Contact form error: ' . $e->getMessage());
            $error = 'Failed to send message. Please try again later.';
        }
    }
    }
}
?>

<div class="page-hero-sm">
    <div class="container">
        <h1 class="fw-bold">Contact Us</h1>
        <p class="mb-0 text-white-50">We'd love to hear from you</p>
    </div>
</div>

<section class="section-padding">
    <div class="container">
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="contact-info-card">
                    <i class="bi bi-geo-alt"></i>
                    <h6 class="fw-bold text-primary">Address</h6>
                    <p class="text-muted small mb-0">Kigali, Kicukiro-niboye, Rwanda</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="contact-info-card">
                    <i class="bi bi-telephone"></i>
                    <h6 class="fw-bold text-primary">Phone</h6>
                    <p class="text-muted small mb-0">+250 784 266 545</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="contact-info-card">
                    <i class="bi bi-envelope"></i>
                    <h6 class="fw-bold text-primary">Email</h6>
                    <p class="text-muted small mb-0">info@championliquorstore.com</p>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="auth-card">
                    <h4 class="fw-bold mb-1 text-primary">Send Us a Message</h4>
                    <p class="text-muted small mb-4">We typically reply within 24 hours.</p>

                    <?php if ($sent): ?>
                        <div class="alert alert-success">Your message has been sent. We will get back to you shortly.</div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Your Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Message</label>
                            <textarea name="message" class="form-control" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-gold w-100"><i class="bi bi-send me-2"></i>Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding pt-0">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3987.5!2d30.1071264!3d-1.9843227!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x19dca772b1f61063%3A0xca1162a6debf77cd!2sChampion+Liquor+Store!5e0!3m2!1sen!2srw!4v1"
                    width="100%" height="400" style="border:0; border-radius: 12px;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
