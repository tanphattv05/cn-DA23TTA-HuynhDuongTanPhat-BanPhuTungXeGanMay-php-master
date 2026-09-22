<?php
namespace MotoParts\App\Controllers\Storefront;

use MotoParts\App\Core\View;
use MotoParts\App\Models\StorefrontProduct;

if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

final class ProductController
{
    public function __construct()
    {
        ini_set('display_errors', '0');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    private function model(): StorefrontProduct
    {
        // Public catalog: use the existing connection, never the Admin auth bootstrap.
        require dirname(__DIR__, 3) . '/config/database.php';
        return new StorefrontProduct($conn);
    }

    private function presentation(array $product): array
    {
        $product['image_url'] = '../assets/images/products/' . rawurlencode(basename((string) ($product['image'] ?? '')));
        return $product;
    }

    private function unavailable(\Throwable $exception): string
    {
        // Log a diagnostic class/code only: exception messages can contain SQL or credentials.
        error_log('MotoParts storefront products: ' . get_class($exception) . ' code=' . (int) $exception->getCode());
        http_response_code(503);
        return 'Không thể tải sản phẩm. Vui lòng thử lại sau.';
    }

    public function index(): void
    {
        $products = [];
        $error = '';
        try {
            foreach ($this->model()->all() as $product) {
                $products[] = $this->presentation($product);
            }
        } catch (\Throwable $exception) {
            $error = $this->unavailable($exception);
        }
        View::storefront('storefront/products/index', compact('products', 'error'));
    }

    public function detail(): void
    {
        $input = $_GET['id'] ?? null;
        $id = is_string($input) && preg_match('/\A[1-9][0-9]{0,9}\z/', $input)
            ? filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]])
            : false;
        $product = null;
        $error = '';
        $canAddToCart = false;
        if (!$id) {
            http_response_code(404);
        } else {
            try {
                $product = $this->model()->find($id);
                if (!$product) {
                    http_response_code(404);
                } else {
                    $product = $this->presentation($product);
                    $canAddToCart = (int) $product['stock'] > 0;
                }
            } catch (\Throwable $exception) {
                $error = $this->unavailable($exception);
            }
        }
        View::storefront('storefront/products/detail', compact('product', 'error', 'canAddToCart'));
    }
}