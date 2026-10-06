<?php

namespace Tests\Unit\Web;

use PHPUnit\Framework\TestCase;

use Web\Shop\Cart;

/**
 * Web\Shop\Cart - session дээрх зочны сагсны тест.
 *
 * Сагс нь зөвхөн [product_id => quantity] хадгалдаг тул session руу
 * хэвийн бус өгөгдөл (сөрөг тоо, ID биш key, хэт олон мөр) орж
 * ирэхгүй байх нь гол шаардлага.
 */
class CartTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testNormalizeDropsInvalidLines(): void
    {
        $this->assertSame(
            [5 => 2, 7 => 1],
            Cart::normalize([5 => 2, 0 => 3, -1 => 1, 'x' => 1, 7 => '1', 8 => 0, 9 => -4, 10 => 'abc'])
        );
        $this->assertSame([], Cart::normalize('not-an-array'));
        $this->assertSame([], Cart::normalize(null));
    }

    public function testNormalizeCapsQuantityAndLines(): void
    {
        $this->assertSame([3 => Cart::MAX_QUANTITY], Cart::normalize([3 => Cart::MAX_QUANTITY + 100]));

        $many = [];
        for ($i = 1; $i <= Cart::MAX_LINES + 10; $i++) {
            $many[$i] = 1;
        }
        $this->assertCount(Cart::MAX_LINES, Cart::normalize($many));
    }

    public function testAddAccumulatesAndCounts(): void
    {
        Cart::add(4, 2);
        Cart::add(4, 3);
        Cart::add(9, 1);
        $this->assertSame([4 => 5, 9 => 1], Cart::lines());
        $this->assertSame(6, Cart::count());
    }

    public function testSetZeroRemovesLineAndEmptyCartIsUnset(): void
    {
        Cart::add(4, 2);
        Cart::set(4, 0);
        $this->assertSame([], Cart::lines());
        $this->assertArrayNotHasKey(Cart::SESSION_KEY, $_SESSION);
    }

    public function testTamperedSessionIsNormalizedOnRead(): void
    {
        $_SESSION[Cart::SESSION_KEY] = [12 => '3', 'evil' => 99, 13 => -2];
        $this->assertSame([12 => 3], Cart::lines());
        $this->assertSame(3, Cart::count());
    }

    public function testClear(): void
    {
        Cart::add(1, 1);
        Cart::clear();
        $this->assertSame(0, Cart::count());
    }
}
