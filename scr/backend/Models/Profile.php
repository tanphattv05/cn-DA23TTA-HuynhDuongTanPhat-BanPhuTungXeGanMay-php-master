<?php
namespace MotoParts\App\Models;
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class Profile
{
    public function __construct(private \mysqli $db) {}

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $stmt->bind_param('si', $data['email'], $id);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) throw new \DomainException('Email này đã được sử dụng.');
        $stmt = $this->db->prepare("UPDATE users SET fullname=?, email=?, phone=? WHERE id=? AND role='customer'");
        $stmt->bind_param('sssi', $data['fullname'], $data['email'], $data['phone'], $id);
        try { $stmt->execute(); }
        catch (\mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) throw new \DomainException('Email này đã được sử dụng.');
            throw $e;
        }
    }

    public function changePassword(int $id, string $current, string $next): void
    {
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id=? AND role='customer'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $hash = $stmt->get_result()->fetch_assoc()['password'] ?? '';
        if (!password_verify($current, $hash)) throw new \DomainException('Mật khẩu hiện tại không chính xác.');
        $newHash = password_hash($next, PASSWORD_DEFAULT);
        // Compare-and-swap prevents a concurrent password change from being overwritten.
        $stmt = $this->db->prepare("UPDATE users SET password=? WHERE id=? AND role='customer' AND password=?");
        $stmt->bind_param('sis', $newHash, $id, $hash);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) throw new \DomainException('Tài khoản đã thay đổi. Vui lòng thử lại.');
    }
}
