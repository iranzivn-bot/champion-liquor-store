<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();

$pageTitle = 'Create Product';

$uploadDir = PRODUCT_UPLOAD_PATH;

$pdo = getDbConnection();

$categories = $pdo->query('SELECT id, code, name FROM categories ORDER BY name ASC')->fetchAll();
$brands     = $pdo->query('SELECT id, code, name FROM brands ORDER BY name ASC')->fetchAll();

$category_id    = 0;
$brand_id       = 0;
$name           = '';
$slug           = '';
$price          = '';
$discount_price = '';
$stock_quantity = 0;
$barcode        = '';
$status         = ACTIVE_STATUS;
$errors         = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page.';
    }

    if (count($errors) === 0) {
    $category_id    = (int) ($_POST['category_id'] ?? 0);
    $brand_id       = (int) ($_POST['brand_id'] ?? 0);
    $name           = trim($_POST['name']           ?? '');
    $slug           = trim($_POST['slug']           ?? '');
    $price          = trim($_POST['price']          ?? '');
    $discount_price = trim($_POST['discount_price'] ?? '');
    $stock_quantity = (int) ($_POST['stock_quantity'] ?? 0);
    $barcode        = trim($_POST['barcode'] ?? '');
    $status         = $_POST['status'] ?? ACTIVE_STATUS;

    if ($category_id <= 0) {
        $errors[] = 'Please select a category.';
    }
    if ($name === '') {
        $errors[] = 'Product name is required.';
    }
    if ($slug === '') {
        $errors[] = 'Slug is required.';
    }
    if ($price === '' || !is_numeric($price) || (float) $price < 0) {
        $errors[] = 'A valid price is required.';
    }
    if ($discount_price !== '' && (!is_numeric($discount_price) || (float) $discount_price < 0)) {
        $errors[] = 'Discount price must be a valid positive number.';
    }
    if ($discount_price !== '' && is_numeric($discount_price) && is_numeric($price) && (float) $discount_price >= (float) $price) {
        $errors[] = 'Discount price must be less than the regular price.';
    }
    if ($stock_quantity < 0) {
        $errors[] = 'Stock quantity cannot be negative.';
    }
    if ($barcode !== '' && strlen($barcode) > 50) {
        $errors[] = 'Barcode must be 50 characters or less.';
    }
    if ($barcode !== '') {
        $check = $pdo->prepare('SELECT COUNT(*) FROM products WHERE barcode = :barcode');
        $check->execute([':barcode' => $barcode]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'A product with this barcode already exists.';
        }
    }
    if (!in_array($status, [ACTIVE_STATUS, INACTIVE_STATUS], true)) {
        $status = ACTIVE_STATUS;
    }

    if ($name !== '') {
        $check = $pdo->prepare('SELECT COUNT(*) FROM products WHERE name = :name');
        $check->execute([':name' => $name]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'A product with this name already exists.';
        }
    }

    if ($slug !== '') {
        $check = $pdo->prepare('SELECT COUNT(*) FROM products WHERE slug = :slug');
        $check->execute([':slug' => $slug]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'A product with this slug already exists.';
        }
    }

    $imageName = null;
    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowedExts  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxFileSize  = 2 * 1024 * 1024;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed with error code ' . $file['error'] . '.';
        } elseif (!in_array($mimeType, $allowedTypes, true)) {
            $errors[] = 'Image must be JPEG, PNG, GIF, or WebP (detected: ' . htmlspecialchars($mimeType) . ').';
        } elseif (!in_array($ext, $allowedExts, true)) {
            $errors[] = 'Image file extension must be jpg, jpeg, png, gif, or webp.';
        } elseif ($file['size'] > $maxFileSize) {
            $errors[] = 'Image must be smaller than 2 MB.';
        } else {
            $imageName = generateUniqueFilename($file['name']);
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $imageName)) {
                $errors[] = 'Failed to move uploaded file.';
                $imageName = null;
            }
        }
    }

    if (count($errors) === 0) {
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(code, 5) AS UNSIGNED)) AS max_num FROM products");
        $row = $stmt->fetch();
        $nextNum = ($row['max_num'] ?? 0) + 1;
        $code = 'PRD-' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare('
            INSERT INTO products (code, category_id, brand_id, name, slug, price, discount_price, stock_quantity, image, barcode, status)
            VALUES (:code, :category_id, :brand_id, :name, :slug, :price, :discount_price, :stock_quantity, :image, :barcode, :status)
        ');
        $stmt->execute([
            ':code'           => $code,
            ':category_id'    => $category_id,
            ':brand_id'       => $brand_id > 0 ? $brand_id : null,
            ':name'           => $name,
            ':slug'           => $slug,
            ':price'          => $price,
            ':discount_price' => $discount_price !== '' ? $discount_price : null,
            ':stock_quantity' => $stock_quantity,
            ':image'          => $imageName,
            ':barcode'        => $barcode ?: null,
            ':status'         => $status,
        ]);

        $newProductId = (int) $pdo->lastInsertId();
        logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'products', 'created', $code, 'Created product: ' . $name . ' (' . $code . ')');

        setFlashMessage('success', 'Product "' . htmlspecialchars($name) . ' (' . $code . ')" created successfully.');
        redirect(SITE_URL . 'admin/products/index.php');
    }
    }
}

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="admin-card">
            <h1>Create Product</h1>
            <p class="text-muted">Fill in the fields below to add a new product.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $category_id === (int) $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['code'] . ' — ' . $cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="brand_id" class="form-label">Brand <span class="text-muted fw-normal small">(recommended)</span></label>
                        <select class="form-select" id="brand_id" name="brand_id">
                            <option value="">-- Select Brand --</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= $brand['id'] ?>" <?= $brand_id === (int) $brand['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($brand['code'] . ' — ' . $brand['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name"
                           value="<?= htmlspecialchars($name) ?>" required maxlength="200"
                           autocomplete="off">
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="slug" name="slug"
                           value="<?= htmlspecialchars($slug) ?>" required maxlength="220"
                           placeholder="Auto-generated from name">
                    <div class="form-text">URL-friendly identifier. Auto-generated from the product name.</div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                            <input type="number" class="form-control" id="price" name="price"
                                   value="<?= htmlspecialchars($price) ?>" required min="0" step="0.01">
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="discount_price" class="form-label">Discount Price</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                            <input type="number" class="form-control" id="discount_price" name="discount_price"
                                   value="<?= htmlspecialchars($discount_price) ?>" min="0" step="0.01">
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="stock_quantity" class="form-label">Stock Quantity</label>
                        <input type="number" class="form-control" id="stock_quantity" name="stock_quantity"
                               value="<?= htmlspecialchars((string) $stock_quantity) ?>" min="0">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="image" class="form-label">Image</label>
                    <input type="file" class="form-control" id="image" name="image"
                           accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="form-text">Accepted: JPEG, PNG, GIF, WebP. Max size: 2 MB.</div>
                    <img id="imagePreview" src="#" alt="Preview"
                         style="display: none; width: 120px; height: 120px; object-fit: cover; border-radius: 4px; border: 1px solid #dee2e6; margin-top: 8px;">
                </div>

                <div class="mb-3">
                    <label for="barcode" class="form-label">Barcode</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="barcode" name="barcode"
                               value="<?= htmlspecialchars($barcode) ?>" maxlength="50"
                               placeholder="EAN-13, UPC-A, or leave empty">
                        <button type="button" class="btn btn-outline-secondary" id="generateBarcodeBtn"
                                title="Generate barcode from product code">
                            <i class="bi bi-upc-scan"></i> Generate
                        </button>
                    </div>
                    <div class="form-text">Optional. Must be unique if provided.</div>
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?= $status === ACTIVE_STATUS ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status === INACTIVE_STATUS ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn" style="background: var(--admin-primary); color: #fff;">Save Product</button>
                    <a href="<?= SITE_URL ?>admin/products/index.php" class="btn btn-secondary">Cancel</a>
                </div>

            </form>
        </div>

    </main>
</div>

<script>
function generateBarcode() {
    var code = document.getElementById('name')?.value || '';
    if (!code) return;
    // Create a numeric hash from the product name
    var hash = 0;
    for (var i = 0; i < code.length; i++) {
        var chr = code.charCodeAt(i);
        hash = ((hash << 5) - hash) + chr;
        hash |= 0;
    }
    // Pad to 12 digits + check digit = EAN-13
    var num = Math.abs(hash).toString().padStart(12, '0').slice(-12);
    var sum = 0;
    for (var j = 0; j < 12; j++) {
        sum += parseInt(num[j]) * (j % 2 === 0 ? 1 : 3);
    }
    var check = (10 - (sum % 10)) % 10;
    document.getElementById('barcode').value = num + check;
}

document.addEventListener('DOMContentLoaded', function () {
    var genBtn = document.getElementById('generateBarcodeBtn');
    if (genBtn) {
        genBtn.addEventListener('click', generateBarcode);
    }
});

function slugify(text) {
    return text
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-+|-+$/g, '');
}

document.addEventListener('DOMContentLoaded', function () {
    var nameInput = document.getElementById('name');
    var slugInput = document.getElementById('slug');
    if (nameInput && slugInput) {
        nameInput.addEventListener('input', function () {
            slugInput.value = slugify(nameInput.value);
        });
    }

    var imageInput = document.getElementById('image');
    var imagePreview = document.getElementById('imagePreview');
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function (e) {
            var file = e.target.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                imagePreview.src = '#';
                imagePreview.style.display = 'none';
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/admin-footer.php';
