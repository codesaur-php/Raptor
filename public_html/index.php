<?php
/**
 * ============================================================================
 *  Raptor Framework - Entry Point / Bootstrap
 * ============================================================================
 *
 *  Энэ файл нь Raptor төслийн бүх HTTP хүсэлтийг хүлээн авч,
 *  тохирох Application (Web эсвэл Dashboard)-д дамжуулан боловсруулах
 *  гол эхлэл цэг (bootstrap) юм.
 *
 *  Raptor нь codesaur ecosystem-ийн core package бөгөөд бусад codesaur
 *  packages-тэй хамтран ажилладаг framework юм.
 *
 *  Үндсэн үүргүүд:
 *  ----------------
 *  1. Composer autoload-г ачаалах
 *  2. Error log файлын байрлал тохируулах
 *  3. .env тохиргоог унших (Dotenv)
 *  4. Хөгжүүлэлтийн / Production горим тодорхойлох
 *  5. Custom error handler бүртгэх
 *  6. Цагийн бүс тохируулах (ENV дээр заасан бол)
 *  7. PSR-7 стандартын дагуу ServerRequest үүсгэх
 *  8. Өгөгдлийн сангийн PDO холболтыг үүсгэж request-д inject хийх
 *  9. URL замаас хамааран Web эсвэл Dashboard Application-г сонгох
 *  10. PSR-15 стандартын дагуу request-г handle хийх
 *
 *  @package    codesaur/raptor
 *  @author     Narankhuu <codesaur@gmail.com>
 *  @copyright  Copyright (c) 2012-present codesaur (Narankhuu)
 *  @license    MIT
 *
 *  СЕРВЕРИЙН ТОХИРГООНЫ ТАЙЛБАР:
 *  -----------------------------------
 *  Энэ index.php файл нь Apache серверийн .htaccess тохиргоотой
 *  зөв ажиллаж байна. Гэхдээ Apache сервер биш, nginx сервертэй 
 *  тохиолдолд .nginx.conf.example жишээ тохиргооноос харж өөрийн 
 *  nginx тохиргоог зөв хийж Raptor-г ажиллуулах хэрэгтэй!
 *
 *  @see docs/conf.example/.nginx.conf.example nginx серверийн жишээ тохиргоо
 *  @see docs/conf.example/.htaccess.example Apache серверийн жишээ тохиргоо
 */

use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Message\NonBodyResponse;

/**
 * Бүх алдааны төрлийг идэвхтэй болгох
 * Development болон Production аль ч горимд алдааг лог хийх зорилгоор
 */
\error_reporting(\E_ALL);

// ---------------------------------------------------------------------------
// 1. Bootstrap-ийн суурь: autoload ачаалах, error log тохируулах, .env унших
// ---------------------------------------------------------------------------
/**
 * Доорх алхмууд бүгд серверийн тохиргооноос хамаардаг тул аль нэг нь
 * амжилтгүй болбол нэг л catch барьж, 500 статустайгаар зогсоно:
 *   1. Composer autoload-г шалгаж ачаалах
 *   2. Error log файлын байрлал тохируулах
 *   3. .env тохиргоог унших (Dotenv)
 */
try {
    /** @var string $root_dir Төслийн root директорийн зам */
    $root_dir = \dirname(__DIR__);
    /** @var string $autoload Composer autoload файлын бүтэн зам */
    $autoload = "$root_dir/vendor/autoload.php";
    if (!\file_exists($autoload)) {
        throw new \RuntimeException("$autoload is missing!");
    }
    /** @var \Composer\Autoload\ClassLoader $composer Composer autoload instance */
    $composer = require($autoload);

    // Error log байрлал тохируулах
    \ini_set('log_errors', 'On');
    \ini_set('error_log', "$root_dir/logs/code.log");

    /** @var \Dotenv\Dotenv $dotenv .env файлыг унших Dotenv instance */
    $dotenv = \Dotenv\Dotenv::createImmutable($root_dir);
    $dotenv->load();
    /**
     * .env файлаас уншсан boolean утгыг string-ээс бодит boolean болгох
     * (Dotenv нь бүх утгыг string хэлбэрээр авдаг тул)
     */
    foreach ($_ENV as &$env) {
        if ($env == 'true') {
            $env = true;
        } elseif ($env == 'false') {
            $env = false;
        }
    }
    unset($env); // Reference-г цэвэрлэх
} catch (\Throwable $e) {
    \http_response_code(500);
    die("codesaur exit: {$e->getMessage()}");
}

// ---------------------------------------------------------------------------
// 4. Хөгжүүлэлтийн горим тодорхойлох
// ---------------------------------------------------------------------------
/**
 * CODESAUR_DEVELOPMENT тогтмол
 * 
 * @var bool CODESAUR_DEVELOPMENT Хөгжүүлэлтийн горим эсэхийг тодорхойлдог.
 *                                  true бол development, false бол production.
 *                                  CODESAUR_APP_ENV != 'production' үед true байна.
 */
\define(
    'CODESAUR_DEVELOPMENT',
    isset($_ENV['CODESAUR_APP_ENV'])
        ? $_ENV['CODESAUR_APP_ENV'] != 'production'
        : false
);
// Development үед алдааг дэлгэцэн дээр харуулна. Production үед бол зөвхөн лог файлд бичнэ
\ini_set('display_errors', CODESAUR_DEVELOPMENT ? 'On' : 'Off');

// ---------------------------------------------------------------------------
// 5. Error handler - бүх алдааг лог руу бичээд үргэлжлүүлэх
// ---------------------------------------------------------------------------
/**
 * Custom error handler function
 * 
 * Бүх PHP алдааг барьж авч, лог файлд бичнэ.
 * Default PHP error handler-д дамжуулахгүй (return true).
 * 
 * @param int    $errno   Алдааны код (E_ERROR, E_WARNING, гэх мэт)
 * @param string $errstr  Алдааны мессеж
 * @param string $errfile Алдаа гарсан файлын зам
 * @param int    $errline Алдаа гарсан мөрийн дугаар
 * 
 * @return bool true - алдааг барьж авсан, default handler-д дамжуулахгүй
 */
\set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    switch ($errno) {
        case \E_USER_ERROR:   $error = 'Fatal error'; break;
        case \E_USER_WARNING: $error = 'Warning';     break;
        case \E_USER_NOTICE:  $error = 'Notice';      break;
        default:              $error = 'Unknown error'; break;
    }
    \error_log("$error #$errno: $errstr in $errfile on line $errline");
    return true;
});

// ---------------------------------------------------------------------------
// 6. Цагийн бүс тохируулах (ENV дээрээс)
// ---------------------------------------------------------------------------
/**
 * CODESAUR_APP_TIME_ZONE утга байвал PHP-ийн цагийн бүсийг тохируулах
 * 
 * Жишээ: 'Asia/Ulaanbaatar', 'UTC', 'America/New_York' гэх мэт
 * Байхгүй бол PHP-ийн default timezone ашиглана
 */
if (!empty($_ENV['CODESAUR_APP_TIME_ZONE'])) {
    \date_default_timezone_set($_ENV['CODESAUR_APP_TIME_ZONE']);
}

// ---------------------------------------------------------------------------
// 7. PSR-7 дагуу ServerRequest-г глобал орчноос үүсгэх
// ---------------------------------------------------------------------------
/**
 * PSR-7 стандартын дагуу ServerRequest объект үүсгэх
 * 
 * @var \codesaur\Http\Message\ServerRequest $request 
 *      Глобал PHP орчноос ($_SERVER, $_GET, $_POST, гэх мэт) үүссэн
 *      PSR-7 ServerRequest object
 */
$request = (new ServerRequest())->initFromGlobal();

// ---------------------------------------------------------------------------
// 8. Өгөгдлийн сангийн PDO холболтыг үүсгэж request-д inject хийх
// ---------------------------------------------------------------------------
/**
 * Web ба Dashboard аппликейшнүүд аль аль нь нэг бааз ашиглах ёстой тул
 * entry point дээр нэг л удаа PDO нээж, request-д attribute болгон дамжуулна.
 * Driver сонголт .env-ийн RAPTOR_DB_DRIVER хувьсагчаар удирдагдана.
 *
 * Database нь аль хэдийн үүссэн байх ёстой - шинэ орчинд developer
 * зөвхөн хоосон санг л гараар урьдчилж үүсгээд орхино: өөрийн DB
 * хэрэгслээр (mysql/psql CLI, phpMyAdmin, hosting panel г.м.)
 * CREATE DATABASE ажиллуулна - үүнд ямар нэг код бичих шаардлагагүй.
 * Доторх бүх хүснэгт болон анхны seed өгөгдлийг Raptor анх ажиллах
 * үедээ Model классуудаараа автоматаар үүсгэдэг тул хүснэгтүүдийг
 * гараар бүү үүсгэ.
 */
try {
    $pdo = \Dashboard\DatabaseConnection::connect();
    $request = $request->withAttribute('pdo', $pdo);
} catch (\Throwable $e) {
    \error_log("DB холболтын алдаа: {$e->getMessage()}");

    \http_response_code(503);

    // Production-д алдааны дэлгэрэнгүйг гаргахгүй (мэдээлэл алдагдахаас сэргийлнэ),
    // зөвхөн development үед getMessage()-г харуулна.    
    $message = 'Database connection failed';
    if (CODESAUR_DEVELOPMENT) {
        $message .= ' - ' . $e->getMessage();
    }
    die("codesaur exit: $message.");
}

/**
 * URL path-г цэвэрлэх (subdirectory дээр ажиллуулахад ашиглагдана)
 * 
 * Жишээ: Хэрэв скрипт /subdir/public_html/index.php байвал,
 *        /subdir/public_html хэсгийг path-аас хасна.
 * 
 * @var string $path Цэвэрлэгдсэн URL path (leading slash-тай)
 */
$path = \rawurldecode($request->getUri()->getPath());
if (($length = \strlen(\dirname($request->getServerParams()['SCRIPT_NAME']))) > 1) {
    $path = \substr($path, $length);
    $path = '/' . \ltrim($path, '/');
}

// ---------------------------------------------------------------------------
// 9. Өгөгдсөн path-аас хамааран Application сонгох
// ---------------------------------------------------------------------------
/**
 * URL path-аас хамааран Application instance үүсгэх
 *
 * Routing логик:
 *   - /dashboard/... -> Dashboard\Application (Admin panel)
 *   - Бусад бүх зам -> Web\Application (Public website)
 *
 * NonBodyResponse - Application-ийн constructor-д дамжуулж буй хариуны
 * fallback prototype:
 *   - Controller/action нь ResponseInterface биш төрөл буцаавал Application
 *     дотор энэ prototype-оос clone хийж хүчинтэй PSR-7 хариу болгоно.
 *   - Body stream огт агуулаагүй (getBody() дуудвал RuntimeException шиднэ),
 *     учир нь Raptor-ийн controller-ууд контентоо output buffer-аар шууд
 *     echo/print хийж browser руу хэвлэдэг - хариу нь зөвхөн HTTP status,
 *     reason phrase, header-уудыг л зөөх carrier болж ажиллана.
 *   - Body stream-тэй Response-ийн оронд үүнийг сонгосноор хэзээ ч
 *     ашиглагдахгүй Output stream дэмий үүсгэхээс зайлсхийнэ (хөнгөн).
 *
 * @see \codesaur\Http\Message\NonBodyResponse Body-гүй минимал PSR-7 хариу
 * @see \codesaur\Http\Application\Application::handle() prototype-оос clone хийх логик
 *
 * @var \Dashboard\Application|\Web\Application $application
 *      Path-аас хамааран сонгогдсон Application instance
 */
if ((\explode('/', $path)[1] ?? '') == 'dashboard') {
    // Dashboard-ийн бүх router нь prefix-naive (route-ууд '/users', '/news'
    // гэх мэт '/dashboard'-гүй бүртгэгдсэн). Application-ийг mount path-д
    // mount хийснээр match() нь request path-аас prefix-ийг автоматаар зүсэж,
    // generate()/link нь буцах URL-д prefix-ийг автоматаар нэмнэ.
    $application = (new \Dashboard\Application(new NonBodyResponse()))->mount('/dashboard');
} else {
    $application = new \Web\Application(new NonBodyResponse());
}

// ---------------------------------------------------------------------------
// 10. Сонгогдсон Application-д PSR-15 handler-ээр request-г дамжуулах
// ---------------------------------------------------------------------------
/**
 * PSR-15 стандартын дагуу request-г handle хийх
 * 
 * Application::handle() нь PSR-15 RequestHandlerInterface-ийн 
 * handle() method-ийг дуудна. Энэ нь:
 *   - Middleware chain-г дамжуулах
 *   - Route matching хийх
 *   - Controller-г ажиллуулах
 *   - Response буцаах
 * 
 * @see \Psr\Http\Server\RequestHandlerInterface::handle()
 */
$application->handle($request);
