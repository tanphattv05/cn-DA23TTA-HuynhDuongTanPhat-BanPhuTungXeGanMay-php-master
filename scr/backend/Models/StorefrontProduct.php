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

    private const FROM = ' FROM products p INNER JOIN categories c ON c.id = p.category_id';
    private const SORTS = [
        'newest' => 'p.created_at DESC, p.id DESC',
        'price_asc' => 'p.price ASC, p.id DESC',
        'price_desc' => 'p.price DESC, p.id DESC',
        'name_asc' => 'p.name ASC, p.id DESC',
    ];

    // One predicate builder for both queries. Values arrive normalized by Controller.
    private function conditions(array $filters): array
    {
        $parts = []; $types = ''; $values = [];
        if ($filters['q'] !== '') {
            // Explicit ! escape also makes literal backslashes independent of SQL mode.
            $term = '%' . strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $parts[] = "(p.name LIKE ? ESCAPE '!' OR p.brand LIKE ? ESCAPE '!')";
            $types .= 'ss'; array_push($values, $term, $term);
        }
        if ($filters['category'] !== '') {
            $parts[] = 'p.category_id = ?'; $types .= 'i'; $values[] = (int) $filters['category'];
        }
        foreach (['min_price' => '>=', 'max_price' => '<='] as $key => $operator) {
            if ($filters[$key] !== '') {
                $parts[] = 'p.price ' . $operator . ' ?'; $types .= 's'; $values[] = $filters[$key];
            }
        }
        if ($filters['stock'] === 'in_stock') $parts[] = 'p.stock > 0';
        if ($filters['stock'] === 'out_of_stock') $parts[] = 'p.stock <= 0';
        return [$parts ? ' WHERE ' . implode(' AND ', $parts) : '', $types, $values];
    }

    private function rows(string $sql, string $types = '', array $values = []): array
    {
        $statement = $this->connection->prepare($sql);
        try {
            if ($types !== '') $statement->bind_param($types, ...$values);
            $statement->execute();
            return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        } finally { $statement->close(); }
    }

    public function categories(): array
    {
        return $this->rows('SELECT id, name FROM categories ORDER BY name ASC, id ASC');
    }

    public function count(array $filters): int
    {
        [$where, $types, $values] = $this->conditions($filters);
        return (int) $this->rows('SELECT COUNT(*) AS total' . self::FROM . $where, $types, $values)[0]['total'];
    }

    public function page(array $filters, int $offset): array
    {
        [$where, $types, $values] = $this->conditions($filters);
        $sort = self::SORTS[$filters['sort']] ?? self::SORTS['newest'];
        $types .= 'ii'; array_push($values, 12, max(0, $offset));
        return $this->rows('SELECT p.id, p.name, p.price, p.image, p.brand, p.stock, c.name AS category_name'
            . self::FROM . $where . ' ORDER BY ' . $sort . ' LIMIT ? OFFSET ?', $types, $values);
    }

    public function find(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT p.id, p.name, p.price, p.image, p.brand, p.stock, p.description,
                    c.name AS category_name
             FROM products p INNER JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?'
        );
        try {
            $statement->bind_param('i', $id);
            $statement->execute();
            return $statement->get_result()->fetch_assoc();
        } finally { $statement->close(); }
    }
}
