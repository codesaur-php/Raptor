<?php

/**
 * PHPStan-д зориулсан bootstrap.
 *
 * Runtime-д public_html/index.php (болон tests/bootstrap.php) дотор
 * тодорхойлогддог глобал тогтмолуудыг static analysis-д мэдэгдэнэ.
 * Энэ файл нь зөвхөн PHPStan-ийн шинжилгээний үед уншигдана - application
 * runtime-д огт ачаалагдахгүй (composer autoload-д ороогүй).
 */

if (!\defined('CODESAUR_DEVELOPMENT')) {
    \define('CODESAUR_DEVELOPMENT', false);
}
