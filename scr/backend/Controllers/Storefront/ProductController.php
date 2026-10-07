<?php
namespace MotoParts\App\Controllers\Storefront;

use MotoParts\App\Core\View;
use MotoParts\App\Core\CartCsrf;
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
        require dirname(__DIR__, 3) . '/backend/config/database.php';
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
        [$filters, $validationErrors, $page] = $this->catalogInput($_GET);
        $categories = []; $total = 0; $totalPages = 1; $pagination = [];
        try {
            $model = $this->model();
            $categories = $model->categories();
            if ($filters['category'] !== '' && !in_array($filters['category'], array_map('strval', array_column($categories, 'id')), true)) {
                $validationErrors['category'] = 'Danh mục không hợp lệ hoặc không còn tồn tại.';
            }
            if (!$validationErrors) {
                $total = $model->count($filters);
                $totalPages = max(1, (int) ceil($total / 12));
                $page = min($page, $totalPages);
                foreach ($model->page($filters, ($page - 1) * 12) as $product) {
                    $products[] = $this->presentation($product);
                }
                $pagination = $this->catalogPagination($filters, $page, $totalPages);
            } else {
                $page = 1;
            }
        } catch (\Throwable $exception) {
            $error = $this->unavailable($exception);
        }
        View::storefront('storefront/products/index', compact('products', 'error', 'filters', 'validationErrors', 'categories', 'total', 'totalPages', 'page', 'pagination'));
    }

    private function catalogInput(array $query): array
    {
        $defaults = ['q'=>'', 'category'=>'', 'min_price'=>'', 'max_price'=>'', 'stock'=>'all', 'sort'=>'newest'];
        $filters = $defaults; $errors = [];
        foreach ($defaults as $key => $default) {
            if (!isset($query[$key])) continue;
            if (!is_string($query[$key]) || !mb_check_encoding($query[$key], 'UTF-8')) {
                if (!in_array($key, ['stock','sort'], true)) $errors[$key] = 'Bộ lọc không hợp lệ. Vui lòng nhập lại.';
                continue;
            }
            $value = trim($query[$key]);
            $maxLength = $key === 'q' ? 100 : 32;
            if (mb_strlen($value, 'UTF-8') > $maxLength) {
                if (!in_array($key, ['stock','sort'], true)) $errors[$key] = $key === 'q' ? 'Từ khóa tối đa 100 ký tự.' : 'Giá trị bộ lọc quá dài.';
                $value = mb_substr($value, 0, $maxLength, 'UTF-8');
            }
            $filters[$key] = $value;
        }
        if ($filters['category'] !== '' && (!preg_match('/\A[1-9][0-9]{0,9}\z/', $filters['category']) || (int)$filters['category'] > 2147483647)) {
            $errors['category'] = 'Danh mục không hợp lệ.';
        }
        foreach (['min_price','max_price'] as $key) {
            if ($filters[$key] !== '' && !preg_match('/\A[0-9]{1,10}\z/', $filters[$key])) {
                $errors[$key] = 'Giá phải là số nguyên từ 0 đến 9.999.999.999, không dùng số mũ.';
            }
        }
        if (!isset($errors['min_price']) && !isset($errors['max_price'])
            && $filters['min_price'] !== '' && $filters['max_price'] !== '' && (int)$filters['min_price'] > (int)$filters['max_price']) {
            $errors['price_range'] = 'Giá tối thiểu không được lớn hơn giá tối đa.';
        }
        if (!in_array($filters['stock'], ['all','in_stock','out_of_stock'], true)) $filters['stock'] = 'all';
        if (!in_array($filters['sort'], ['newest','price_asc','price_desc','name_asc'], true)) $filters['sort'] = 'newest';
        $pageInput = $query['page'] ?? '';
        $page = is_string($pageInput) && preg_match('/\A[1-9][0-9]*\z/', $pageInput)
            ? filter_var($pageInput, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) : false;
        return [$filters, $errors, $page ?: 1];
    }

    private function catalogPagination(array $filters, int $page, int $totalPages): array
    {
        if ($totalPages <= 1) return [];
        $query = array_filter($filters, static fn($value) => $value !== '');
        if ($query['stock'] === 'all') unset($query['stock']);
        if ($query['sort'] === 'newest') unset($query['sort']);
        $url = static fn(int $number): string => 'products.php?' . http_build_query($query + ['page'=>$number], '', '&', PHP_QUERY_RFC3986);
        $links = [];
        if ($page > 1) $links[] = ['label'=>'Trước','url'=>$url($page-1),'current'=>false];
        $numbers = array_unique(array_merge([1], range(max(1,$page-2),min($totalPages,$page+2)), [$totalPages]));
        sort($numbers); $previous = 0;
        foreach ($numbers as $number) {
            if ($previous && $number > $previous+1) $links[] = ['label'=>'…','url'=>null,'current'=>false];
            $links[] = ['label'=>(string)$number,'url'=>$url($number),'current'=>$number===$page];
            $previous = $number;
        }
        if ($page < $totalPages) $links[] = ['label'=>'Sau','url'=>$url($page+1),'current'=>false];
        return $links;
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
        $csrfToken = CartCsrf::token();
        View::storefront('storefront/products/detail', compact('product', 'error', 'canAddToCart', 'csrfToken'));
    }
}
