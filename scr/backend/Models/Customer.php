<?php
namespace MotoParts\App\Models;

if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

final class Customer
{
    private \mysqli $connection;

    public function __construct(\mysqli $connection)
    {
        $this->connection = $connection;
    }

    private function query(string $sql, string $types, array $values): \mysqli_stmt
    {
        $statement = $this->connection->prepare($sql);
        $statement->bind_param($types, ...$values);
        $statement->execute();
        return $statement;
    }

    private function search(string $keyword): array
    {
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword) . '%';
        return [
            "u.role = 'customer' AND (u.fullname LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR u.phone LIKE ? ESCAPE '!')",
            [$like, $like, $like]
        ];
    }

    public function count(string $keyword): int
    {
        [$where, $values] = $this->search($keyword);
        return (int) $this->query('SELECT COUNT(*) AS total FROM users u WHERE ' . $where, 'sss', $values)->get_result()->fetch_assoc()['total'];
    }

    public function paginate(string $keyword, int $limit, int $offset): array
    {
        [$where, $values] = $this->search($keyword);
        // One aggregate row per user, including customers with no orders.
        return $this->query(
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
            'sssii', array_merge($values, [$limit, $offset])
        )->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function find(int $id): ?array
    {
        return $this->query(
            "SELECT id, fullname, email, phone, created_at FROM users WHERE id = ? AND role = 'customer'",
            'i', [$id]
        )->get_result()->fetch_assoc();
    }

    public function orderStatistics(int $customerId): array
    {
        return $this->query(
            "SELECT COUNT(*) AS order_count,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) AS completed_count,
                    COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END), 0) AS completed_total
             FROM orders WHERE user_id = ?", 'i', [$customerId]
        )->get_result()->fetch_assoc();
    }

    public function orderHistory(int $customerId, int $limit, int $offset): array
    {
        return $this->query(
            'SELECT id, created_at, fullname, total, status FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?',
            'iii', [$customerId, $limit, $offset]
        )->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
