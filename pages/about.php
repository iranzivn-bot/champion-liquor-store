<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-hero-sm">
    <div class="container">
        <h1 class="fw-bold">About Us</h1>
        <p class="mb-0 text-white-50">Learn more about <?= SITE_NAME ?></p>
    </div>
</div>

<section class="section-padding">
    <div class="container">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8 text-center">
                <h2 class="section-title">Our Story</h2>
                <p class="section-subtitle">
                    <?= SITE_NAME ?> was founded with a simple mission — to provide the highest quality beverages,
                    spirits, and supermarket essentials at affordable prices. Since our establishment, we have grown
                    into a trusted name in the industry, serving thousands of satisfied customers across Rwanda.
                </p>
                <p class="text-muted">
                    We pride ourselves on our extensive selection of products ranging from fine wines and premium
                    spirits to everyday groceries and household items. Our team is dedicated to ensuring every
                    customer receives exceptional service and finds exactly what they need.
                </p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="mission-card">
                    <i class="bi bi-bullseye"></i>
                    <h5 class="fw-bold text-primary">Our Mission</h5>
                    <p class="text-muted small mb-0">To provide premium quality products with exceptional customer service, creating a one-stop shopping experience for our community.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mission-card">
                    <i class="bi bi-eye"></i>
                    <h5 class="fw-bold text-primary">Our Vision</h5>
                    <p class="text-muted small mb-0">To be the leading supermarket and liquor store in Rwanda, known for quality, affordability, and reliability.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mission-card">
                    <i class="bi bi-heart"></i>
                    <h5 class="fw-bold text-primary">Our Values</h5>
                    <p class="text-muted small mb-0">Integrity, quality, customer focus, and community service are at the heart of everything we do.</p>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-5">
            <div class="col-6 col-md-3">
                <div class="feature-box">
                    <div class="feature-icon"><i class="bi bi-truck"></i></div>
                    <h5>Fast Delivery</h5>
                    <p>Free delivery within Kigali on orders above RWF 50,000</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="feature-box">
                    <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
                    <h5>Quality Guarantee</h5>
                    <p>100% authentic products sourced from trusted suppliers</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="feature-box">
                    <div class="feature-icon"><i class="bi bi-credit-card"></i></div>
                    <h5>Secure Payments</h5>
                    <p>Multiple secure payment options for your convenience</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="feature-box">
                    <div class="feature-icon"><i class="bi bi-headset"></i></div>
                    <h5>24/7 Support</h5>
                    <p>Dedicated customer service team ready to help anytime</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
