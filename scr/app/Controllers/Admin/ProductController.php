<?php
namespace MotoParts\App\Controllers\Admin;

use MotoParts\App\Core\View;
use MotoParts\App\Models\Category;
use MotoParts\App\Models\Product;

if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

final class ProductController
{
    private \mysqli $connection;
    private Product $products;
    private Category $categories;

    public function __construct()
    {
        global $conn, $baseUrl;
        require_once dirname(__DIR__, 3) . '/admin/includes/product-bootstrap.php';
        require_once dirname(__DIR__, 3) . '/admin/includes/product-upload.php';
        $this->connection = $conn;
        $this->products = new Product($conn);
        $this->categories = new Category($conn);
    }

    public function index(): void
    {
        $q = mb_substr(product_text($_GET, 'q'), 0, 255, 'UTF-8');
        $categoryId = filter_var(product_text($_GET, 'category'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) ?: 0;
        $page = filter_var(product_text($_GET, 'page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $error = '';
        $rows = $categories = [];
        $total = 0;
        $pages = 1;
        try {
            $categories = $this->categories->options();
            $total = $this->products->count($q, $categoryId);
            $pages = max(1, (int) ceil($total / 10));
            $page = min($page, $pages);
            $rows = $this->products->paginate($q, $categoryId, 10, ($page - 1) * 10);
        } catch (\Throwable $e) {
            http_response_code(503);
            $error = 'Không thể tải danh sách sản phẩm. Vui lòng thử lại sau.';
        }
        $success = $_SESSION['product_success'] ?? '';
        unset($_SESSION['product_success']);
        $pageTitle = 'Quản lý sản phẩm';
        View::admin('admin/products/index', compact('q', 'categoryId', 'page', 'pages', 'rows', 'categories', 'total', 'error', 'success', 'pageTitle'));
    }

    public function form(): void
    {
        $id = null;
        if (isset($_GET['id'])) {
            $id = is_string($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) : false;
            if (!$id) { http_response_code(404); exit('Sản phẩm không tồn tại.'); }
        }
        $data = array_fill_keys(['name', 'category_id', 'price', 'brand', 'stock', 'description', 'image'], '');
        $data['stock'] = '0';
        try {
            if ($id) {
                $data = $this->products->find($id);
                if (!$data) { http_response_code(404); exit('Sản phẩm không tồn tại.'); }
            }
            $categories = $this->categories->options();
        } catch (\Throwable $e) {
            http_response_code(503);
            exit('Không thể tải dữ liệu sản phẩm. Vui lòng thử lại sau.');
        }
        $errors = [];
        $flash = $_SESSION['product_form'] ?? null;
        unset($_SESSION['product_form']);
        if ($flash && $flash['id'] === $id) {
            $data = array_replace($data, $flash['data']);
            $errors = $flash['errors'];
        }
        $pageTitle = $id ? 'Sửa sản phẩm' : 'Thêm sản phẩm';
        $csrfToken = $_SESSION['csrf_token'];
        View::admin('admin/products/form', compact('id', 'data', 'categories', 'errors', 'pageTitle', 'csrfToken'));
    }

    public function save(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit('Chỉ chấp nhận POST.');
        }
        $idInput = product_text($_POST, 'id');
        $id = filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        $destination = 'product-form.php' . ($id ? '?id=' . $id : '');
        [$data, $errors] = product_validate($_POST);
        if ($idInput !== '' && !$id) $errors[] = 'Mã sản phẩm không hợp lệ.';
        if (!isset($_POST['csrf_token']) || !is_string($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $errors[] = 'Phiên gửi biểu mẫu không hợp lệ hoặc dữ liệu quá lớn. Vui lòng thử lại.';
        }
        $newImage = null;
        $transaction = false;
        try {
            if (!$errors) {
                $this->connection->begin_transaction();
                $transaction = true;
                $current = null;
                if ($id) {
                    $current = $this->products->find($id, true);
                    if (!$current) $errors[] = 'Sản phẩm không còn tồn tại.';
                }
                if (!$this->categories->findShared((int) $data['category_id'])) $errors[] = 'Danh mục không tồn tại.';
                if (!$errors) {
                    $newImage = product_upload($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
                    $image = $newImage ?? ($current['image'] ?? '');
                    if ($id) {
                        $this->products->update($id, $data, $image);
                    } else {
                        $this->products->create($data, $image);
                    }
                    $this->connection->commit();
                    $transaction = false;
                    $_SESSION['product_success'] = $id ? 'Đã cập nhật sản phẩm.' : 'Đã thêm sản phẩm.';
                    unset($_SESSION['product_form']);
                    product_redirect('products.php');
                }
            }
        } catch (\mysqli_sql_exception $e) {
            $errors[] = 'Không thể lưu sản phẩm. Vui lòng thử lại sau.';
        } catch (\RuntimeException $e) {
            $errors[] = $e->getMessage();
        } catch (\Throwable $e) {
            $errors[] = 'Không thể xử lý sản phẩm. Vui lòng thử lại sau.';
        }
        if ($transaction) {
            try { $this->connection->rollback(); } catch (\Throwable $e) {}
        }
        // Only remove this request's newly uploaded image, never the previous image.
        if ($newImage !== null) @unlink(dirname(__DIR__, 3) . '/assets/images/products/' . $newImage);
        $_SESSION['product_form'] = ['id' => $id ?: null, 'data' => $data, 'errors' => $errors];
        product_redirect($destination);
    }
}
