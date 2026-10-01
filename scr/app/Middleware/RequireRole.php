<?php
namespace MotoParts\App\Middleware;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class RequireRole
{
    public static function enforce(string $role): array
    {
        $user = Authenticate::requireUser();
        if ($user['role'] !== $role) Authenticate::error(403, 'Bạn không có quyền truy cập trang quản trị.');
        return $user;
    }
}
