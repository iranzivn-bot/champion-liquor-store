<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';
requireAdmin();
requirePermission('settings.manage');
$pageTitle = lang('settings');
$success = getFlashMessage('success');
$error   = getFlashMessage('error');
require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="container-fluid p-4">

            <h2 class="fw-bold mb-4" style="color:#001F5B;">
                <i class="bi bi-gear"></i> <?= lang('settings') ?>
            </h2>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab" aria-controls="overview" aria-selected="true">Overview</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="groups-tab" data-bs-toggle="tab" data-bs-target="#groups" type="button" role="tab" aria-controls="groups" aria-selected="false">Groups</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="overview" role="tabpanel">

                    <div class="row g-4">
                        <div class="col-md-4">
                            <a href="general.php" class="text-decoration-none">
                                <div class="card shadow-sm border-0 h-100" style="border-left: 4px solid #001F5B !important;">
                                    <div class="card-body text-center py-4">
                                        <i class="bi bi-sliders2" style="font-size:2.5rem;color:#001F5B;"></i>
                                        <h5 class="mt-2 fw-bold">General</h5>
                                        <p class="text-muted small mb-0">Site name, timezone, currency</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="company.php" class="text-decoration-none">
                                <div class="card shadow-sm border-0 h-100" style="border-left: 4px solid #001F5B !important;">
                                    <div class="card-body text-center py-4">
                                        <i class="bi bi-building" style="font-size:2.5rem;color:#001F5B;"></i>
                                        <h5 class="mt-2 fw-bold">Company</h5>
                                        <p class="text-muted small mb-0">Business contact details</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="appearance.php" class="text-decoration-none">
                                <div class="card shadow-sm border-0 h-100" style="border-left: 4px solid #001F5B !important;">
                                    <div class="card-body text-center py-4">
                                        <i class="bi bi-palette" style="font-size:2.5rem;color:#001F5B;"></i>
                                        <h5 class="mt-2 fw-bold">Appearance</h5>
                                        <p class="text-muted small mb-0">Logo & favicon</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="email.php" class="text-decoration-none">
                                <div class="card shadow-sm border-0 h-100" style="border-left: 4px solid #001F5B !important;">
                                    <div class="card-body text-center py-4">
                                        <i class="bi bi-envelope" style="font-size:2.5rem;color:#001F5B;"></i>
                                        <h5 class="mt-2 fw-bold">Email</h5>
                                        <p class="text-muted small mb-0">SMTP configuration</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="orders.php" class="text-decoration-none">
                                <div class="card shadow-sm border-0 h-100" style="border-left: 4px solid #001F5B !important;">
                                    <div class="card-body text-center py-4">
                                        <i class="bi bi-cart" style="font-size:2.5rem;color:#001F5B;"></i>
                                        <h5 class="mt-2 fw-bold">Orders</h5>
                                        <p class="text-muted small mb-0">Tax, shipping, thresholds</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="security.php" class="text-decoration-none">
                                <div class="card shadow-sm border-0 h-100" style="border-left: 4px solid #001F5B !important;">
                                    <div class="card-body text-center py-4">
                                        <i class="bi bi-shield-lock" style="font-size:2.5rem;color:#001F5B;"></i>
                                        <h5 class="mt-2 fw-bold">Security</h5>
                                        <p class="text-muted small mb-0">Login & password policy</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="maintenance.php" class="text-decoration-none">
                                <div class="card shadow-sm border-0 h-100" style="border-left: 4px solid #001F5B !important;">
                                    <div class="card-body text-center py-4">
                                        <i class="bi bi-tools" style="font-size:2.5rem;color:#001F5B;"></i>
                                        <h5 class="mt-2 fw-bold">Maintenance</h5>
                                        <p class="text-muted small mb-0">Maintenance mode toggle</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="backup.php" class="text-decoration-none">
                                <div class="card shadow-sm border-0 h-100" style="border-left: 4px solid #001F5B !important;">
                                    <div class="card-body text-center py-4">
                                        <i class="bi bi-cloud-arrow-down" style="font-size:2.5rem;color:#001F5B;"></i>
                                        <h5 class="mt-2 fw-bold">Backup</h5>
                                        <p class="text-muted small mb-0">Database backups</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="system-info.php" class="text-decoration-none">
                                <div class="card shadow-sm border-0 h-100" style="border-left: 4px solid #001F5B !important;">
                                    <div class="card-body text-center py-4">
                                        <i class="bi bi-info-circle" style="font-size:2.5rem;color:#001F5B;"></i>
                                        <h5 class="mt-2 fw-bold">System Info</h5>
                                        <p class="text-muted small mb-0">PHP, MySQL, server details</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="languages.php" class="text-decoration-none">
                                <div class="card shadow-sm border-0 h-100" style="border-left: 4px solid #001F5B !important;">
                                    <div class="card-body text-center py-4">
                                        <i class="bi bi-globe2" style="font-size:2.5rem;color:#001F5B;"></i>
                                        <h5 class="mt-2 fw-bold">Languages</h5>
                                        <p class="text-muted small mb-0">Manage supported languages</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                </div>
                <div class="tab-pane fade" id="groups" role="tabpanel">

                    <?php $grouped = getGroupedSettings(); ?>
                    <?php if (empty($grouped)): ?>
                        <div class="alert alert-info">No settings found in the database.</div>
                    <?php else: ?>
                        <div class="accordion" id="settingsAccordion">
                            <?php $i = 0; foreach ($grouped as $group => $settingsList): $i++; ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button <?= $i > 1 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#group<?= $i ?>">
                                            <strong style="color:#001F5B;"><?= htmlspecialchars(ucfirst($group)) ?></strong>
                                            <span class="badge bg-secondary ms-2"><?= count($settingsList) ?></span>
                                        </button>
                                    </h2>
                                    <div id="group<?= $i ?>" class="accordion-collapse collapse <?= $i === 1 ? 'show' : '' ?>" data-bs-parent="#settingsAccordion">
                                        <div class="accordion-body">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Key</th>
                                                            <th>Value</th>
                                                            <th>Description</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($settingsList as $s): ?>
                                                            <tr>
                                                                <td><code><?= htmlspecialchars($s['setting_key']) ?></code></td>
                                                                <td><small class="text-break"><?= htmlspecialchars(mb_substr((string) $s['setting_value'], 0, 80)) ?></small></td>
                                                                <td><small class="text-muted"><?= htmlspecialchars($s['description'] ?? '') ?></small></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>

    </main>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
