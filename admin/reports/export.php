<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();

$type   = trim($_GET['type'] ?? 'sales');
$format = trim($_GET['format'] ?? 'csv');

logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'reports', 'export_' . $format, $type, 'Exported report: ' . $type . ' as ' . $format);
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
$status = trim($_GET['status'] ?? '');
$period = trim($_GET['period'] ?? 'daily');
$search = trim($_GET['search'] ?? '');
$sort   = trim($_GET['sort'] ?? 'spent_desc');
$tab    = trim($_GET['tab'] ?? 'bestsellers');

$pdo = getDbConnection();

$filename = 'report-' . $type . '-' . date('Y-m-d');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $output = fopen('php://output', 'w');
} elseif ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    $output = fopen('php://output', 'w');
} else {
    setFlashMessage('error', 'Invalid export format.');
    redirect(SITE_URL . 'admin/reports/' . $type . '.php');
    exit;
}

fwrite($output, "\xEF\xBB\xBF");

switch ($type) {

    case 'sales':
        $whereClause = "WHERE o.status != 'cancelled'";
        $params = [];
        if ($from !== '') {
            $whereClause .= ' AND DATE(o.created_at) >= :from_date';
            $params[':from_date'] = $from;
        }
        if ($to !== '') {
            $whereClause .= ' AND DATE(o.created_at) <= :to_date';
            $params[':to_date'] = $to;
        }
        switch ($period) {
            case 'weekly':   $dateLabel = "CONCAT(YEAR(o.created_at), '-W', LPAD(WEEK(o.created_at, 1), 2, '0'))"; break;
            case 'monthly':  $dateLabel = "DATE_FORMAT(o.created_at, '%Y-%m')"; break;
            case 'yearly':   $dateLabel = "YEAR(o.created_at)"; break;
            default:         $dateLabel = "DATE(o.created_at)"; break;
        }
        $stmt = $pdo->prepare("
            SELECT {$dateLabel} AS label, COUNT(*) AS orders, COALESCE(SUM(grand_total), 0) AS revenue
            FROM orders o
            {$whereClause}
            GROUP BY label
            ORDER BY label DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        fputcsv($output, ['Period', 'Orders', 'Revenue (RWF)']);
        foreach ($rows as $r) {
            fputcsv($output, [$r['label'], (int) $r['orders'], number_format((float) $r['revenue'], 2)]);
        }
        break;

    case 'orders':
        $whereClause = 'WHERE 1=1';
        $params = [];
        if ($from !== '') {
            $whereClause .= ' AND DATE(o.created_at) >= :from_date';
            $params[':from_date'] = $from;
        }
        if ($to !== '') {
            $whereClause .= ' AND DATE(o.created_at) <= :to_date';
            $params[':to_date'] = $to;
        }
        if ($status !== '') {
            $whereClause .= ' AND o.status = :status';
            $params[':status'] = $status;
        }
        $stmt = $pdo->prepare("
            SELECT o.order_number, COALESCE(o.customer_name, u.full_name) AS customer,
                   o.status, o.grand_total, o.created_at
            FROM orders o
            JOIN users u ON o.user_id = u.id
            {$whereClause}
            ORDER BY o.created_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        fputcsv($output, ['Order Number', 'Customer', 'Status', 'Total (RWF)', 'Date']);
        foreach ($rows as $r) {
            fputcsv($output, [
                $r['order_number'],
                $r['customer'],
                $r['status'],
                number_format((float) $r['grand_total'], 2),
                date('Y-m-d', strtotime($r['created_at'])),
            ]);
        }
        break;

    case 'products':
        if ($tab === 'bestsellers') {
            $stmt = $pdo->prepare("
                SELECT p.name, p.code, c.name AS category, p.stock_quantity,
                       SUM(oi.quantity) AS sold_qty, COALESCE(SUM(oi.subtotal), 0) AS revenue
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN products p ON oi.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE o.status = 'delivered'
                GROUP BY p.id, p.name, p.code, c.name, p.stock_quantity
                ORDER BY sold_qty DESC
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll();
            fputcsv($output, ['Product', 'Code', 'Category', 'Stock', 'Sold Qty', 'Revenue (RWF)']);
            foreach ($rows as $r) {
                fputcsv($output, [
                    $r['name'], $r['code'], $r['category'] ?? '—',
                    (int) $r['stock_quantity'], (int) $r['sold_qty'],
                    number_format((float) $r['revenue'], 2),
                ]);
            }
        } elseif ($tab === 'lowstock') {
            $rows = $pdo->query("
                SELECT p.name, p.code, c.name AS category, p.stock_quantity, p.price
                FROM products p LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.stock_quantity > 0 AND p.stock_quantity < 5
                ORDER BY p.stock_quantity ASC
            ")->fetchAll();
            fputcsv($output, ['Product', 'Code', 'Category', 'Stock', 'Price (RWF)']);
            foreach ($rows as $r) {
                fputcsv($output, [
                    $r['name'], $r['code'], $r['category'] ?? '—',
                    (int) $r['stock_quantity'], number_format((float) $r['price'], 2),
                ]);
            }
        } else {
            $rows = $pdo->query("
                SELECT p.name, p.code, c.name AS category, p.price
                FROM products p LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.stock_quantity = 0
                ORDER BY p.name ASC
            ")->fetchAll();
            fputcsv($output, ['Product', 'Code', 'Category', 'Price (RWF)']);
            foreach ($rows as $r) {
                fputcsv($output, [
                    $r['name'], $r['code'], $r['category'] ?? '—',
                    number_format((float) $r['price'], 2),
                ]);
            }
        }
        break;

    case 'customers':
        $whereClause = "WHERE u.role = 'customer'";
        $params = [];
        if ($search !== '') {
            $whereClause .= ' AND (u.full_name LIKE :search OR u.email LIKE :search2)';
            $params[':search'] = "%{$search}%";
            $params[':search2'] = "%{$search}%";
        }
        $orderBy = match ($sort) {
            'name_asc' => 'u.full_name ASC', 'name_desc' => 'u.full_name DESC',
            'orders_desc' => 'total_orders DESC', 'spent_asc' => 'total_spent ASC',
            default => 'total_spent DESC',
        };
        $stmt = $pdo->prepare("
            SELECT u.full_name, u.email, u.phone, COUNT(DISTINCT o.id) AS total_orders,
                   COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.grand_total ELSE 0 END), 0) AS total_spent
            FROM users u LEFT JOIN orders o ON u.id = o.user_id
            {$whereClause}
            GROUP BY u.id, u.full_name, u.email, u.phone
            ORDER BY {$orderBy}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        fputcsv($output, ['Name', 'Email', 'Phone', 'Total Orders', 'Total Spent (RWF)']);
        foreach ($rows as $r) {
            fputcsv($output, [
                $r['full_name'], $r['email'], $r['phone'] ?? '—',
                (int) $r['total_orders'], number_format((float) $r['total_spent'], 2),
            ]);
        }
        break;

    case 'finance':
        $whereClause = "WHERE o.status != ?";
        $params = [ORDER_CANCELLED];
        if ($from !== '') { $whereClause .= ' AND DATE(o.created_at) >= ?'; $params[] = $from; }
        if ($to !== '')   { $whereClause .= ' AND DATE(o.created_at) <= ?'; $params[] = $to; }
        $stmt = $pdo->prepare("
            SELECT o.order_number, COALESCE(o.customer_name, u.full_name) AS customer,
                   o.subtotal, o.shipping, o.tax, o.grand_total, o.payment_status, o.created_at
            FROM orders o JOIN users u ON o.user_id = u.id
            $whereClause ORDER BY o.created_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        fputcsv($output, ['Order', 'Customer', 'Subtotal', 'Shipping', 'Tax', 'Grand Total', 'Payment', 'Date']);
        foreach ($rows as $r) {
            fputcsv($output, [
                $r['order_number'], $r['customer'],
                number_format((float)$r['subtotal'],2), number_format((float)$r['shipping'],2),
                number_format((float)$r['tax'],2), number_format((float)$r['grand_total'],2),
                $r['payment_status'], date('Y-m-d', strtotime($r['created_at'])),
            ]);
        }
        break;

    case 'profit':
        $whereDate = '';
        $params = [];
        if ($from !== '') { $whereDate .= ' AND DATE(o.created_at) >= ?'; $params[] = $from; }
        if ($to !== '')   { $whereDate .= ' AND DATE(o.created_at) <= ?'; $params[] = $to; }
        $stmt = $pdo->prepare("
            SELECT p.name, p.code, p.price AS selling_price, p.cost_price,
                   SUM(oi.quantity) AS qty_sold,
                   SUM(oi.subtotal) AS sales,
                   SUM(oi.quantity * COALESCE(p.cost_price,0)) AS cost,
                   SUM(oi.subtotal - (oi.quantity * COALESCE(p.cost_price,0))) AS profit,
                   CASE WHEN SUM(oi.subtotal)>0 THEN ROUND((SUM(oi.subtotal - (oi.quantity * COALESCE(p.cost_price,0))) / SUM(oi.subtotal))*100,2) ELSE 0 END AS margin
            FROM order_items oi JOIN products p ON oi.product_id=p.id JOIN orders o ON oi.order_id=o.id
            WHERE o.status='delivered' $whereDate
            GROUP BY p.id, p.name, p.code, p.price, p.cost_price
            ORDER BY profit DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        fputcsv($output, ['Product','Code','Selling Price','Cost Price','Qty Sold','Sales','Cost','Profit','Margin %']);
        foreach ($rows as $r) {
            fputcsv($output, [
                $r['name'], $r['code'],
                number_format((float)$r['selling_price'],2), number_format((float)$r['cost_price'],2),
                (int)$r['qty_sold'],
                number_format((float)$r['sales'],2), number_format((float)$r['cost'],2),
                number_format((float)$r['profit'],2), (float)$r['margin'],
            ]);
        }
        break;

    case 'tax':
        $whereClause = "WHERE o.status != ?";
        $params = [ORDER_CANCELLED];
        if ($from !== '') { $whereClause .= ' AND DATE(o.created_at) >= ?'; $params[] = $from; }
        if ($to !== '')   { $whereClause .= ' AND DATE(o.created_at) <= ?'; $params[] = $to; }
        $stmt = $pdo->prepare("
            SELECT o.order_number, COALESCE(o.customer_name,u.full_name) AS customer,
                   o.grand_total, o.tax, o.payment_status, o.created_at
            FROM orders o JOIN users u ON o.user_id=u.id
            $whereClause AND o.tax>0 ORDER BY o.created_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        fputcsv($output, ['Order','Customer','Grand Total','Tax','Tax %','Payment','Date']);
        foreach ($rows as $r) {
            $taxPct = (float)$r['grand_total'] > 0 ? round((float)$r['tax']/(float)$r['grand_total']*100,1) : 0;
            fputcsv($output, [
                $r['order_number'], $r['customer'],
                number_format((float)$r['grand_total'],2), number_format((float)$r['tax'],2),
                $taxPct, $r['payment_status'], date('Y-m-d', strtotime($r['created_at'])),
            ]);
        }
        break;

    case 'inventory':
        $stmt = $pdo->query("
            SELECT p.name, p.code, c.name AS category, b.name AS brand,
                   p.price, p.cost_price, p.stock_quantity,
                   (p.price * p.stock_quantity) AS retail_value,
                   (COALESCE(p.cost_price,0) * p.stock_quantity) AS cost_value,
                   COALESCE((SELECT SUM(oi.quantity) FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE oi.product_id=p.id AND o.status='delivered'),0) AS total_sold
            FROM products p
            LEFT JOIN categories c ON p.category_id=c.id
            LEFT JOIN brands b ON p.brand_id=b.id
            WHERE p.status='active'
            ORDER BY p.name ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        fputcsv($output, ['Product','Code','Category','Brand','Price','Cost Price','Stock','Retail Value','Cost Value','Total Sold']);
        foreach ($rows as $r) {
            fputcsv($output, [
                $r['name'], $r['code'], $r['category']??'—', $r['brand']??'—',
                number_format((float)$r['price'],2), number_format((float)$r['cost_price']??0,2),
                (int)$r['stock_quantity'],
                number_format((float)$r['retail_value'],2), number_format((float)$r['cost_value'],2),
                (int)$r['total_sold'],
            ]);
        }
        break;

    default:
        fputcsv($output, ['Error: Unknown report type.']);
        break;
}

fclose($output);
exit;
