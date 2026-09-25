<?php
namespace MotoParts\App\Services;

use MotoParts\App\Models\Checkout;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class CheckoutService
{
    private Checkout $model;
    private const MAX_CENTS = 999999999999; // DECIMAL(12,2)

    public function __construct(Checkout $model)
    {
        $this->model = $model;
    }

    public static function normalize($input): array
    {
        if (!is_array($input) || !$input) throw new \DomainException('Giỏ hàng đang trống.');
        $cart = [];
        foreach ($input as $key => $value) {
            $id = CartService::integer($key);
            $quantity = CartService::integer($value);
            if ($id === null || $quantity === null) throw new \DomainException('Dữ liệu giỏ hàng không hợp lệ.');
            $cart[$id] = $quantity;
        }
        ksort($cart, SORT_NUMERIC);
        return $cart;
    }

    private static function decimal(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function summarize(array $cart, array $products): array
    {
        $byId = array_column($products, null, 'id');
        $rows = [];
        $total = 0;
        foreach ($cart as $id => $quantity) {
            if (!isset($byId[$id])) throw new \DomainException('Một sản phẩm trong giỏ hàng không còn tồn tại.');
            $product = $byId[$id];
            if ((int) $product['stock'] < 1) throw new \DomainException('Sản phẩm "' . $product['name'] . '" đã hết hàng.');
            if ((int) $product['stock'] < $quantity) throw new \DomainException('Sản phẩm "' . $product['name'] . '" không còn đủ số lượng.');
            if (!preg_match('/\A([0-9]{1,10})\.([0-9]{2})\z/', (string) $product['price'], $parts)) {
                throw new \DomainException('Giá sản phẩm không hợp lệ. Vui lòng liên hệ cửa hàng.');
            }
            $price = (int) $parts[1] * 100 + (int) $parts[2];
            // Check BEFORE multiplying: huge quantities must not overflow PHP integers.
            if ($price > intdiv(self::MAX_CENTS - $total, $quantity)) {
                throw new \DomainException('Tổng tiền vượt giới hạn cho phép. Vui lòng giảm số lượng.');
            }
            $subtotal = $price * $quantity;
            $total += $subtotal;
            $product['quantity'] = $quantity;
            $product['subtotal'] = self::decimal($subtotal);
            $rows[] = $product;
        }
        return [$rows, self::decimal($total)];
    }

    public function place($input, array $recipient, ?int $userId): int
    {
        $cart = self::normalize($input);
        try {
            $this->model->begin();
            if ($userId !== null && !$this->model->user($userId, true)) {
                throw new \DomainException('Tài khoản không còn hợp lệ. Vui lòng đăng nhập lại.');
            }
            $products = [];
            // One exact-key locking read per ID, in deterministic ascending order.
            foreach ($cart as $id => $quantity) {
                $product = $this->model->lockProduct($id);
                if (!$product) throw new \DomainException('Một sản phẩm trong giỏ hàng không còn tồn tại.');
                $products[] = $product;
            }
            [$rows, $total] = $this->summarize($cart, $products);
            $orderId = $this->model->create($userId, $recipient, $total);
            foreach ($rows as $product) {
                $this->model->addDetail($orderId, (int) $product['id'], $product['quantity'], $product['price']);
                $this->model->deduct((int) $product['id'], $product['quantity']);
            }
            $this->model->commit();
            return $orderId;
        } catch (\Throwable $exception) {
            try { $this->model->rollback(); } catch (\Throwable $rollbackError) {}
            throw $exception;
        }
    }
}