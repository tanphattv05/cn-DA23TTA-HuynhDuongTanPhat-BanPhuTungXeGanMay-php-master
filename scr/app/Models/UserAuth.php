<?php
namespace MotoParts\App\Models;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class UserAuth
{
    private \mysqli $conn;

    public function __construct(\mysqli $conn) { $this->conn = $conn; }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->conn->prepare('SELECT id, fullname, email, phone, role, password FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT id, fullname, email, phone, role FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() !== null;
    }

    public function createCustomer(string $name, string $email, string $phone, string $hash): int
    {
        $stmt = $this->conn->prepare("INSERT INTO users (fullname, email, phone, password, role) VALUES (?, ?, ?, ?, 'customer')");
        $stmt->bind_param('ssss', $name, $email, $phone, $hash);
        $stmt->execute();
        return (int) $this->conn->insert_id;
    }
}
