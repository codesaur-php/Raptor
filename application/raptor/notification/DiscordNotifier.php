<?php

namespace Raptor\Notification;

use codesaur\Http\Client\CurlClient;

/**
 * Discord Webhook ашиглан dashboard-ийн чухал үйл явдлуудыг мэдэгдэх.
 *
 * .env дотор RAPTOR_DISCORD_WEBHOOK_URL тохируулсан байх шаардлагатай.
 * URL хоосон бол мэдэгдэл илгээхгүй (silent skip).
 */
class DiscordNotifier
{
    private string $webhookUrl;

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
     */
    public function send(string $title, string $description = '', int $color = 0x3498db, array $fields = []): void
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

            $client = new CurlClient();
            $client->request(
                $this->webhookUrl,
                'POST',
                \json_encode(['embeds' => [$embed]]),
                [\CURLOPT_HTTPHEADER => ['Content-Type: application/json']]
            );
        } catch (\Throwable $e) {
            \error_log("DiscordNotifier: {$e->getMessage()}");
        }
    }

    // ── Түгээмэл өнгөнүүд ──

    const COLOR_SUCCESS = 0x2ecc71; // ногоон
    const COLOR_INFO    = 0x3498db; // цэнхэр
    const COLOR_WARNING = 0xf39c12; // шар
    const COLOR_DANGER  = 0xe74c3c; // улаан
    const COLOR_PURPLE  = 0x9b59b6; // нил ягаан

    // ── Тусгай мэдэгдлүүд ──

    public function userSignupRequest(string $username, string $email): void
    {
        $this->send(
            '📋 New User Signup Request',
            "**$username** has requested to register.",
            self::COLOR_INFO,
            [
                ['name' => 'Username', 'value' => $username, 'inline' => true],
                ['name' => 'Email', 'value' => $email, 'inline' => true]
            ]
        );
    }

    public function userApproved(string $username, string $email, string $admin = ''): void
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
            $fields
        );
    }

    public function newOrder(int $orderId, string $customer, string $email, string $product, int $quantity): void
    {
        $this->send(
            '🛒 New Order #' . $orderId,
            "**$customer** placed a new order.",
            self::COLOR_SUCCESS,
            [
                ['name' => 'Customer', 'value' => $customer, 'inline' => true],
                ['name' => 'Email', 'value' => $email, 'inline' => true],
                ['name' => 'Product', 'value' => $product ?: '-', 'inline' => true],
                ['name' => 'Quantity', 'value' => (string)$quantity, 'inline' => true]
            ]
        );
    }

    public function orderStatusChanged(int $orderId, string $customer, string $oldStatus, string $newStatus, string $admin = ''): void
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
            $fields
        );
    }

    public function contentAction(string $type, string $action, string $title, ?int $id = null, string $admin = ''): void
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
        $actionLabels = [
            'insert' => 'New', 'update' => 'Updated',
            'delete' => 'Deleted', 'publish' => 'Published'
        ];
        $actionLabel = $actionLabels[$action] ?? \ucfirst($action);

        $fields = [
            ['name' => 'Type', 'value' => \ucfirst($type), 'inline' => true],
            ['name' => 'Action', 'value' => $actionLabel, 'inline' => true]
        ];
        if ($id !== null) {
            $fields[] = ['name' => 'ID', 'value' => (string)$id, 'inline' => true];
        }
        if ($admin !== '') {
            $fields[] = ['name' => 'By', 'value' => $admin, 'inline' => true];
        }

        $this->send(
            "$icon " . \ucfirst($type) . " ($actionLabel): $title",
            '',
            $color,
            $fields
        );
    }
}
