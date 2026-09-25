<?php
namespace MotoParts\App\Models;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class CartProduct
{
    private \mysqli $connection;

    public function __construct(\mysqli $connection)
    {
        $this->connection = $connection;
    }

    public function find(int $id): ?array
    {
        $rows = $this->findMany([$id]);
        return $rows[0] ?? null;
    }

    public function findMany(array $ids): array
    {
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->connection->prepare(
            'SELECT id, name, price, image, stock FROM products WHERE id IN (' . $placeholders . ') ORDER BY id'
        );
        $statement->bind_param(str_repeat('i', count($ids)), ...$ids);
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}