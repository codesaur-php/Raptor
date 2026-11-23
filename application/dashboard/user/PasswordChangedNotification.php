<?php

namespace Dashboard\User;

use codesaur\Template\MemoryTemplate;

/**
 * Trait PasswordChangedNotification
 * ------------------------------------------------------------------
 * Нууц үг солигдсон тухай хэрэглэгчийн и-мэйл рүү мэдэгдэл илгээнэ
 * ('password-changed' загвар, templates reference). Нууц үг өөрчлөгддөг
 * бүх зам дуудна:
 *   - UsersController::setPassword()  - өөрөө солих, system_coder солих
 *   - LoginController::setPassword()  - "Нууц үгээ мартсан уу?" холбоосоор
 *
 * Эзэн нь өөрөө хийгээгүй өөрчлөлтийг шууд мэдэж нууц үгээ сэргээнэ
 * (OWASP ASVS V2.2.3). Загвар байхгүй, и-мэйл буруу эсвэл илгээж чадаагүй
 * бол нууц үгийн өөрчлөлтийг буцаахгүй - чимээгүй алгасна (error_log).
 *
 * Шаардлага: Dashboard\Controller-оос удамшсан класс (getService, getRequest,
 * getLanguageCode, generateRouteLink).
 *
 * @package Dashboard\User
 */
trait PasswordChangedNotification
{
    /**
     * @param array       $record  users мөр (username, email)
     * @param string|null $code    И-мэйлийн хэл; null бол одоогийн dashboard хэл
     * @param bool        $byAdmin Бусдын нууц үгийг system_coder сольсон - загвар
     *                             "системийн админ сольсон" гэж бичнэ (by_admin)
     */
    protected function notifyPasswordChanged(array $record, ?string $code = null, bool $byAdmin = false): void
    {
        $email = (string)($record['email'] ?? '');
        if (\filter_var($email, \FILTER_VALIDATE_EMAIL) === false) {
            return;
        }
        try {
            $template = $this->getService('template_service')
                ?->getByKeyword('password-changed', $code ?: $this->getLanguageCode());
            if (empty($template['content'])) {
                return;
            }
            $body = new MemoryTemplate();
            $body->source($template['content']);
            $body->set('username', $record['username'] ?? '');
            $body->set('email', $email);
            $body->set('date', \date('Y-m-d H:i'));
            $body->set('ip', $this->getRequest()->getServerParams()['REMOTE_ADDR'] ?? '');
            $body->set('login', $this->generateRouteLink('login', [], true));
            $body->set('by_admin', $byAdmin);
            $this->getService('mailer')
                ->mail($email, null, $template['title'], $body->output())
                ->send();
        } catch (\Throwable $err) {
            \error_log('password-changed notification: ' . $err->getMessage());
        }
    }
}
