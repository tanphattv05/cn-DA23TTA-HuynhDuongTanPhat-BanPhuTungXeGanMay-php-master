<?php
namespace MotoParts\App\Controllers\Storefront;

use MotoParts\App\Core\CartCsrf;
use MotoParts\App\Middleware\Authenticate;
use MotoParts\App\Models\ProductReview;
use MotoParts\App\Services\CartService;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class ReviewController
{
    private static function model(): ProductReview { return new ProductReview(Authenticate::connection()); }

    private static function failure(\Throwable $error): string
    {
        error_log('MotoParts reviews: '.get_class($error).' code='.(int)$error->getCode());
        return 'Đánh giá chưa sẵn sàng. Vui lòng thử lại sau.';
    }

    public static function context(int $product, mixed $inputPage): array
    {
        $state=['error'=>'','rows'=>[],'total'=>0,'average'=>0,'page'=>1,'pages'=>1,
            'role'=>'guest','eligible'=>false,'own'=>null,'token'=>'','flash'=>''];
        try {
            $user=Authenticate::current();
            $state['role']=$user['role'] ?? 'guest';
            $model=self::model();
            $summary=$model->summary($product);
            $state['total']=(int)$summary['total'];
            $state['average']=$summary['average'];
            $state['pages']=max(1,(int)ceil($state['total']/10));
            $state['page']=min(CartService::integer($inputPage) ?? 1,$state['pages']);
            $state['rows']=$model->page($product,($state['page']-1)*10);
            if ($state['role']==='customer') {
                $state['eligible']=$model->purchased((int)$user['id'],$product);
                $state['own']=$model->own((int)$user['id'],$product);
                $state['token']=CartCsrf::token();
            }
            if (($_SESSION['review_flash']['product'] ?? null)===$product) {
                $state['flash']=$_SESSION['review_flash']['message'];
                unset($_SESSION['review_flash']);
            }
        } catch (\Throwable $error) { $state['error']=self::failure($error); }
        return $state;
    }

    public function submit(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') {
            header('Allow: POST'); Authenticate::error(405,'Vui lòng gửi biểu mẫu đánh giá.');
        }
        $user=Authenticate::requireUser();
        if ($user['role']!=='customer') Authenticate::error(403,'Chỉ khách hàng được gửi đánh giá.');
        if (!CartCsrf::valid($_POST['csrf_token'] ?? null)) Authenticate::error(403,'Phiên gửi biểu mẫu không hợp lệ.');
        $product=CartService::integer($_POST['product_id'] ?? null);
        $operation=$_POST['operation'] ?? null;
        if ($product===null || !in_array($operation,['save','delete'],true)) Authenticate::error(400,'Sản phẩm hoặc thao tác không hợp lệ.');
        $rating=CartService::integer($_POST['rating'] ?? null);
        $comment=$_POST['comment'] ?? '';
        if ($operation==='save') {
            if ($rating===null || $rating>5 || !is_string($comment) || !mb_check_encoding($comment,'UTF-8')) {
                Authenticate::error(400,'Số sao phải là số nguyên từ 1 đến 5 và nhận xét phải hợp lệ.');
            }
            $comment=trim($comment);
            if (mb_strlen($comment,'UTF-8')>1000) Authenticate::error(400,'Nhận xét tối đa 1000 ký tự.');
        }
        try {
            $model=self::model();
            if (!$model->exists($product)) Authenticate::error(404,'Sản phẩm không còn tồn tại.');
            if ($operation==='save') {
                if (!$model->purchased((int)$user['id'],$product)) Authenticate::error(403,'Chỉ được đánh giá sản phẩm đã mua trong đơn hoàn thành.');
                $model->save((int)$user['id'],$product,$rating,$comment);
            } else {
                $model->remove((int)$user['id'],$product);
            }
            $message=$operation==='save' ? 'Đã lưu đánh giá của bạn.' : 'Đã xóa đánh giá của bạn.';
        } catch (\Throwable $error) { $message=self::failure($error); }
        $_SESSION['review_flash']=['product'=>$product,'message'=>$message];
        header('Location: '.Authenticate::BASE_URL.'/pages/product-detail.php?id='.$product.'#product-reviews',true,303);
        exit;
    }
}
