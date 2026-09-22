<?php
namespace MotoParts\App\Models;

if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

final class Category
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

    public function options(): array
    {
        $statement = $this->connection->prepare('SELECT id, name FROM categories ORDER BY name');
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function findShared(int $id): ?array
    {
        return $this->query('SELECT id FROM categories WHERE id = ? LOCK IN SHARE MODE', 'i', [$id])->get_result()->fetch_assoc();
    }

    private function searchPattern(string $keyword): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword) . '%';
    }

    public function count(string $keyword): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) AS total FROM categories WHERE name LIKE ? ESCAPE '!'",
            's', [$this->searchPattern($keyword)]
        )->get_result()->fetch_assoc()['total'];
    }

    public function paginate(string $keyword, int $limit, int $offset): array
    {
        return $this->query(
            "SELECT c.id, c.name, c.description,
             (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
             FROM categories c WHERE c.name LIKE ? ESCAPE '!'
             ORDER BY c.id DESC LIMIT ? OFFSET ?",
            'sii', [$this->searchPattern($keyword), $limit, $offset]
        )->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function find(int $id, bool $lock = false): ?array
    {
        return $this->query(
            'SELECT id, name, description FROM categories WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''),
            'i', [$id]
        )->get_result()->fetch_assoc();
    }

    public function nameExists(string $name, ?int $exceptId): bool
    {
        // Equality inherits the column collation; no new UNIQUE constraint.
        return $this->query(
            'SELECT id FROM categories WHERE name = ? AND id <> ? LIMIT 1',
            'si', [$name, $exceptId ?? 0]
        )->get_result()->fetch_assoc() !== null;
    }

    public function create(string $name, string $description): void
    {
        $this->query('INSERT INTO categories (name, description) VALUES (?, ?)', 'ss', [$name, $description]);
    }

    public function update(int $id, string $name, string $description): void
    {
        $this->query('UPDATE categories SET name = ?, description = ? WHERE id = ?', 'ssi', [$name, $description, $id]);
    }
}
