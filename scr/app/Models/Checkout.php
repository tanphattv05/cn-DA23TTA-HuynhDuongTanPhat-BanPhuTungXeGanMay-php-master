<?php
namespace MotoParts\App\Models;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class Checkout
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

    public function products(array $ids): array
    {
        return (new CartProduct($this->connection))->findMany($ids);
    }

    public function lockProduct(int $id): ?array
    {
        return $this->query(
            'SELECT id, name, price, stock FROM products WHERE id = ? FOR UPDATE', 'i', [$id]
        )->get_result()->fetch_assoc();
    }

    public function user(int $id, bool $lock = false): ?array
    {
        return $this->query(
            "SELECT id, fullname, phone, role FROM users WHERE id = ? AND role IN ('customer','admin')"
                . ($lock ? ' LOCK IN SHARE MODE' : ''), 'i', [$id]
        )->get_result()->fetch_assoc();
    }

    public function create(?int $userId, array $recipient, string $total): int
    {
        $this->query(
            "INSERT INTO orders(user_id,fullname,phone,address,note,total,status) VALUES (?,?,?,?,?,?,'pending')",
            'isssss', [$userId, $recipient['fullname'], $recipient['phone'], $recipient['address'], $recipient['note'], $total]
        );
        return $this->connection->insert_id;
    }

    public function addDetail(int $orderId, int $productId, int $quantity, string $price): void
    {
        $this->query(
            'INSERT INTO order_details(order_id,product_id,quantity,price) VALUES (?,?,?,?)',
            'iiis', [$orderId, $productId, $quantity, $price]
        );
    }

    public function deduct(int $productId, int $quantity): void
    {
        $statement = $this->query(
            'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?',
            'iii', [$quantity, $productId, $quantity]
        );
        if ($statement->affected_rows !== 1) {
            throw new \DomainException('Không còn đủ tồn kho. Vui lòng kiểm tra lại giỏ hàng.');
        }
    }

    public function begin(): void { $this->connection->begin_transaction(); }
    public function commit(): void { $this->connection->commit(); }
    public function rollback(): void { $this->connection->rollback(); }
}