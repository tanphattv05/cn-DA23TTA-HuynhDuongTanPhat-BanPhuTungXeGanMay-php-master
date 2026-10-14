<?php
namespace MotoParts\App\Controllers\Storefront;

use MotoParts\App\Core\CartCsrf;
use MotoParts\App\Core\View;
use MotoParts\App\Middleware\Authenticate;
use MotoParts\App\Models\Favorite;
use MotoParts\App\Services\CartService;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class FavoriteController
{
    private static function model(): Favorite { return new Favorite(Authenticate::connection()); }

    private static function customer(): array
    {
        $user = Authenticate::requireUser();
        if ($user['role'] !== 'customer') Authenticate::error(403, 'Chỉ khách hàng được sử dụng sản phẩm yêu thích.');
        return $user;
    }

    private static function failure(\Throwable $error): string
    {
        error_log('MotoParts favorites: '.get_class($error).' code='.(int)$error->getCode());
        return 'Tính năng yêu thích chưa sẵn sàng. Vui lòng thử lại sau.';
    }

    // One batched query for the displayed products; unavailable favorites never break catalog.
    public static function context(array $productIds): array
    {
        $state = ['role'=>'guest', 'ids'=>[], 'token'=>'', 'error'=>''];
        try {
            $user = Authenticate::current();
            if (!$user) return $state;
            $state['role'] = $user['role'];
            if ($user['role'] === 'customer') {
                $state['ids'] = self::model()->savedIds((int)$user['id'], $productIds);
                $state['token'] = CartCsrf::token();
            }
        } catch (\Throwable $error) { $state['error'] = self::failure($error); }
        return $state;
    }

    public function index(): void
    {
        $user = self::customer();
        $products = []; $error = '';
        $flash = $_SESSION['favorite_flash'] ?? '';
        unset($_SESSION['favorite_flash']);
        $csrfToken = CartCsrf::token();
        $favoriteState = ['role'=>'customer', 'ids'=>[], 'token'=>$csrfToken, 'error'=>''];
        try {
            $products = self::model()->products((int)$user['id']);
            foreach ($products as &$product) {
                $product['image_url'] = '../assets/images/products/'.rawurlencode(basename((string)$product['image']));
            }
            unset($product);
            $favoriteState['ids'] = array_map('intval', array_column($products, 'id'));
        } catch (\Throwable $exception) {
            http_response_code(503); $error = self::failure($exception);
        }
        View::storefront('storefront/favorites/index', compact('products','error','flash','csrfToken','favoriteState'));
    }

    public function toggle(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST'); Authenticate::error(405, 'Vui lòng gửi biểu mẫu để thay đổi yêu thích.');
        }
        $user = self::customer();
        if (!CartCsrf::valid($_POST['csrf_token'] ?? null)) Authenticate::error(403, 'Phiên gửi biểu mẫu không hợp lệ.');
        $id = CartService::integer($_POST['product_id'] ?? null);
        $operation = $_POST['operation'] ?? null;
        if ($id === null || !in_array($operation, ['add','remove'], true)) {
            Authenticate::error(400, 'Sản phẩm hoặc thao tác không hợp lệ.');
        }
        try {
            $model = self::model();
            if (!$model->exists($id)) Authenticate::error(404, 'Sản phẩm không còn tồn tại.');
            $model->set((int)$user['id'], $id, $operation === 'add');
            $_SESSION['favorite_flash'] = $operation === 'add' ? 'Đã lưu sản phẩm yêu thích.' : 'Đã bỏ sản phẩm yêu thích.';
        } catch (\Throwable $exception) {
            $_SESSION['favorite_flash'] = self::failure($exception);
        }
        // Only code-owned destinations. Ignore any posted URL or user_id.
        $target = ($_POST['source'] ?? '') === 'product' ? 'product-detail.php?id='.$id : 'favorites.php';
        header('Location: '.Authenticate::BASE_URL.'/pages/'.$target, true, 303);
        exit;
    }
}
