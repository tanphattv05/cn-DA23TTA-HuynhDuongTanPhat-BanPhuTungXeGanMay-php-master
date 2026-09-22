<?php
namespace MotoParts\App\Controllers\Admin;

use MotoParts\App\Core\View;
use MotoParts\App\Models\Category;

if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

final class CategoryController
{
    private \mysqli $connection;
    private Category $categories;

    public function __construct()
    {
        // Existing auth.php runs before every action, including POST.
        // Globals preserve the legacy database/layout contract during migration.
        global $conn, $baseUrl;
        require_once dirname(__DIR__, 3) . '/admin/includes/category-input.php';
        $this->connection = $conn;
        $this->categories = new Category($conn);
    }

    public function index(): void
    {
        $q = mb_substr(product_text($_GET, 'q'), 0, 100, 'UTF-8');
        $page = filter_var(product_text($_GET, 'page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $rows = [];
        $total = 0;
        $pages = 1;
        $error = $_SESSION['category_error'] ?? '';
        $success = $_SESSION['category_success'] ?? '';
        unset($_SESSION['category_error'], $_SESSION['category_success']);
        try {
            $total = $this->categories->count($q);
            $pages = max(1, (int) ceil($total / 10));
            $page = min($page, $pages);
            $rows = $this->categories->paginate($q, 10, ($page - 1) * 10);
        } catch (\Throwable $e) {
            http_response_code(503);
            $error = 'Không thể tải danh sách danh mục. Vui lòng thử lại sau.';
        }
        $pageTitle = 'Quản lý danh mục';
        View::admin('admin/categories/index', compact('q', 'page', 'rows', 'total', 'pages', 'error', 'success', 'pageTitle'));
    }

    public function form(): void
    {
        $id = category_id($_GET);
        $data = ['name' => '', 'description' => ''];
        $errors = [];
        $unavailable = $id === false || (array_key_exists('id', $_GET) && $id === null);
        if ($unavailable) {
            http_response_code(404);
            $errors[] = 'Mã danh mục không hợp lệ.';
        } else {
            try {
                if ($id !== null) {
                    $category = $this->categories->find($id);
                    if (!$category) {
                        http_response_code(404);
                        $unavailable = true;
                        $errors[] = 'Danh mục không tồn tại.';
                    } else {
                        $data = $category;
                    }
                }
            } catch (\Throwable $e) {
                http_response_code(503);
                $unavailable = true;
                $errors[] = 'Không thể tải danh mục. Vui lòng thử lại sau.';
            }
        }
        $flash = $_SESSION['category_form'] ?? null;
        if ($flash && $flash['id'] === $id) {
            unset($_SESSION['category_form']);
            if (!$unavailable) {
                $data = $flash['data'];
                $errors = $flash['errors'];
            }
        }
        $pageTitle = $id ? 'Sửa danh mục' : 'Thêm danh mục';
        $csrfToken = $_SESSION['csrf_token'];
        View::admin('admin/categories/form', compact('id', 'data', 'errors', 'unavailable', 'pageTitle', 'csrfToken'));
    }

    public function save(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit('Chỉ chấp nhận POST.');
        }
        $id = category_id($_POST);
        if ($id === false || !array_key_exists('id', $_POST)) {
            $_SESSION['category_error'] = 'Mã danh mục không hợp lệ. Không có dữ liệu nào được lưu.';
            product_redirect('categories.php');
        }
        [$data, $errors] = category_validate($_POST);
        if (!isset($_POST['csrf_token']) || !is_string($_POST['csrf_token'])
            || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $errors[] = 'Phiên gửi biểu mẫu không hợp lệ. Vui lòng thử lại.';
        }
        $transaction = false;
        $missing = false;
        try {
            if (!$errors) {
                $this->connection->begin_transaction();
                $transaction = true;
                if ($id !== null && !$this->categories->find($id, true)) {
                    $missing = true;
                }
                if (!$missing) {
                    if ($this->categories->nameExists($data['name'], $id)) {
                        $errors[] = 'Tên danh mục đã tồn tại. Vui lòng chọn tên khác.';
                    } else {
                        if ($id === null) {
                            $this->categories->create($data['name'], $data['description']);
                        } else {
                            $this->categories->update($id, $data['name'], $data['description']);
                        }
                        $this->connection->commit();
                        $transaction = false;
                        unset($_SESSION['category_form']);
                        $_SESSION['category_success'] = $id === null ? 'Đã thêm danh mục.' : 'Đã cập nhật danh mục.';
                        product_redirect('categories.php');
                    }
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'Không thể lưu danh mục. Vui lòng thử lại sau.';
        }
        if ($transaction) {
            try { $this->connection->rollback(); } catch (\Throwable $e) {}
        }
        if ($missing) {
            $_SESSION['category_error'] = 'Danh mục không tồn tại. Không có dữ liệu nào được lưu.';
            product_redirect('categories.php');
        }
        $_SESSION['category_form'] = ['id' => $id, 'data' => $data, 'errors' => $errors];
        product_redirect('category-form.php' . ($id !== null ? '?id=' . $id : ''));
    }
}
