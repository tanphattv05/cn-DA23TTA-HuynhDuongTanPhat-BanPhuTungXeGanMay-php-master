<?php
namespace MotoParts\App\Models;

if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

final class Product
{
    private \mysqli $connection;

    public function __construct(\mysqli $connection)
    {
        $this->connection = $connection;
    }

    private function query(string $sql, string $types = '', array $values = []): \mysqli_stmt
    {
        $statement = $this->connection->prepare($sql);
        if ($types !== '') $statement->bind_param($types, ...$values);
        $statement->execute();
        return $statement;
    }

    private function filters(string $keyword, int $categoryId): array
    {
        $where = ' WHERE 1=1';
        $types = '';
        $values = [];
        if ($keyword !== '') {
            $where .= " AND (p.name LIKE ? ESCAPE '!' OR p.brand LIKE ? ESCAPE '!')";
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword) . '%';
            $types = 'ss';
            $values = [$like, $like];
        }
        if ($categoryId) {
            $where .= ' AND p.category_id = ?';
            $types .= 'i';
            $values[] = $categoryId;
        }
        return [$where, $types, $values];
    }

    public function count(string $keyword, int $categoryId): int
    {
        [$where, $types, $values] = $this->filters($keyword, $categoryId);
        return (int) $this->query('SELECT COUNT(*) AS total FROM products p' . $where, $types, $values)->get_result()->fetch_assoc()['total'];
    }

    public function paginate(string $keyword, int $categoryId, int $limit, int $offset): array
    {
        [$where, $types, $values] = $this->filters($keyword, $categoryId);
        return $this->query(
            'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id'
            . $where . ' ORDER BY p.id DESC LIMIT ? OFFSET ?',
            $types . 'ii', array_merge($values, [$limit, $offset])
        )->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function find(int $id, bool $lock = false): ?array
    {
        return $this->query('SELECT * FROM products WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), 'i', [$id])->get_result()->fetch_assoc();
    }

    private function values(array $data, string $image): array
    {
        return [(int) $data['category_id'], $data['name'], $data['price'], $image,
            $data['brand'], (int) $data['stock'], $data['description']];
    }

    public function create(array $data, string $image): void
    {
        $this->query(
            'INSERT INTO products (category_id,name,price,image,brand,stock,description) VALUES (?,?,?,?,?,?,?)',
            'issssis', $this->values($data, $image)
        );
    }

    public function update(int $id, array $data, string $image): void
    {
        $this->query(
            'UPDATE products SET category_id=?, name=?, price=?, image=?, brand=?, stock=?, description=? WHERE id=?',
            'issssisi', array_merge($this->values($data, $image), [$id])
        );
    }
}
