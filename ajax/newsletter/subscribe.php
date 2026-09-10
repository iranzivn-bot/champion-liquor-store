<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

if (strlen($email) > 255) {
    echo json_encode(['success' => false, 'message' => 'Email address is too long.']);
    exit;
}

$pdo = getDbConnection();

try {
    $existing = $pdo->prepare("SELECT id, status FROM newsletter_subscribers WHERE email = ?");
    $existing->execute([$email]);
    $sub = $existing->fetch();

    if ($sub) {
        if ($sub['status'] === 'active') {
            echo json_encode(['success' => true, 'message' => 'You are already subscribed!']);
            exit;
        }
        $pdo->prepare("UPDATE newsletter_subscribers SET status = 'active', subscribed_at = NOW(), unsubscribed_at = NULL WHERE id = ?")->execute([$sub['id']]);
        echo json_encode(['success' => true, 'message' => 'Welcome back! You have been re-subscribed.']);
        exit;
    }

    $pdo->prepare("INSERT INTO newsletter_subscribers (email) VALUES (?)")->execute([$email]);
    echo json_encode(['success' => true, 'message' => 'Thank you for subscribing!']);

} catch (\Throwable $e) {
    error_log('Newsletter subscription error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
}
