<?php
namespace MotoParts\App\Models;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class StorefrontOrder
{
    private \mysqli $connection;

    public function __construct(\mysqli $connection) { $this->connection = $connection; }

    public function forCustomer(int $userId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT id, fullname, total, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function findOwned(int $id, int $userId): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT id, fullname, phone, address, note, total, status, created_at
             FROM orders WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function items(int $id, int $userId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT od.product_id, od.quantity, od.price, od.price * od.quantity AS line_total, p.name, p.image
             FROM order_details od
             INNER JOIN orders o ON o.id = od.order_id
             LEFT JOIN products p ON p.id = od.product_id
             WHERE o.id = ? AND o.user_id = ? ORDER BY od.id ASC'
        );
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
