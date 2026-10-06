<?php

namespace Web\Shop;

/**
 * Class Cart
 * ---------------------------------------------------------------
 * Зочны сагс - session дээр хадгалагдах энгийн [product_id => quantity] map.
 *
 * Сагсанд зөвхөн бүтээгдэхүүний ID болон тоо ширхэг хадгална. Нэр, үнэ,
 * үлдэгдэл, нийтлэгдсэн эсэхийг сагсыг харуулах болон захиалга илгээх
 * бүрд DB-ээс шинээр уншина (ShopController::resolveItems) - session дахь
 * өгөгдөлд үнэ итгэхгүй.
 *
 * Session-д бичих нь зөвхөн /session/ prefix-тэй route дээр боломжтой
 * (SessionMiddleware), бусад route-д сагсыг зөвхөн уншина.
 *
 * Хэмжээний хязгаар (session-ийг хэт томруулахгүй):
 *   - MAX_LINES    - сагсан дахь өөр бүтээгдэхүүний дээд тоо
 *   - MAX_QUANTITY - нэг бүтээгдэхүүний дээд тоо ширхэг
 *
 * @package Web\Shop
 */
final class Cart
{
    /** Session key. Dashboard-той нэг session хуваалцдаг тул RAPTOR_WEB_ prefix-тэй. */
    public const SESSION_KEY = 'RAPTOR_WEB_CART';

    public const MAX_LINES = 50;

    public const MAX_QUANTITY = 999;

    /**
     * Сагсны мөрүүд.
     *
     * @return array<int,int> [product_id => quantity]
     */
    public static function lines(): array
    {
        return self::normalize($_SESSION[self::SESSION_KEY] ?? []);
    }

    /**
     * Сагсан дахь нийт тоо ширхэг (navbar-ын badge).
     */
    public static function count(): int
    {
        return \array_sum(self::lines());
    }

    /**
     * Бүтээгдэхүүн нэмэх (байгаа бол тоо ширхэгийг нэмэгдүүлнэ).
     */
    public static function add(int $productId, int $quantity): void
    {
        $lines = self::lines();
        $lines[$productId] = ($lines[$productId] ?? 0) + $quantity;
        self::save($lines);
    }

    /**
     * Тоо ширхэгийг шууд тохируулах. 0 буюу түүнээс бага бол мөрийг хасна.
     */
    public static function set(int $productId, int $quantity): void
    {
        $lines = self::lines();
        $lines[$productId] = $quantity;
        self::save($lines);
    }

    /**
     * Сагсыг хоослох (захиалга амжилттай илгээгдсэний дараа).
     */
    public static function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * @param array<int,int> $lines
     */
    private static function save(array $lines): void
    {
        $lines = self::normalize($lines);
        if (empty($lines)) {
            self::clear();
        } else {
            $_SESSION[self::SESSION_KEY] = $lines;
        }
    }

    /**
     * Session-оос эсвэл client-ээс ирсэн өгөгдлийг цэвэрлэх.
     *
     * Эерэг бүхэл ID, 1..MAX_QUANTITY тоо ширхэгтэй мөрүүдийг л үлдээж,
     * MAX_LINES-ээс илүүг хаяна.
     *
     * @param mixed $raw
     * @return array<int,int>
     */
    public static function normalize(mixed $raw): array
    {
        if (!\is_array($raw)) {
            return [];
        }
        $lines = [];
        foreach ($raw as $id => $qty) {
            $id = \filter_var($id, \FILTER_VALIDATE_INT);
            $qty = \filter_var($qty, \FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0 || $qty === false || $qty <= 0) {
                continue;
            }
            $lines[$id] = \min($qty, self::MAX_QUANTITY);
            if (\count($lines) >= self::MAX_LINES) {
                break;
            }
        }
        return $lines;
    }
}
