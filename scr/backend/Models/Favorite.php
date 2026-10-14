<?php
namespace MotoParts\App\Models;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class Favorite
{
    public function __construct(private \mysqli $conn) {}

    private function rows(string $sql, string $types, array $values): array
    {
        $stmt = $this->conn->prepare($sql);
        try {
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        } finally { $stmt->close(); }
    }

    public function products(int $userId): array
    {
        return $this->rows('SELECT p.id, p.name, p.price, p.image, p.stock
            FROM favorites f INNER JOIN products p ON p.id = f.product_id
            WHERE f.user_id = ? ORDER BY f.id DESC', 'i', [$userId]);
    }

    public function savedIds(int $userId, array $ids): array
    {
        if (!$ids) return [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        return array_map('intval', array_column($this->rows(
            'SELECT product_id FROM favorites WHERE user_id = ? AND product_id IN ('.$marks.')',
            str_repeat('i', count($ids)+1), array_merge([$userId], $ids)), 'product_id'));
    }

    public function exists(int $productId): bool
    {
        return (bool) $this->rows('SELECT id FROM products WHERE id = ?', 'i', [$productId]);
    }

    // Desired state, not an inverted toggle: repeated POSTs cannot undo an add.
    public function set(int $userId, int $productId, bool $saved): void
    {
        $this->rows($saved
            ? 'INSERT INTO favorites (user_id, product_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = id'
            : 'DELETE FROM favorites WHERE user_id = ? AND product_id = ?', 'ii', [$userId, $productId]);
    }
}
