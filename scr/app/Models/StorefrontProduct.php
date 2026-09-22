<?php
namespace MotoParts\App\Models;

if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

final class StorefrontProduct
{
    private \mysqli $connection;

    public function __construct(\mysqli $connection)
    {
        $this->connection = $connection;
    }

    public function all(): array
    {
        return $this->connection->query(
            'SELECT p.id, p.name, p.price, p.image, p.brand, c.name AS category_name
             FROM products p INNER JOIN categories c ON c.id = p.category_id
             ORDER BY p.id DESC'
        )->fetch_all(MYSQLI_ASSOC);
    }

    public function find(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT p.id, p.name, p.price, p.image, p.brand, p.stock, p.description,
                    c.name AS category_name
             FROM products p INNER JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?'
        );
        $statement->bind_param('i', $id);
        $statement->execute();
        return $statement->get_result()->fetch_assoc();
    }
}