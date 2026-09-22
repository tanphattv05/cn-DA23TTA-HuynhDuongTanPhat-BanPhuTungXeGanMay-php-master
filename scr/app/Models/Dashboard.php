<?php
namespace MotoParts\App\Models;

if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

final class Dashboard
{
    private \mysqli $connection;

    public function __construct(\mysqli $connection)
    {
        $this->connection = $connection;
    }

    public function statistics(): array
    {
        return $this->connection->query(
            "SELECT
                (SELECT COUNT(*) FROM products) AS product_count,
                (SELECT COUNT(*) FROM categories) AS category_count,
                (SELECT COUNT(*) FROM users WHERE role = 'customer') AS customer_count,
                (SELECT COUNT(*) FROM orders) AS order_count,
                (SELECT COUNT(*) FROM orders WHERE status = 'pending') AS pending_count,
                (SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'completed') AS revenue"
        )->fetch_assoc();
    }

    public function latestOrders(): array
    {
        return $this->connection->query(
            'SELECT id, fullname, total, status, created_at FROM orders ORDER BY id DESC LIMIT 10'
        )->fetch_all(MYSQLI_ASSOC);
    }
}