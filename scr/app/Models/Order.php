<?php
namespace MotoParts\App\Models;

if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

final class Order
{
    private \mysqli $connection;

    public function __construct(\mysqli $connection)
    {
        $this->connection = $connection;
    }

    private function query(string $sql, string $types = '', array $values = []): \mysqli_stmt
    {
        $statement = $this->connection->prepare($sql);
        if ($types !== '') {
            $statement->bind_param($types, ...$values);
        }
        $statement->execute();
        return $statement;
    }

    private function condition(array $filters): array
    {
        $where = [];
        $types = '';
        $values = [];
        if ($filters['q'] !== '') {
            if (preg_match('/\A#?([0-9]+)\z/', $filters['q'], $match)) {
                $digits = ltrim($match[1], '0');
                $id = strlen($digits) <= 10 && (float) ($digits ?: '0') <= 2147483647 ? (int) $digits : 0;
                $where[] = 'id = ?';
                $types .= 'i';
                $values[] = $id;
            } else {
                $where[] = "(fullname LIKE ? ESCAPE '!' OR phone LIKE ? ESCAPE '!')";
                $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
                $types .= 'ss';
                array_push($values, $like, $like);
            }
        }
        if ($filters['status'] !== '') {
            $where[] = 'status = ?';
            $types .= 's';
            $values[] = $filters['status'];
        }
        if ($filters['from'] !== '') {
            $where[] = 'created_at >= ?';
            $types .= 's';
            $values[] = $filters['from'] . ' 00:00:00';
        }
        if ($filters['to'] !== '') {
            // orders.created_at is TIMESTAMP with second precision.
            $where[] = 'created_at <= ?';
            $types .= 's';
            $values[] = $filters['to'] . ' 23:59:59';
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $types, $values];
    }

    public function count(array $filters): int
    {
        [$where, $types, $values] = $this->condition($filters);
        return (int) $this->query('SELECT COUNT(*) AS total FROM orders' . $where, $types, $values)->get_result()->fetch_assoc()['total'];
    }

    public function paginate(array $filters, int $limit, int $offset): array
    {
        [$where, $types, $values] = $this->condition($filters);
        return $this->query(
            'SELECT id, fullname, phone, total, status, created_at FROM orders' . $where . ' ORDER BY id DESC LIMIT ? OFFSET ?',
            $types . 'ii', array_merge($values, [$limit, $offset])
        )->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function find(int $id): ?array
    {
        return $this->query(
            "SELECT o.id, o.user_id, o.fullname, o.phone, o.address, o.note, o.total, o.status, o.created_at,
                    u.id AS customer_id
             FROM orders o LEFT JOIN users u ON u.id = o.user_id AND u.role = 'customer'
             WHERE o.id = ?", 'i', [$id]
        )->get_result()->fetch_assoc();
    }

    public function items(int $id): array
    {
        return $this->query(
            'SELECT od.product_id, od.quantity, od.price, od.price * od.quantity AS line_total,
                    p.name, p.image
             FROM order_details od LEFT JOIN products p ON p.id = od.product_id
             WHERE od.order_id = ? ORDER BY od.id', 'i', [$id]
        )->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function totals(int $id, string $savedTotal): array
    {
        return $this->query(
            'SELECT COALESCE(SUM(price * quantity), 0) AS detail_total,
                    COALESCE(SUM(price * quantity), 0) <> CAST(? AS DECIMAL(12,2)) AS differs
             FROM order_details WHERE order_id = ?', 'si', [$savedTotal, $id]
        )->get_result()->fetch_assoc();
    }

    public function begin(): void
    {
        $this->connection->begin_transaction();
    }

    public function lock(int $id): ?array
    {
        return $this->query('SELECT id, status FROM orders WHERE id = ? FOR UPDATE', 'i', [$id])->get_result()->fetch_assoc();
    }

    public function restoreStock(int $id): void
    {
        $details = $this->query(
            'SELECT product_id, quantity FROM order_details WHERE order_id = ? ORDER BY product_id', 'i', [$id]
        )->get_result();
        $restore = $this->connection->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        while ($detail = $details->fetch_assoc()) {
            $productId = (int) $detail['product_id'];
            $quantity = (int) $detail['quantity'];
            $restore->bind_param('ii', $quantity, $productId);
            $restore->execute();
            if ($restore->affected_rows !== 1) {
                throw new \RuntimeException('Không thể hoàn trả tồn kho cho sản phẩm.');
            }
        }
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->query('UPDATE orders SET status = ? WHERE id = ?', 'si', [$status, $id]);
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollback(): void
    {
        $this->connection->rollback();
    }
}