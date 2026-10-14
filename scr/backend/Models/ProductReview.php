<?php
namespace MotoParts\App\Models;
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class ProductReview
{
    public function __construct(private \mysqli $conn) {}

    private const PURCHASE = "SELECT 1 FROM orders o INNER JOIN order_details d ON d.order_id=o.id
        WHERE o.user_id=? AND d.product_id=? AND o.status='completed' AND d.quantity>0";

    private function rows(string $sql, string $types, array $values): array
    {
        $stmt=$this->conn->prepare($sql);
        try {
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $result=$stmt->get_result();
            return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        } finally { $stmt->close(); }
    }

    public function purchased(int $user, int $product): bool
    {
        return (bool)$this->rows(self::PURCHASE.' LIMIT 1', 'ii', [$user,$product]);
    }

    public function exists(int $product): bool
    {
        return (bool)$this->rows('SELECT id FROM products WHERE id=?', 'i', [$product]);
    }

    public function summary(int $product): array
    {
        return $this->rows('SELECT COUNT(*) AS total, COALESCE(AVG(rating),0) AS average
            FROM product_reviews WHERE product_id=?', 'i', [$product])[0];
    }

    public function page(int $product, int $offset): array
    {
        return $this->rows('SELECT r.id, r.rating, r.comment, r.created_at, r.updated_at, u.fullname
            FROM product_reviews r INNER JOIN users u ON u.id=r.user_id
            WHERE r.product_id=? ORDER BY r.id DESC LIMIT 10 OFFSET ?', 'ii', [$product,$offset]);
    }

    public function own(int $user, int $product): ?array
    {
        return $this->rows('SELECT rating, comment FROM product_reviews WHERE user_id=? AND product_id=?',
            'ii', [$user,$product])[0] ?? null;
    }

    public function save(int $user, int $product, int $rating, string $comment): void
    {
        // Recheck the purchase in the write itself; UNIQUE makes repeated saves idempotent.
        $this->rows('INSERT INTO product_reviews (user_id,product_id,rating,comment)
            SELECT ?,?,?,? WHERE EXISTS ('.self::PURCHASE.')
            ON DUPLICATE KEY UPDATE rating=VALUES(rating), comment=VALUES(comment)',
            'iiisii', [$user,$product,$rating,$comment,$user,$product]);
    }

    public function remove(int $user, int $product): void
    {
        $this->rows('DELETE FROM product_reviews WHERE user_id=? AND product_id=?', 'ii', [$user,$product]);
    }
}
