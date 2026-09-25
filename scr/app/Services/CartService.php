<?php
namespace MotoParts\App\Services;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class CartService
{
    private array $session;

    public function __construct(array &$session)
    {
        $this->session =& $session;
    }

    public static function integer($value, bool $allowZero = false): ?int
    {
        if (!is_int($value) && !is_string($value)) return null;
        if (!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/', (string) $value)) return null;
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => [
            'min_range' => $allowZero ? 0 : 1, 'max_range' => 2147483647
        ]]);
        return $number === false ? null : $number;
    }

    public function read(): array
    {
        $cart = [];
        foreach (is_array($this->session['cart'] ?? null) ? $this->session['cart'] : [] as $key => $value) {
            $id = self::integer($key);
            $quantity = self::integer($value);
            if ($id !== null && $quantity !== null) $cart[$id] = $quantity;
        }
        return $cart;
    }

    public function totalQuantity(): int
    {
        return array_sum($this->read());
    }

    public function add(array $product, int $quantity): bool
    {
        $cart = $this->read();
        $id = (int) $product['id'];
        $wanted = ($cart[$id] ?? 0) + $quantity;
        $cart[$id] = min($wanted, (int) $product['stock']);
        $this->session['cart'] = $cart;
        return $wanted > (int) $product['stock'];
    }

    public function update(array $quantities, array $products): void
    {
        $cart = $this->read();
        $byId = array_column($products, null, 'id');
        // Validate every line before replacing the session cart.
        foreach ($quantities as $id => $quantity) {
            if (!isset($cart[$id])) throw new \InvalidArgumentException('Sản phẩm không có trong giỏ hàng.');
            if ($quantity === 0) {
                unset($cart[$id]);
                continue;
            }
            if (!isset($byId[$id])) throw new \InvalidArgumentException('Sản phẩm không còn tồn tại.');
            if ((int) $byId[$id]['stock'] < 1) throw new \InvalidArgumentException('Sản phẩm đã hết hàng.');
            if ($quantity > (int) $byId[$id]['stock']) throw new \InvalidArgumentException('Số lượng vượt tồn kho. Giỏ hàng chưa được cập nhật.');
            $cart[$id] = $quantity;
        }
        $this->session['cart'] = $cart;
    }

    public function remove(int $id): bool
    {
        $cart = $this->read();
        if (!isset($cart[$id])) return false;
        unset($cart[$id]);
        $this->session['cart'] = $cart;
        return true;
    }

    public function reconcile(array $products): array
    {
        $cart = $this->read();
        $next = [];
        $rows = [];
        $total = 0;
        foreach ($products as $product) {
            $id = (int) $product['id'];
            $quantity = min($cart[$id] ?? 0, max(0, (int) $product['stock']));
            if ($quantity < 1) continue;
            $next[$id] = $quantity;
            $product['quantity'] = $quantity;
            $product['subtotal'] = (float) $product['price'] * $quantity;
            $total += $product['subtotal'];
            $rows[] = $product;
        }
        $changed = $next != ($this->session['cart'] ?? []);
        $this->session['cart'] = $next;
        return [$rows, $total, $changed];
    }
}