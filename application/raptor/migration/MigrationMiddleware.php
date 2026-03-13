<?php

namespace Raptor\Migration;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class MigrationMiddleware
 *
 * HTTP хүсэлт бүрт pending migration файл байгаа эсэхийг шалгаж,
 * байвал автоматаар ажиллуулдаг PSR-15 middleware.
 *
 * Онцлог:
 *  - MySQLConnectMiddleware-ийн ДАРАА ажиллана (PDO бэлэн болсон үед).
 *  - Алдаа гарсан ч хүсэлтийг блоклохгүй, зөвхөн error_log-д бичнэ.
 *  - hasPending() нь glob() дээр суурилсан тул DB query шаардахгүй,
 *    хүсэлт бүрт нэмэгдэх ачаалал маш бага.
 *
 * @package Raptor\Migration
 */
class MigrationMiddleware implements MiddlewareInterface
{
    /**
     * Pending migration байвал ажиллуулж, хүсэлтийг дараагийн handler-т дамжуулах.
     *
     * @param ServerRequestInterface  $request  HTTP хүсэлт
     * @param RequestHandlerInterface $handler  Дараагийн handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $pdo = $request->getAttribute('pdo');
        if ($pdo) {
            try {
                $runner = new MigrationRunner($pdo, \dirname(__DIR__, 3) . '/database/migrations');
                if ($runner->hasPending()) {
                    $runner->migrate();
                }
            } catch (\Throwable $e) {
                \error_log('Migration error: ' . $e->getMessage());
            }
        }

        return $handler->handle($request);
    }
}
