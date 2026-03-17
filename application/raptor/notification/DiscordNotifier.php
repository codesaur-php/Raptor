<?php

namespace Raptor\Notification;

use codesaur\Http\Client\CurlClient;

/**
 * Class DiscordNotifier
 * ------------------------------------------------------------------
 * Discord Webhook ашиглан dashboard-ийн чухал үйл явдлуудыг мэдэгдэх.
 *
 * Энэ класс нь Discord-ийн Webhook API руу embed мэдэгдэл илгээх
 * зориулалттай бөгөөд дараах үйл явдлуудыг мэдэгдэх боломжтой:
 *
 *   - Хэрэглэгчийн бүртгэлийн хүсэлт (userSignupRequest)
 *   - Хэрэглэгч баталгаажсан (userApproved)
 *   - Шинэ захиалга (newOrder)
 *   - Захиалгын статус өөрчлөгдсөн (orderStatusChanged)
 *   - Шинэ хөгжүүлэлтийн хүсэлт (newDevRequest)
 *   - Хөгжүүлэлтийн хүсэлт шинэчлэгдсэн (devRequestUpdated)
 *   - Контент үйлдэл: үүсгэх, шинэчлэх, устгах, нийтлэх (contentAction)
 *   - Тохиргоо шинэчлэгдсэн (settingsUpdated)
 *
 * .env дотор RAPTOR_DISCORD_WEBHOOK_URL тохируулсан байх шаардлагатай.
 * URL хоосон бол мэдэгдэл илгээхгүй (silent skip).
 *
 * @package Raptor\Notification
 */
class DiscordNotifier
{
    /** @var string Discord Webhook URL */
    private string $webhookUrl;

    /**
     * DiscordNotifier constructor.
     *
     * .env файлаас RAPTOR_DISCORD_WEBHOOK_URL утгыг авч тохируулна.
     */
    public function __construct()
    {
        $this->webhookUrl = $_ENV['RAPTOR_DISCORD_WEBHOOK_URL'] ?? '';
    }

    /**
     * Discord руу embed мэдэгдэл илгээх.
     *
     * @param string $title   Мэдэгдлийн гарчиг
     * @param string $description Дэлгэрэнгүй текст
     * @param int    $color   Embed-ийн зүүн ирмэгийн өнгө (decimal)
     * @param array  $fields  Нэмэлт талбарууд [['name'=>..,'value'=>..,'inline'=>bool], ...]
     * @param string $appUrl  Аппликейшний URL (footer-д харуулах)
     */
    public function send(string $title, string $description = '', int $color = 0x3498db, array $fields = [], string $appUrl = ''): void
    {
        if (empty($this->webhookUrl)) {
            return;
        }

        try {
            $embed = [
                'title' => \mb_substr($title, 0, 256),
                'color' => $color,
                'timestamp' => \gmdate('Y-m-d\TH:i:s\Z')
            ];

            if ($description !== '') {
                $embed['description'] = \mb_substr($description, 0, 2048);
            }

            if (!empty($fields)) {
                $embed['fields'] = \array_slice($fields, 0, 25);
            }

            if ($appUrl !== '') {
                $embed['footer'] = ['text' => $appUrl];
            }

            $client = new CurlClient();
            $client->request(
                $this->webhookUrl,
                'POST',
                \json_encode(['embeds' => [$embed]]),
                [\CURLOPT_HTTPHEADER => ['Content-Type: application/json']]
            );
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log("DiscordNotifier: {$e->getMessage()}");
            }
        }
    }

    /** @var int Амжилтын өнгө (ногоон) */
    const COLOR_SUCCESS = 0x2ecc71;
    /** @var int Мэдээллийн өнгө (цэнхэр) */
    const COLOR_INFO    = 0x3498db;
    /** @var int Анхааруулгын өнгө (шар) */
    const COLOR_WARNING = 0xf39c12;
    /** @var int Алдааны өнгө (улаан) */
    const COLOR_DANGER  = 0xe74c3c;
    /** @var int Нил ягаан өнгө */
    const COLOR_PURPLE  = 0x9b59b6;

    /**
     * Шинэ хэрэглэгч бүртгүүлэх хүсэлт ирсэн тухай мэдэгдэл.
     *
     * @param string $username Хэрэглэгчийн нэр
     * @param string $email    Хэрэглэгчийн имэйл
     * @param string $appUrl   Аппликейшний URL
     * @return void
     */
    public function userSignupRequest(string $username, string $email, string $appUrl = ''): void
    {
        $this->send(
            '📋 New User Signup Request',
            "**$username** has requested to register.",
            self::COLOR_INFO,
            [
                ['name' => 'Username', 'value' => $username, 'inline' => true],
                ['name' => 'Email', 'value' => $email, 'inline' => true]
            ],
            $appUrl
        );
    }

    /**
     * Хэрэглэгч баталгаажсан тухай мэдэгдэл.
     *
     * @param string $username Хэрэглэгчийн нэр
     * @param string $email    Хэрэглэгчийн имэйл
     * @param string $admin    Баталгаажуулсан админы нэр
     * @param string $appUrl   Аппликейшний URL
     * @return void
     */
    public function userApproved(string $username, string $email, string $admin = '', string $appUrl = ''): void
    {
        $fields = [
            ['name' => 'Username', 'value' => $username, 'inline' => true],
            ['name' => 'Email', 'value' => $email, 'inline' => true]
        ];
        if ($admin !== '') {
            $fields[] = ['name' => 'Approved by', 'value' => $admin, 'inline' => true];
        }

        $this->send(
            '✅ User Approved',
            "**$username** has been approved and can now log in.",
            self::COLOR_SUCCESS,
            $fields,
            $appUrl
        );
    }

    /**
     * Шинэ захиалга ирсэн тухай мэдэгдэл.
     *
     * @param int    $orderId  Захиалгын ID
     * @param string $customer Захиалагчийн нэр
     * @param string $email    Захиалагчийн имэйл
     * @param string $product  Бүтээгдэхүүний нэр
     * @param int    $quantity Тоо ширхэг
     * @param string $phone    Захиалагчийн утасны дугаар
     * @param string $appUrl   Аппликейшний URL
     * @return void
     */
    public function newOrder(int $orderId, string $customer, string $email, string $product, int $quantity, string $phone = '', string $appUrl = ''): void
    {
        $fields = [
            ['name' => 'Customer', 'value' => $customer, 'inline' => true],
            ['name' => 'Email', 'value' => $email, 'inline' => true],
            ['name' => 'Phone', 'value' => $phone ?: '-', 'inline' => true],
            ['name' => 'Product', 'value' => $product ?: '-', 'inline' => true],
            ['name' => 'Quantity', 'value' => (string)$quantity, 'inline' => true]
        ];

        $this->send(
            '🛒 New Order #' . $orderId,
            "**$customer** placed a new order.",
            self::COLOR_SUCCESS,
            $fields,
            $appUrl
        );
    }

    /**
     * Захиалгын статус өөрчлөгдсөн тухай мэдэгдэл.
     *
     * @param int    $orderId   Захиалгын ID
     * @param string $customer  Захиалагчийн нэр
     * @param string $oldStatus Хуучин статус
     * @param string $newStatus Шинэ статус
     * @param string $admin     Өөрчилсөн админы нэр
     * @param string $appUrl    Аппликейшний URL
     * @return void
     */
    public function orderStatusChanged(int $orderId, string $customer, string $oldStatus, string $newStatus, string $admin = '', string $appUrl = ''): void
    {
        $fields = [
            ['name' => 'From', 'value' => $oldStatus, 'inline' => true],
            ['name' => 'To', 'value' => $newStatus, 'inline' => true]
        ];
        if ($admin !== '') {
            $fields[] = ['name' => 'Changed by', 'value' => $admin, 'inline' => true];
        }

        $this->send(
            '📦 Order #' . $orderId . ' Status Changed',
            "Status updated for **$customer**'s order.",
            self::COLOR_WARNING,
            $fields,
            $appUrl
        );
    }

    /**
     * Шинэ хөгжүүлэлтийн хүсэлт үүссэн тухай мэдэгдэл.
     *
     * @param int    $requestId  Хүсэлтийн ID
     * @param string $title      Хүсэлтийн гарчиг
     * @param string $author     Үүсгэсэн хэрэглэгчийн нэр
     * @param string $assignedTo Хариуцагчийн нэр
     * @param string $appUrl     Аппликейшний URL
     * @return void
     */
    public function newDevRequest(int $requestId, string $title, string $author, string $assignedTo = '', string $appUrl = ''): void
    {
        $fields = [
            ['name' => 'Author', 'value' => $author, 'inline' => true]
        ];
        if ($assignedTo !== '') {
            $fields[] = ['name' => 'Assigned to', 'value' => $assignedTo, 'inline' => true];
        }

        $this->send(
            '🔧 Dev Request #' . $requestId,
            "**$title**",
            self::COLOR_INFO,
            $fields,
            $appUrl
        );
    }

    /**
     * Хөгжүүлэлтийн хүсэлтэд хариулт бичигдсэн тухай мэдэгдэл.
     *
     * @param int    $requestId Хүсэлтийн ID
     * @param string $title     Хүсэлтийн гарчиг
     * @param string $author    Хариулт бичсэн хэрэглэгчийн нэр
     * @param string $status    Шинэ статус (хоосон бол өөрчлөгдөөгүй)
     * @param string $appUrl    Аппликейшний URL
     * @return void
     */
    public function devRequestUpdated(int $requestId, string $title, string $author, string $status = '', string $appUrl = ''): void
    {
        $fields = [
            ['name' => 'By', 'value' => $author, 'inline' => true]
        ];
        if ($status !== '') {
            $fields[] = ['name' => 'Status', 'value' => $status, 'inline' => true];
        }

        $this->send(
            '💬 Dev Request #' . $requestId . ' Updated',
            "**$title**",
            self::COLOR_WARNING,
            $fields,
            $appUrl
        );
    }

    /**
     * Холбоо барих хуудаснаас шинэ мессеж ирсэн тухай мэдэгдэл.
     *
     * @param string $name    Илгээгчийн нэр
     * @param string $phone   Утасны дугаар
     * @param string $email   И-мэйл (хоосон байж болно)
     * @param string $message Мессежийн агуулга
     * @param string $appUrl  Аппликейшний URL
     * @return void
     */
    public function newContactMessage(string $name, string $phone, string $email, string $message, string $appUrl = ''): void
    {
        $fields = [
            ['name' => 'Name', 'value' => $name, 'inline' => true],
            ['name' => 'Phone', 'value' => $phone, 'inline' => true],
        ];
        if ($email !== '') {
            $fields[] = ['name' => 'Email', 'value' => $email, 'inline' => true];
        }
        $fields[] = ['name' => 'Message', 'value' => \mb_substr($message, 0, 1024), 'inline' => false];

        $this->send(
            '📩 New Contact Message',
            "**$name** sent a message via the contact form.",
            self::COLOR_INFO,
            $fields,
            $appUrl
        );
    }

    /**
     * Контент дээр үйлдэл хийсэн тухай мэдэгдэл.
     *
     * Дэмжигдэх үйлдлүүд: insert, update, delete, publish.
     * Контентын төрөл: news, page, product гэх мэт.
     *
     * @param string   $type   Контентын төрөл (news, page, product...)
     * @param string   $action Үйлдлийн нэр (insert, update, delete, publish)
     * @param string   $title  Контентын гарчиг
     * @param int|null $id     Контентын ID (байхгүй байж болно)
     * @param string   $admin   Үйлдэл хийсэн админы нэр
     * @param string   $appUrl  Аппликейшний URL
     * @param array    $updates Өөрчлөгдсөн талбаруудын жагсаалт
     * @return void
     */
    public function contentAction(string $type, string $action, string $title, ?int $id = null, string $admin = '', string $appUrl = '', array $updates = []): void
    {
        $icons = [
            'insert' => '🆕', 'update' => '✏️',
            'delete' => '🗑️', 'publish' => '📢'
        ];
        $colors = [
            'insert' => self::COLOR_SUCCESS, 'update' => self::COLOR_INFO,
            'delete' => self::COLOR_DANGER, 'publish' => self::COLOR_PURPLE
        ];

        $icon = $icons[$action] ?? '📝';
        $color = $colors[$action] ?? self::COLOR_INFO;
        $actionPast = [
            'insert' => 'created', 'update' => 'updated',
            'delete' => 'deleted', 'publish' => 'published'
        ];
        $pastVerb = $actionPast[$action] ?? $action;

        $idSuffix = $id !== null ? " #$id" : '';
        $desc = $admin !== ''
            ? "**$admin** $pastVerb a " . \strtolower($type) . "$idSuffix"
            : \ucfirst($type) . " has been $pastVerb.$idSuffix";

        $fields = [];
        if (!empty($updates)) {
            $fields[] = ['name' => 'Changed', 'value' => \implode(', ', $updates), 'inline' => false];
        }

        $this->send(
            "$icon " . \ucfirst($type) . ": $title",
            $desc,
            $color,
            $fields,
            $appUrl
        );
    }

    /**
     * Тохиргоо шинэчлэгдсэн тухай мэдэгдэл.
     *
     * @param string $section Тохиргооны хэсэг (texts, files, options)
     * @param array  $updates Өөрчлөгдсөн талбаруудын жагсаалт
     * @param string $admin   Үйлдэл хийсэн админы нэр
     * @param string $appUrl  Аппликейшний URL
     * @return void
     */
    public function settingsUpdated(string $section, array $updates, string $admin = '', string $appUrl = ''): void
    {
        $icons = ['texts' => '📝', 'files' => '🖼️', 'options' => '⚙️'];
        $icon = $icons[$section] ?? '🔧';

        $desc = $admin !== ''
            ? "**$admin** updated settings ($section)."
            : "Settings ($section) have been updated.";

        $fields = [];
        if (!empty($updates)) {
            $fields[] = ['name' => 'Changed', 'value' => \implode(', ', $updates), 'inline' => false];
        }

        $this->send(
            "$icon Settings: " . \ucfirst($section),
            $desc,
            self::COLOR_WARNING,
            $fields,
            $appUrl
        );
    }
}
