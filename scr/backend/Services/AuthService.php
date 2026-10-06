<?php
namespace MotoParts\App\Services;

use MotoParts\App\Models\UserAuth;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class AuthService
{
    private UserAuth $users;
    public function __construct(UserAuth $users) { $this->users = $users; }

    public static function publicInput(array $input): array
    {
        $data = [];
        foreach (['fullname', 'email', 'phone'] as $key) {
            $value = $input[$key] ?? '';
            $data[$key] = is_string($value) && mb_check_encoding($value, 'UTF-8') ? trim($value) : '';
        }
        $data['email'] = strtolower($data['email']);
        $data['phone'] = preg_replace('/\s+/u', '', $data['phone']);
        return $data;
    }

    public function register(array $input): int
    {
        $data = self::publicInput($input);
        if ($data['fullname'] === '' || mb_strlen($data['fullname'], 'UTF-8') > 100) {
            throw new \DomainException('Họ và tên là bắt buộc và không được vượt quá 100 ký tự.');
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 100) {
            throw new \DomainException('Địa chỉ email không hợp lệ.');
        }
        if (!preg_match('/\A[0-9]{9,11}\z/', $data['phone'])) {
            throw new \DomainException('Số điện thoại phải có từ 9 đến 11 chữ số.');
        }
        $password = $input['password'] ?? null;
        if (!is_string($password) || strlen($password) < 6 || strlen($password) > 72 || strpos($password, "\0") !== false) {
            throw new \DomainException('Mật khẩu phải dài từ 6 đến 72 byte và không chứa ký tự null.');
        }
        if ($password !== ($input['password_confirm'] ?? null)) throw new \DomainException('Hai mật khẩu không trùng khớp.');
        if ($this->users->emailExists($data['email'])) throw new \DomainException('Email này đã được sử dụng.');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            return $this->users->createCustomer($data['fullname'], $data['email'], $data['phone'], $hash);
        } catch (\mysqli_sql_exception $exception) {
            // The existing unique email index also protects concurrent registrations.
            if ((int) $exception->getCode() === 1062) throw new \DomainException('Email này đã được sử dụng.');
            throw $exception;
        }
    }

    public function login(array $input): array
    {
        $email = self::publicInput($input)['email'];
        $password = $input['password'] ?? null;
        $failure = 'Email hoặc mật khẩu không chính xác.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100 || !is_string($password)
            || $password === '' || strpos($password, "\0") !== false) throw new \DomainException($failure);
        $user = $this->users->findByEmail($email);
        if (!$user || !password_verify($password, $user['password']) || !in_array($user['role'], ['customer', 'admin'], true)) {
            throw new \DomainException($failure);
        }
        return self::publicUser($user);
    }

    public static function publicUser(array $user): array
    {
        return ['id' => (int) $user['id'], 'fullname' => $user['fullname'], 'email' => $user['email'],
            'phone' => $user['phone'], 'role' => $user['role']];
    }
}
