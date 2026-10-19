<?php
namespace MotoParts\App\Models;
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class ShippingAddress
{
    public function __construct(private \mysqli $db) {}
    private function query(string $sql, string $types, array $values): \mysqli_stmt
    {
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        return $stmt;
    }
    public function all(int $userId): array
    {
        return $this->query('SELECT id, fullname, phone, address, is_default FROM shipping_addresses WHERE user_id=? ORDER BY is_default DESC, id', 'i', [$userId])->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    public function owned(int $id, int $userId): ?array
    {
        return $this->query('SELECT id, fullname, phone, address, is_default FROM shipping_addresses WHERE id=? AND user_id=?', 'ii', [$id, $userId])->get_result()->fetch_assoc();
    }
    public function save(int $userId, string $operation, ?int $id, array $data, bool $makeDefault): void
    {
        $this->db->begin_transaction();
        try {
            // Serialize address mutations for one account, including the first insert.
            $user = $this->query("SELECT id FROM users WHERE id=? AND role='customer' FOR UPDATE", 'i', [$userId])->get_result()->fetch_assoc();
            if (!$user) throw new \DomainException('Tài khoản không hợp lệ.');
            if ($id !== null && !$this->owned($id, $userId)) throw new \DomainException('Địa chỉ không tồn tại hoặc không thuộc tài khoản của bạn.');
            if ($operation === 'save') {
                if ($id === null) {
                    $this->query('INSERT INTO shipping_addresses(user_id,fullname,phone,address) VALUES(?,?,?,?)', 'isss', [$userId, $data['fullname'], $data['phone'], $data['address']]);
                    $id = $this->db->insert_id;
                } else {
                    $this->query('UPDATE shipping_addresses SET fullname=?,phone=?,address=? WHERE id=? AND user_id=?', 'sssii', [$data['fullname'], $data['phone'], $data['address'], $id, $userId]);
                }
            } elseif ($operation === 'delete' && $id !== null) {
                $this->query('DELETE FROM shipping_addresses WHERE id=? AND user_id=?', 'ii', [$id, $userId]);
            } elseif ($operation !== 'default' || $id === null) throw new \DomainException('Thao tác không hợp lệ.');
            if ($operation === 'default' || ($operation === 'save' && $makeDefault)) {
                $this->query('UPDATE shipping_addresses SET is_default=0 WHERE user_id=? AND is_default=1', 'i', [$userId]);
                $this->query('UPDATE shipping_addresses SET is_default=1 WHERE id=? AND user_id=?', 'ii', [$id, $userId]);
            }
            $rows = $this->all($userId);
            // First address becomes default; deleting default promotes oldest remaining.
            if ($rows && !(int) $rows[0]['is_default']) {
                $this->query('UPDATE shipping_addresses SET is_default=1 WHERE id=? AND user_id=?', 'ii', [(int) $rows[0]['id'], $userId]);
            }
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollback(); throw $e; }
    }
}
