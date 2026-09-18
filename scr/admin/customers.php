<?php
require_once __DIR__ . '/includes/customer-view.php';
$q = mb_substr(product_text($_GET, 'q'), 0, 100, 'UTF-8');
$page = customer_page($_GET);
$like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
$where = "u.role = 'customer' AND (u.fullname LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR u.phone LIKE ? ESCAPE '!')";
$rows = [];
$total = 0;
$pages = 1;
$error = '';
try {
    $total = (int) product_query('SELECT COUNT(*) AS total FROM users u WHERE ' . $where, 'sss', [$like, $like, $like])->get_result()->fetch_assoc()['total'];
    $pages = max(1, (int) ceil($total / 10));
    $page = min($page, $pages);
    // Exactly one aggregate row per user; no order_details join and no N+1 queries.
    $rows = product_query(
        "SELECT u.id, u.fullname, u.email, u.phone, u.created_at,
                COALESCE(s.order_count, 0) AS order_count,
                COALESCE(s.completed_total, 0) AS completed_total
         FROM users u
         LEFT JOIN (
             SELECT user_id, COUNT(*) AS order_count,
                    SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END) AS completed_total
             FROM orders WHERE user_id IS NOT NULL GROUP BY user_id
         ) s ON s.user_id = u.id
         WHERE " . $where . ' ORDER BY u.id DESC LIMIT ? OFFSET ?',
        'sssii', [$like, $like, $like, 10, ($page - 1) * 10]
    )->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    http_response_code(503);
    $error = 'Không thể tải danh sách khách hàng. Vui lòng thử lại sau.';
}
$pageTitle = 'Quản lý khách hàng';
include __DIR__ . '/includes/header.php';
?>
<div class="py-3">
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= product_escape($error) ?></div><?php endif; ?>
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-8">
            <label for="q" class="form-label">Họ tên, email hoặc điện thoại</label>
            <input class="form-control" id="q" name="q" maxlength="100" value="<?= product_escape($q) ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end gap-2">
            <button class="btn btn-dark" type="submit">Tìm kiếm</button>
            <a class="btn btn-outline-secondary" href="customers.php">Bỏ tìm kiếm</a>
        </div>
    </form>
    <?php if (!$error): ?>
    <p><?= $total ?> khách hàng — Trang <?= $page ?>/<?= $pages ?></p>
    <div class="table-responsive"><table class="table table-bordered align-middle">
        <thead><tr><th>Mã</th><th>Họ tên</th><th>Email</th><th>Điện thoại</th><th>Ngày đăng ký</th><th>Tổng số đơn</th><th>Tổng tiền hoàn thành</th><th>Thao tác</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $customer): ?>
            <tr>
                <td><?= (int) $customer['id'] ?></td>
                <td><?= product_escape($customer['fullname']) ?></td>
                <td><?= product_escape($customer['email']) ?></td>
                <td><?= product_escape($customer['phone'] ?: '—') ?></td>
                <td><?= product_escape(customer_date($customer['created_at'])) ?></td>
                <td><?= (int) $customer['order_count'] ?></td>
                <td><?= number_format((float) $customer['completed_total'], 2, ',', '.') ?> ₫</td>
                <td><a class="btn btn-sm btn-outline-primary" href="customer-detail.php?id=<?= (int) $customer['id'] ?>">Xem chi tiết</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8">Không tìm thấy khách hàng.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php customer_pagination('customers.php', ['q' => $q], $page, $pages); ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
