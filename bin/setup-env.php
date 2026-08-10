<?php

/**
 * Composer "setup-env" script (post-root-package-install, post-install-cmd, post-update-cmd).
 *
 * 1. Copies docs/conf.example/.env.example to .env when .env is missing
 *    (skipped entirely when the example file itself is absent).
 * 2. Generates RAPTOR_JWT_SECRET in .env when the value is missing, empty,
 *    or still the .env.example placeholder. An existing real secret is never
 *    rotated - rotating it would invalidate all issued JWTs and log every
 *    user out. The secret value itself is never printed to the console.
 *
 * Анхаар: энэ логикийг composer.json руу "@php -r" хэлбэрээр буцааж inline
 * хийж болохгүй - Composer скриптээ үйлдлийн системийн shell-ээр дамжуулж
 * ажиллуулдаг тул Unix shell давхар хашилт доторх $f мэт хувьсагчдыг хоосон
 * утгаар орлуулж, PHP parse error үүсгэнэ.
 *
 * Critical (English): do NOT inline this logic back into composer.json as
 * "@php -r" code - Composer runs scripts through the OS shell, and on Unix
 * the shell expands $variables inside double quotes to empty strings,
 * producing a PHP parse error. It must stay in a script file.
 */

$root = dirname(__DIR__);
$env = $root . '/.env';
$example = $root . '/docs/conf.example/.env.example';

if (!file_exists($env) && file_exists($example)) {
    copy($example, $env);
}

if (!file_exists($env)) {
    return;
}

$contents = file_get_contents($env);
$value = preg_match('/^RAPTOR_JWT_SECRET=(.*)$/m', $contents, $matches)
    ? trim($matches[1]) : null;
if ($value !== null
    && $value !== ''
    && $value !== 'will-be-auto-generated-during-composer-post-install'
) {
    return;
}

$secret = bin2hex(random_bytes(64));
if ($value === null) {
    if ($contents !== '' && substr($contents, -1) !== PHP_EOL) {
        $contents .= PHP_EOL;
    }
    $contents .= 'RAPTOR_JWT_SECRET=' . $secret . PHP_EOL;
} else {
    $contents = preg_replace(
        '/^RAPTOR_JWT_SECRET=.*$/m', 'RAPTOR_JWT_SECRET=' . $secret, $contents
    );
}
file_put_contents($env, $contents);
echo 'RAPTOR_JWT_SECRET auto-generated in .env' . PHP_EOL;
