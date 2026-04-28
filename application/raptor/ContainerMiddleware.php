<?php

namespace Raptor;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use codesaur\Container\Container;

/**
 * Class ContainerMiddleware
 *
 * Dependency Injection Container-г request attributes-д inject хийх middleware.
 *
 * Энэ middleware нь codesaur/container package-г ашиглан Container үүсгэж,
 * request attributes-д inject хийнэ. Хөгжүүлэгчид өөрсдийн service-уудыг
 * registerServices() method-д бүртгэж ашиглах боломжтой.
 *
 * ================================================================
 * Хөгжүүлэгчдэд зориулсан заавар
 * ================================================================
 *
 * Service бүртгэх:
 * - ContainerMiddleware-г өргөтгөж registerServices() method-д service-уудыг нэмнэ
 * - registerServices() method нь container болон request параметр авна
 * - Request-ээс PDO, User зэрэг dependency-уудыг авах боломжтой
 * - Controller-д $this->getService('service_id') ашиглан авах
 *
 * Жишээ:
 * ```php
 * namespace App\Middleware;
 *
 * use Raptor\ContainerMiddleware;
 * use Psr\Container\ContainerInterface;
 * use Psr\Http\Message\ServerRequestInterface;
 *
 * class MyContainerMiddleware extends ContainerMiddleware
 * {
 *     protected function registerServices(
 *         ContainerInterface &$container,
 *         ServerRequestInterface $request
 *     ): void {
 *         // PDO шаардлагатай service (Lazy loading - request-ээс PDO авч)
 *         $container->set('mailer', function(ContainerInterface $c) use ($request) {
 *             $pdo = $request->getAttribute('pdo');
 *             return new \Raptor\Mail\Mailer($pdo);
 *         });
 *
 *         // PDO шаардлагагүй service (Lazy loading)
 *         $container->set('cache', function(ContainerInterface $c) {
 *             return new \App\Services\CacheService();
 *         });
 *
 *         // Container-аас өөр service авч ашиглах жишээ
 *         // Email Notification Service нь Mailer service-г ашиглана
 *         $container->set('email_notification', function(ContainerInterface $c) {
 *             $mailer = $c->get('mailer');  // Container-аас mailer service авах
 *             return new \App\Services\EmailNotificationService($mailer);
 *         });
 *     }
 * }
 * ```
 *
 * Application-д өөрийн middleware-г бүртгэнэ:
 * ```php
 * $this->use(new \App\Middleware\MyContainerMiddleware());
 * ```
 *
 * Controller-д ашиглах:
 * ```php
 * $mailer = $this->getService('mailer');
 * $cache = $this->getService('cache');
 * ```
 *
 * @package Raptor
 */
class ContainerMiddleware implements MiddlewareInterface
{
    /**
     * Middleware process.
     *
     * Container үүсгэж, request attributes-д inject хийнэ.
     * Хөгжүүлэгчид өөрсдийн service-уудыг registerServices() method-д
     * бүртгэж ашиглах боломжтой.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Container үүсгэх (эсвэл одоо байгаа container-г ашиглах)
        $container = $request->getAttribute('container');
        if (!$container instanceof ContainerInterface) {
            $container = new Container();
            $this->registerServices($container, $request);
        }

        // Container-г request attribute болгон дамжуулах
        return $handler->handle(
            $request->withAttribute('container', $container)
        );
    }

    /**
     * Service-уудыг container-д бүртгэх.
     *
     * Хөгжүүлэгчид энэ method-г өргөтгөж өөрсдийн service-уудыг бүртгэж болно.
     *
     * ================================================================
     * Жишээ: Service бүртгэх
     * ================================================================
     *
     * ```php
     * protected function registerServices(
     *     ContainerInterface &$container,
     *     ServerRequestInterface $request
     * ): void {
     *     // PDO шаардлагатай service (Lazy loading - request-ээс PDO авч)
     *     $container->set('mailer', function(ContainerInterface $c) use ($request) {
     *         $pdo = $request->getAttribute('pdo');
     *         return new \Raptor\Mail\Mailer($pdo);
     *     });
     *
     *     // PDO шаардлагагүй service (Lazy loading)
     *     $container->set('cache', function(ContainerInterface $c) {
     *         return new \App\Services\CacheService();
     *     });
     *
     *     // User шаардлагатай service (Lazy loading - request-ээс User авч)
     *     $container->set('user_service', function(ContainerInterface $c) use ($request) {
     *         $pdo = $request->getAttribute('pdo');
     *         $user = $request->getAttribute('user');
     *         return new \App\Services\UserService($pdo, $user);
     *     });
     *
     *     // Container-аас өөр service авч ашиглах жишээ
     *     // Email Notification Service нь Mailer service-г ашиглана
     *     $container->set('email_notification', function(ContainerInterface $c) {
     *         $mailer = $c->get('mailer');  // Container-аас mailer service авах
     *         return new \App\Services\EmailNotificationService($mailer);
     *     });
     * }
     * ```
     *
     * ================================================================
     * Зөвлөмж
     * ================================================================
     *
     * 1. Request-ээс dependency авах
     *    -> $pdo = $request->getAttribute('pdo');
     *    -> $user = $request->getAttribute('user');
     *
     * 2. Lazy loading ашиглах (Зөвлөмж)
     *    -> Service-г factory function ашиглан бүртгэнэ
     *    -> Service-г шаардлагатай үед л үүсгэдэг (performance сайжирна)
     *    -> $container->set('mailer', function(ContainerInterface $c) use ($request) {
     *          $pdo = $request->getAttribute('pdo');
     *          return new \Raptor\Mail\Mailer($pdo);
     *      });
     *
     * 3. Factory function-д use ($request) ашиглах
     *    -> Closure дотор request-г ашиглахын тулд use keyword заавал ашиглана
     *
     * 4. Service ID-г тодорхой, уншигдахуйц нэр өгөх
     *    'mailer', 'cache', 'email_notification'
     *    'm', 'c', 'e'
     *
     * 5. Container-аас өөр service авч ашиглах
     *    -> $mailer = $c->get('mailer');  // Container-аас service авах
     *    -> Factory function-д ContainerInterface $c параметр ашиглана
     *    -> use ($request) шаардлагагүй, учир нь зөвхөн container-аас service авч байна
     *
     * @param ContainerInterface $container Container instance
     * @param ServerRequestInterface $request Server request (PDO, User зэрэг dependency-ууд агуулна)
     * @return void
     */
    protected function registerServices(
        ContainerInterface &$container,
        ServerRequestInterface $request
    ): void {
        // File-based cache service (DB query хэмнэх)
        // Cache үүсгэх боломжгүй бол (permission, disk гэх мэт) null буцааж,
        // систем cache-гүйгээр хэвийн ажиллана
        $container->set('cache', function(ContainerInterface $c) {
            try {
                $cacheDir = \dirname($_SERVER['SCRIPT_FILENAME'], 2) . '/private/cache';
                return new CacheService($cacheDir, 43200);
            } catch (\Throwable $e) {
                if (CODESAUR_DEVELOPMENT) {
                    \error_log('Cache service unavailable: ' . $e->getMessage());
                }
                return null;
            }
        });

        // Mailer service бүртгэе (Dashboard-оос хэрэглэгчдэд мэдэгдэл шуудан илгээхэд ашиглана)
        $container->set('mailer', function(ContainerInterface $c) use ($request) {
            $pdo = $request->getAttribute('pdo');
            return new \Raptor\Mail\Mailer($pdo);
        });

        // Template service бүртгэх (templates хүснэгтээс keyword-аар орчуулга татах)
        // Cache service-ийг inject хийнэ - байхгүй (null) бол DB-ээс шууд уншина
        $container->set('template_service', function(ContainerInterface $c) use ($request) {
            $pdo = $request->getAttribute('pdo');
            $cache = $c->has('cache') ? $c->get('cache') : null;
            return new \Raptor\Content\TemplateService($pdo, $cache);
        });
        
        // Discord webhook notification service
        $container->set('discord', function(ContainerInterface $c) use ($request) {
            $authUser = $request->getAttribute('user');
            $userName = \trim(
                ($authUser?->profile['first_name'] ?? '') . ' '
                . ($authUser?->profile['last_name'] ?? '')
            );
            $host = $request->getUri()->getHost();
            return new \Raptor\Notification\DiscordNotifier($userName, $host);
        });

        // PSR-14 Event Dispatcher
        $container->set('events', function(ContainerInterface $c) {
            $provider = new \Raptor\Notification\ListenerProvider();

            // Discord listener бүртгэх
            $discord = new \Raptor\Notification\DiscordListener($c->get('discord'));
            $provider->listen(\Raptor\Notification\ContentEvent::class, [$discord, 'onContentEvent']);
            $provider->listen(\Raptor\Notification\UserEvent::class, [$discord, 'onUserEvent']);
            $provider->listen(\Raptor\Notification\OrderEvent::class, [$discord, 'onOrderEvent']);
            $provider->listen(\Raptor\Notification\DevRequestEvent::class, [$discord, 'onDevRequestEvent']);

            // Ирээдүйд энд нэмэлт listener бүртгэнэ:
            // $provider->listen(ContentEvent::class, [$slackListener, 'onContent']);
            // $provider->listen(ContentEvent::class, [$emailListener, 'onContent']);

            return new \Raptor\Notification\EventDispatcher($provider);
        });

        // ============================================================
        // Хөгжүүлэгч энд өөрийн service-уудыг нэмж бүртгэнэ
        // ============================================================
        //
        // Жишээ: PDO шаардлагагүй service (Lazy loading)
        // $container->set('cache', function(ContainerInterface $c) {
        //     return new \MyNamespace\CacheService();
        // });
        //
        // Жишээ: User шаардлагатай service (Lazy loading)
        // $container->set('user_service', function(ContainerInterface $c) use ($request) {
        //     $pdo = $request->getAttribute('pdo');
        //     $user = $request->getAttribute('user');
        //     return new \MyNamespace\UserService($pdo, $user);
        // });
        //
        // Жишээ: Container-аас өөр service авч ашиглах (Lazy loading)
        // $container->set('email_notification', function(ContainerInterface $c) {
        //     $mailer = $c->get('mailer');  // Container-аас mailer service авах
        //     return new \MyNamespace\EmailNotificationService($mailer);
        // });
        //
    }
}

