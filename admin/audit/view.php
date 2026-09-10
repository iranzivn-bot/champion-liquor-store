<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/constants.php';

requireAdmin();

$pageTitle = 'Audit Log Details';

$pdo = getDbConnection();
$id  = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE id = :id");
$stmt->execute([':id' => $id]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    setFlashMessage('error', 'Audit record not found.');
    redirect(SITE_URL . 'admin/audit/index.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<style>
.detail-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); padding: 1.5rem; margin-bottom: 1.5rem; }
.detail-card h5 { color: #001F5B; font-weight: 700; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #F0F0F0; }
.detail-label { font-size: 0.82rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 0.2rem; }
.detail-value { font-size: 1rem; font-weight: 500; color: #333; margin-bottom: 1rem; }
.detail-value pre { background: #F5F6FA; padding: 0.75rem; border-radius: 6px; font-size: 0.85rem; white-space: pre-wrap; word-break: break-word; }
.back-link { font-size: 0.9rem; }
</style>

<div class="admin-content-wrapper">
<main class="admin-content">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="<?= SITE_URL ?>admin/audit/index.php" class="back-link text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Back to Audit Logs
            </a>
            <h1 class="h3 mb-0 mt-1" style="color:#001F5B;">Audit Log Details</h1>
        </div>
        <div>
            <span class="badge bg-light text-dark border px-3 py-2" style="font-size:0.85rem;">
                <i class="bi bi-hash me-1"></i> #<?= (int) $log['id'] ?>
            </span>
        </div>
    </div>

    <div class="row g-4">

        <div class="col-md-6">
            <div class="detail-card">
                <h5><i class="bi bi-person-circle me-2"></i> User Information</h5>
                <div class="detail-label">Name</div>
                <div class="detail-value"><?= htmlspecialchars($log['user_name'] ?: '—') ?></div>

                <div class="detail-label">User ID</div>
                <div class="detail-value"><?= $log['user_id'] ? '#' . (int) $log['user_id'] : '— (deleted)' ?></div>

                <div class="detail-label">Role</div>
                <div class="detail-value">
                    <?php if ($log['user_role'] === 'admin'): ?>
                        <span class="badge bg-dark">Admin</span>
                    <?php elseif ($log['user_role'] === 'customer'): ?>
                        <span class="badge bg-primary">Customer</span>
                    <?php else: ?>
                        <span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($log['user_role'] ?: '—')) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="detail-card">
                <h5><i class="bi bi-info-circle me-2"></i> Action Details</h5>
                <div class="detail-label">Module</div>
                <div class="detail-value"><?= htmlspecialchars(ucfirst($log['module'])) ?></div>

                <div class="detail-label">Action</div>
                <div class="detail-value">
                    <?php
                    $badgeClass = 'bg-secondary';
                    if ($log['action'] === 'created' || $log['action'] === 'login') $badgeClass = 'bg-success';
                    elseif ($log['action'] === 'updated' || $log['action'] === 'password_changed') $badgeClass = 'bg-info text-dark';
                    elseif ($log['action'] === 'deleted' || $log['action'] === 'logout') $badgeClass = 'bg-danger';
                    elseif ($log['action'] === 'cancelled' || $log['action'] === 'login_failed') $badgeClass = 'bg-warning text-dark';
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action']))) ?></span>
                </div>

                <div class="detail-label">Reference ID</div>
                <div class="detail-value"><code><?= htmlspecialchars($log['reference_id'] ?? '—') ?></code></div>
            </div>
        </div>

        <div class="col-12">
            <div class="detail-card">
                <h5><i class="bi bi-chat-square-text me-2"></i> Description</h5>
                <div class="detail-value">
                    <pre><?= htmlspecialchars($log['description'] ?? 'No description provided.') ?></pre>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="detail-card">
                <h5><i class="bi bi-globe me-2"></i> Request Details</h5>
                <div class="detail-label">IP Address</div>
                <div class="detail-value"><code><?= htmlspecialchars($log['ip_address'] ?: '—') ?></code></div>

                <div class="detail-label">Browser / User Agent</div>
                <div class="detail-value" style="font-size:0.85rem; word-break: break-word; color: #6B7280;">
                    <?= htmlspecialchars($log['user_agent'] ?: '—') ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="detail-card">
                <h5><i class="bi bi-clock me-2"></i> Date & Time</h5>
                <div class="detail-label">Date</div>
                <div class="detail-value"><?= date('F j, Y', strtotime($log['created_at'])) ?></div>

                <div class="detail-label">Time</div>
                <div class="detail-value"><?= date('h:i:s A', strtotime($log['created_at'])) ?></div>

                <div class="detail-label">Timestamp</div>
                <div class="detail-value"><code><?= htmlspecialchars($log['created_at']) ?></code></div>
            </div>
        </div>

    </div>

    <div class="mt-3">
        <a href="<?= SITE_URL ?>admin/audit/index.php" class="btn btn-outline-secondary fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Back to Audit Logs
        </a>
    </div>

</main>
</div>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
