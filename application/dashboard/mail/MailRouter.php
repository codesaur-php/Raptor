<?php

namespace Dashboard\Mail;

use codesaur\Router\Router;

use Dashboard\CsrfMiddleware;

/**
 * Class MailRouter
 *
 * Хэрэглэгчид рүү имэйл илгээх (Систем - Имэйл илгээх) маршрутууд.
 *
 *   GET  /dashboard/mail-send       -> Имэйл илгээх хуудас
 *   POST /dashboard/mail-send/send  -> mode=prepare (хүлээн авагч тогтоох) / mode=batch (багц илгээх)
 *
 * @package Dashboard\Mail
 */
class MailRouter extends Router
{
    public function __construct()
    {
        $this->GET('/mail-send', [MailController::class, 'index'])->name('mail-send');
        $this->POST('/mail-send/send', [MailController::class, 'send'])->name('mail-send-post')->middleware([CsrfMiddleware::class]);
    }
}
