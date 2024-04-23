<?php

namespace Raptor\Localization;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Indoraptor\IndoApplication;
use Indoraptor\Internal\InternalRequest;

class LocalizationMiddleware implements MiddlewareInterface
{
    private function request(?IndoApplication $indo, string $method, string $pattern, $payload = [])
    {
        try {
            $level = \ob_get_level();
            if (\ob_start()) {
                $indo?->handle(new InternalRequest($method, $pattern, $payload));
                $response = \json_decode(\ob_get_contents(), true)
                    ?? throw new \Exception(__CLASS__ . ': Error decoding Indoraptor response!');
                \ob_end_clean();
            }
        } catch (\Throwable $e) {
           if (isset($level)
                && \ob_get_level() > $level
            ) {
                 \ob_end_clean();
            }
            
            $response = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        }
        
        if (isset($response['error']['code'])
            && isset($response['error']['message'])
        ) {
            $error_code = $response['error']['code'];
            throw new \Exception($response['error']['message'], \is_int($error_code) ? $error_code : 0);
        }
        
        return $response;
    }
    
    private function retrieveLanguage(ServerRequestInterface $request)
    {
        try {
            return $this->request($request->getAttribute('indo'), 'GET', '/language');
        } catch (\Throwable $e) {
            if (\defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                \error_log($e->getMessage());
            }
            return ['en' => 'English'];
        }
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $language = $this->retrieveLanguage($request);
        $sess_lang_key = \explode('\\', __NAMESPACE__)[0] . '\\language\\code';
        if (isset($_SESSION[$sess_lang_key])
            && isset($language[$_SESSION[$sess_lang_key]])
        ) {
            $code = $_SESSION[$sess_lang_key];
        } else {
            $code = \key($language);
        }
        
        $text = [];
        try {
            $this->tryCreateDashboardTexts($request->getAttribute('indo'));
            
            $translations = $this->request(
                $request->getAttribute('indo'), 'POST', '/text/retrieve',
                ['code' => $code, 'table' => ['dashboard', 'default', 'user']]);
            foreach ($translations as $translation) {
                $text += $translation;
            }
        } catch (\Throwable $e) {
            if (\defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                \error_log($e->getMessage());
            }
        }
        return $handler->handle($request->withAttribute('localization',
            ['language' => $language, 'code' => $code, 'text' => $text]));
    }
    
    private function tryCreateDashboardTexts(?IndoApplication $indo)
    {
        try {
            $this->request($indo, 'INTERNAL', '/text/table/create/dashboard', [
                ['record' => ['keyword' => 'general-info', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Ерөнхий мэдээлэл'], 'en' => ['text' => 'General Info']]],
                ['record' => ['keyword' => 'reference-tables', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Лавлах хүснэгтүүд'], 'en' => ['text' => 'Reference Tables']]],
                ['record' => ['keyword' => 'text-settings', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Text Settings'], 'mn' => ['text' => 'Текстийн тохиргоо']]],
                ['record' => ['keyword' => 'select-an-image', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Зураг сонгох'], 'en' => ['text' => 'Select an Image']]],
                ['record' => ['keyword' => 'field-is-required', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'This field is required'], 'mn' => ['text' => 'Талбарын утгыг оруулна уу']]],
                ['record' => ['keyword' => 'please-confirm-info', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Мэдээллийг баталгаажуулна уу'], 'en' => ['text' => 'Please confirm infomations']]],
                ['record' => ['keyword' => 'enter-valid-email', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Please enter a valid email address'], 'mn' => ['text' => 'Имэйл хаягыг зөв оруулна уу']]],
                ['record' => ['keyword' => 'email-template-not-set', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Email template not found!'], 'mn' => ['text' => 'Цахим захианы загварыг тодорхойлоогүй байна!']]],
                ['record' => ['keyword' => 'record-successfully-deleted', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Record successfully deleted'], 'mn' => ['text' => 'Бичлэг амжилттай устлаа']]],
                ['record' => ['keyword' => 'created-by', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Үүсгэсэн хэрэглэгч'], 'en' => ['text' => 'Created by']]],
                ['record' => ['keyword' => 'date-created', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Үүссэн огноо'], 'en' => ['text' => 'Date created']]],
                ['record' => ['keyword' => 'updated-by', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Өөрчилсөн хэрэглэгч'], 'en' => ['text' => 'Modified by']]],
                ['record' => ['keyword' => 'date-modified', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Өөрчлөгдсөн огноо'], 'en' => ['text' => 'Date modified']]],
                ['record' => ['keyword' => 'invalid-values', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Утга буруу байна!'], 'en' => ['text' => 'Invalid values!']]],
                ['record' => ['keyword' => 'invalid-request', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Request is not valid!'], 'mn' => ['text' => 'Хүсэлт буруу байна!']]],
                ['record' => ['keyword' => 'system-no-permission', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Уучлаарай, таньд энэ мэдээлэлд хандах эрх олгогдоогүй байна!'], 'en' => ['text' => 'Access Denied, You don\'t have permission to access on this resource!']]],
                ['record' => ['keyword' => 'show-comment', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Show posted comments'], 'mn' => ['text' => 'Бичигдсэн сэтгэгдлүүдийг харуулна']]],
                ['record' => ['keyword' => 'hide-comment', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Бичигдсэн сэтгэгдлүүдийг харуулахгүй'], 'en' => ['text' => 'Hide posted comments']]],
                ['record' => ['keyword' => 'enable-comment', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Users can comment on this post'], 'mn' => ['text' => 'Зочид сэтгэгдэл үлдээж(бичиж) болно']]],
                ['record' => ['keyword' => 'disable-comment', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Зочид сэтгэгдэл үлдээж(бичиж) болохгүй'], 'en' => ['text' => 'Users cannot comment on this post']]],
                ['record' => ['keyword' => 'no-record-selected', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'No record selected'], 'mn' => ['text' => 'Бичлэг сонгогдоогүй байна']]],
                ['record' => ['keyword' => 'select-files', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Файлуудыг сонгох'], 'en' => ['text' => 'Select Files']]],
                ['record' => ['keyword' => 'upload-files', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Файлуудыг илгээх'], 'en' => ['text' => 'Upload Files']]],
                ['record' => ['keyword' => 'u-have-some-form-errors', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'You have some form errors. Please check below'], 'mn' => ['text' => 'Та мэдээллийг алдаатай бөглөсөн байна. Доорх талбаруудаа шалгана уу']]],
                ['record' => ['keyword' => 'add-record', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Add Record'], 'mn' => ['text' => 'Бичлэг нэмэх']]],
                ['record' => ['keyword' => 'edit-record', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Edit Record'], 'mn' => ['text' => 'Бичлэг засах']]],
                ['record' => ['keyword' => 'view-record', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Бичлэг харах'], 'en' => ['text' => 'View record']]],
                ['record' => ['keyword' => 'keyword-existing-in', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Keyword existing in'], 'mn' => ['text' => 'Түлхүүр үг давхцаж байна']]],
                ['record' => ['keyword' => 'record-insert-success', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Бичлэг амжилттай нэмэгдлээ'], 'en' => ['text' => 'Record successfully added']]],
                ['record' => ['keyword' => 'record-insert-error', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Error occurred while inserting record'], 'mn' => ['text' => 'Бичлэг нэмэх явцад алдаа гарлаа']]],
                ['record' => ['keyword' => 'record-update-success', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Record successfully updated'], 'mn' => ['text' => 'Бичлэг амжилттай засагдлаа']]],
                ['record' => ['keyword' => 'something-went-wrong', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Ямар нэгэн саатал учирлаа'], 'en' => ['text' => 'Looks like something went wrong']]],
                ['record' => ['keyword' => 'copy-text-from', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Текст хуулбарлах хэл'], 'en' => ['text' => 'Copy texts from']]],
                ['record' => ['keyword' => 'enter-language-details', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Хэлний мэдээллийг оруулна уу'], 'en' => ['text' => 'Provide language details']]],
                ['record' => ['keyword' => 'select-text-settings', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Текстийн тохиргоог сонгоно уу'], 'en' => ['text' => 'Select text settings']]],
                ['record' => ['keyword' => 'error-existing-lang-code', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Хэлний кодыг системд ашиглаж байгаа тул өөр код сонгоно уу!'], 'en' => ['text' => 'Хэлний кодыг системд ашиглаж байгаа тул өөр код сонгоно уу!']]],
                ['record' => ['keyword' => 'error-lang-existing', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Системд хэлийг ашиглаж байгаа тул өөр хэл сонгоно уу!'], 'en' => ['text' => 'Системд хэлийг ашиглаж байгаа тул өөр хэл сонгоно уу!']]],
                ['record' => ['keyword' => 'error-lang-name-existing', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Системд хэлний нэрийг ашиглаж байгаа тул өөр нэр ашиглана уу!'], 'en' => ['text' => 'Системд хэлний нэрийг ашиглаж байгаа тул өөр нэр ашиглана уу!']]],
                ['record' => ['keyword' => 'ask-dont-have-account-yet', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Don\'t have an account yet?'], 'mn' => ['text' => 'Хэрэглэгч болж амжаагүй байна уу?']]],
                ['record' => ['keyword' => 'enter-email-below', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Enter your e-mail address!'], 'mn' => ['text' => 'Бүртгэлтэй имэйл хаягаа доор бичнэ үү!']]],
                ['record' => ['keyword' => 'enter-personal-details', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Та доор хэсэгт хувийн мэдээллээ оруулна уу!'], 'en' => ['text' => 'Enter your personal details below!']]],
                ['record' => ['keyword' => 'error-password-empty', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Please enter password'], 'mn' => ['text' => 'Нууц үг талбарыг оруулна уу']]],
                ['record' => ['keyword' => 'error-username-empty', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Please enter username'], 'mn' => ['text' => 'Нэвтрэх нэр талбарыг оруулна уу']]],
                ['record' => ['keyword' => 'enter-email-empty', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Please enter email address'], 'mn' => ['text' => 'Имейл хаягыг оруулна уу']]],
                ['record' => ['keyword' => 'forgot-password', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Forgot password?'], 'mn' => ['text' => 'Нууц үгээ мартсан уу?']]],
                ['record' => ['keyword' => 'reset-email-sent', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'An reset e-mail has been sent.<br />Please check your email for further instructions!'], 'mn' => ['text' => 'Нууц үгийг шинэчлэх зааврыг амжилттай илгээлээ.<br />Та заасан имейл хаягаа шалгаж зааврын дагуу нууц үгээ шинэчлэнэ үү!']]],
                ['record' => ['keyword' => 'forgotten-password-reset', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Нууц үг дахин тааруулах'], 'en' => ['text' => 'Forgotten password reset']]],
                ['record' => ['keyword' => 'password-reset-request', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Нууц үгээ шинэчлэх хүсэлт'], 'en' => ['text' => 'Password reset request']]],
                ['record' => ['keyword' => 'set-new-password', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Set new password'], 'mn' => ['text' => 'Шинээр нууц үг тааруулах']]],
                ['record' => ['keyword' => 'retype-password', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Re-type Password'], 'mn' => ['text' => 'Нууц үгээ дахин бичнэ']]],
                ['record' => ['keyword' => 'set-new-password-success', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Your password has been changed successfully! Thank you'], 'mn' => ['text' => 'Нууц үгийг шинээр тохирууллаа. Шинэ нууц үгээ ашиглана уу']]],
                ['record' => ['keyword' => 'fill-new-password', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Please fill a new password!'], 'mn' => ['text' => 'Шинэ нууц үгийг оруулна уу!']]],
                ['record' => ['keyword' => 'password-must-confirm', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Please re-enter the password'], 'mn' => ['text' => 'Нууц үгийг давтан бичих хэрэгтэй']]],
                ['record' => ['keyword' => 'password-must-match', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Password entries must match'], 'mn' => ['text' => 'Нууц үгийг давтан бичихдээ зөв оруулах хэрэгтэй']]],
                ['record' => ['keyword' => 'to-complete-registration-check-email', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Thank you. To complete your registration please check your email'], 'mn' => ['text' => 'Танд баярлалаа. Бүртгэлээ баталгаажуулахын тулд заасан имейлээ шалгана уу']]],
                ['record' => ['keyword' => 'active-account-can-login', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'only active users can login'], 'mn' => ['text' => 'зөвхөн идэвхитэй хэрэглэгч системд нэвтэрч чадна']]],
                ['record' => ['keyword' => 'create-new-account', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Create new account'], 'mn' => ['text' => 'Хэрэглэгч шинээр үүсгэх']]],
                ['record' => ['keyword' => 'personal-info', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Personal Info'], 'mn' => ['text' => 'Хувийн мэдээлэл']]],
                ['record' => ['keyword' => 'change-password', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Change Password'], 'mn' => ['text' => 'Нууц үг']]],
                ['record' => ['keyword' => 'new-password', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Шинэ нууц үг'], 'en' => ['text' => 'New Password']]],
                ['record' => ['keyword' => 'retype-new-password', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Re-type New Password'], 'mn' => ['text' => 'Шинэ нууц үгийг давтах']]],
                ['record' => ['keyword' => 'edit-account', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'Edit account information'], 'mn' => ['text' => 'Хэрэглэгчийн мэдээлэл өөрчлөх']]],
                ['record' => ['keyword' => 'account-exists', 'type' => 'sys-defined'], 'content' => ['en' => ['text' => 'It looks like information belongs to an existing account'], 'mn' => ['text' => 'Заасан мэдээлэл бүхий хэрэглэгч аль хэдийн бүртгэгдсэн байна']]],
                ['record' => ['keyword' => 'request-new-account', 'type' => 'sys-defined'], 'content' => ['mn' => ['text' => 'Хэрэглэгчээр бүртгүүлэх хүсэлт'], 'en' => ['text' => 'Request a new account']]]
            ]);
        } catch (\Throwable $e) {
            if (\defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                \error_log($e->getMessage());
            }
        }
    }
}
