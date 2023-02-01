<?php

namespace Raptor\Localization;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Indoraptor\InternalRequest;
use Indoraptor\IndoApplication;

class LocalizationMiddleware implements MiddlewareInterface
{
    private function request(?IndoApplication $indo, string $method, string $pattern, $payload = [])
    {
        try {
            if (\ob_start()) {
                $indo?->handle(new InternalRequest($method, $pattern, $payload));
                $response = \json_decode(\ob_get_contents(), true)
                    ?? throw new \Exception(__CLASS__ . ': Error decoding Indoraptor response!');
                \ob_end_clean();
            }
        } catch (\Throwable $th) {
            if (\ob_get_level()) {
                \ob_end_clean();
            }
            
            $response = ['error' => ['code' => $th->getCode(), 'message' => $th->getMessage()]];
        }
        
        if (isset($response['error']['code'])
            && isset($response['error']['message'])
        ) {
            throw new \Exception($response['error']['message'], $response['error']['code']);
        }
        
        return $response;
    }
    
    private function retrieveLanguage(ServerRequestInterface $request)
    {
        try {
            return $this->request($request->getAttribute('indo'), 'GET', '/language');
        } catch (\Throwable $th) {
            if (\defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                \error_log($th->getMessage());
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
        } catch (\Throwable $th) {
            if (\defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                \error_log($th->getMessage());
            }
        }
        return $handler->handle($request->withAttribute('localization',
            ['language' => $language, 'code' => $code, 'text' => $text]));
    }
    
    public function tryCreateDashboardTexts(?IndoApplication $indo)
    {
        try {
            $this->request($indo, 'INTERNAL', '/text/table/create/dashboard', [
                ['record' => ['keyword' => 'access-log', 'created_at' => '2018-12-25 17:14:34'], 'content' => ['mn' => ['text' => 'Хандалтын протокол'], 'en' => ['text' => 'Access Log']]],
                ['record' => ['keyword' => 'role', 'created_at' => '2018-12-25 17:14:38'], 'content' => ['mn' => ['text' => 'Үүрэг'], 'en' => ['text' => 'Role']]],
                ['record' => ['keyword' => 'main-image', 'created_at' => '2018-12-25 17:14:39'], 'content' => ['mn' => ['text' => 'Үндсэн зураг'], 'en' => ['text' => 'Main Image']]],
                ['record' => ['keyword' => 'mail-carrier', 'created_at' => '2018-12-25 17:14:40'], 'content' => ['mn' => ['text' => 'Шууданч'], 'en' => ['text' => 'Mail carrier']]],
                ['record' => ['keyword' => 'to-upload', 'created_at' => '2018-12-25 17:14:40'], 'content' => ['en' => ['text' => 'To Upload'], 'mn' => ['text' => 'Байршуулах']]],
                ['record' => ['keyword' => 'accounts-note', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Та энэ хэсэгт системийн хэрэглэгчдийн бүртгэлийг удирдах боломжтой'], 'en' => ['text' => 'Here you can manage system accounts']]],
                ['record' => ['keyword' => 'languages-note', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Here you can add remove system locales'], 'mn' => ['text' => 'Та энэ хэсэгт системийн хэлнүүдийг нэмж хасч өөрчлөх боломжтой']]],
                ['record' => ['keyword' => 'text-note', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Та энэ хэсэгт системийн бүх хэсэгт харагдах текстийг удирдан зохион байгуулах боломжтой'], 'en' => ['text' => 'Here you can manage system-wide texts']]],
                ['record' => ['keyword' => 'title-note', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'title of record'], 'mn' => ['text' => 'бичлэгийн гарчиг']]],
                ['record' => ['keyword' => 'short-note', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'short version of content'], 'mn' => ['text' => 'бичлэгийн хураангуй агуулга']]],
                ['record' => ['keyword' => 'full-note', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'бичлэгийн бүрэн эх'], 'en' => ['text' => 'full version of content']]],
                ['record' => ['keyword' => 'content-image-note', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Зурагны төрөл ба мэдээллийг оруулах шаардлагатай'], 'en' => ['text' => 'Image type and information need to be specified']]],
                ['record' => ['keyword' => 'pages-note', 'created_at' => '2019-06-06 18:14:18'], 'content' => ['mn' => ['text' => 'Та энэхүү хэсэгт вебийн үндсэн мэдээлэл хуудсуудыг удирдах боломжтой'], 'en' => ['text' => 'Here in this area, you can manage website information & pages']]],
                ['record' => ['keyword' => 'news-note', 'created_at' => '2019-06-06 18:14:18'], 'content' => ['en' => ['text' => 'Here in this area, you can manage news, events and announcements'], 'mn' => ['text' => 'Та энэхүү хэсэгт мэдээ мэдээлэл, үйл явдал, заруудыг удирдах боломжтой']]],

                ['record' => ['keyword' => 'general-info', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Ерөнхий мэдээлэл'], 'en' => ['text' => 'General Info']]],
                ['record' => ['keyword' => 'general-tables', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'General Tables'], 'mn' => ['text' => 'Ерөнхий хүснэгтүүд']]],
                ['record' => ['keyword' => 'document-templates', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Баримт бичгийн загварууд'], 'en' => ['text' => 'Document Templates']]],
                ['record' => ['keyword' => 'reference-tables', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Лавлах хүснэгтүүд'], 'en' => ['text' => 'Reference Tables']]],
                ['record' => ['keyword' => 'text-settings', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Text Settings'], 'mn' => ['text' => 'Текстийн тохиргоо']]],
                ['record' => ['keyword' => 'world-countries', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'World Countries'], 'mn' => ['text' => 'Дэлхийн улсууд']]],
                ['record' => ['keyword' => 'get-initial-code', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'үүсгэгч кодыг авах'], 'en' => ['text' => 'get initial code']]],
                ['record' => ['keyword' => 'user-notifications', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'User Notifications'], 'mn' => ['text' => 'Хэрэглэгчийн мэдэгдэл']]],

                ['record' => ['keyword' => 'quick-actions', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Хялбар цэс'], 'en' => ['text' => 'Quick Actions']]],
                ['record' => ['keyword' => 'my-calendar', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Миний календар'], 'en' => ['text' => 'My Calendar']]],
                ['record' => ['keyword' => 'my-profile', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'My Profile'], 'mn' => ['text' => 'Миний профиль']]],
                ['record' => ['keyword' => 'last-login', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Сүүлд нэвтэрсэн'], 'en' => ['text' => 'Last Login']]],

                ['record' => ['keyword' => 'select-a-country', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Улсаа сонгоорой'], 'en' => ['text' => 'Select a Country']]],
                ['record' => ['keyword' => 'sub-pages', 'created_at' => '2019-06-06 18:14:18'], 'content' => ['mn' => ['text' => 'Дэд хуудсууд'], 'en' => ['text' => 'Sub Pages']]],
                ['record' => ['keyword' => 'date-hand', 'created_at' => '2019-06-06 18:14:19'], 'content' => ['en' => ['text' => 'Information date'], 'mn' => ['text' => 'Мэдээллийн огноо']]],
                ['record' => ['keyword' => 'select-image', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Зураг сонгох'], 'en' => ['text' => 'Select an Image']]],
                ['record' => ['keyword' => 'choose-file', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Файлаа сонгоно уу!'], 'en' => ['text' => 'Choose a file!']]],
                ['record' => ['keyword' => 'delete-file-ask', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Are you sure you want to delete this file?'], 'mn' => ['text' => 'Та энэ файлыг устгахдаа итгэлтэй байна уу?']]],
                ['record' => ['keyword' => 'delete-file-success', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Файлыг амжилттай устгалаа'], 'en' => ['text' => 'File deleted successfully']]],
                ['record' => ['keyword' => 'delete-file-error', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Файл устгах явцад алдаа гарлаа'], 'en' => ['text' => 'Error occurred while deleting the file']]],
                ['record' => ['keyword' => 'field-is-required', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'This field is required'], 'mn' => ['text' => 'Талбарын утгыг оруулна уу']]],
                ['record' => ['keyword' => 'confirm-info', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Мэдээллийг баталгаажуулна уу'], 'en' => ['text' => 'Please confirm infomations']]],
                ['record' => ['keyword' => 'account-not-found', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Хэрэглэгчийн мэдээлэл олдсонгүй!'], 'en' => ['text' => 'Account not found!']]],
                ['record' => ['keyword' => 'email-template-not-set', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Email template not found!'], 'mn' => ['text' => 'Цахим захианы загварыг тодорхойлоогүй байна!']]],
                ['record' => ['keyword' => 'emailer-not-set', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Email carrier not found!'], 'mn' => ['text' => 'Шууданчыг тохируулаагүй байна!']]],
                ['record' => ['keyword' => 'email-succesfully-sent', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Email successfully sent to destination'], 'mn' => ['text' => 'Цахим шуудан амжилттай илгээгдлээ']]],
                ['record' => ['keyword' => 'enter-valid-email', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Please enter a valid email address'], 'mn' => ['text' => 'Имэйл хаягыг зөв оруулна уу']]],
                ['record' => ['keyword' => 'no-content-in-time', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Одоогоор харуулах мэдээлэл байхгүй'], 'en' => ['text' => 'There is no content to display']]],
                ['record' => ['keyword' => 'rank-on-site', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'бичлэг сайт дээр харагдах дараалал'], 'en' => ['text' => 'rank of the post']]],
                ['record' => ['keyword' => 'record-successfully-deleted', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Record successfully deleted'], 'mn' => ['text' => 'Бичлэг амжилттай устлаа']]],
                ['record' => ['keyword' => 'created-by', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Үүсгэсэн хэрэглэгч'], 'en' => ['text' => 'Created by']]],
                ['record' => ['keyword' => 'date-created', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Үүссэн огноо'], 'en' => ['text' => 'Date created']]],
                ['record' => ['keyword' => 'more-info', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Дэлгэрэнгүй мэдээлэл'], 'en' => ['text' => 'More Info']]],
                ['record' => ['keyword' => 'invalid-request', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Request is not valid!'], 'mn' => ['text' => 'Хүсэлт буруу байна!']]],
                ['record' => ['keyword' => 'cant-complete-request', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Хүсэлтийг гүйцээх боломжгүй'], 'en' => ['text' => 'Can\'t complete request']]],
                ['record' => ['keyword' => 'show-comment', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Show posted comments'], 'mn' => ['text' => 'Бичигдсэн сэтгэгдлүүдийг харуулна']]],
                ['record' => ['keyword' => 'hide-comment', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Бичигдсэн сэтгэгдлүүдийг харуулахгүй'], 'en' => ['text' => 'Hide posted comments']]],
                ['record' => ['keyword' => 'enable-comment', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Users can comment on this post'], 'mn' => ['text' => 'Зочид сэтгэгдэл үлдээж(бичиж) болно']]],
                ['record' => ['keyword' => 'disable-comment', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Зочид сэтгэгдэл үлдээж(бичиж) болохгүй'], 'en' => ['text' => 'Users cannot comment on this post']]],
                ['record' => ['keyword' => 'settings-general', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'General settings'], 'mn' => ['text' => 'Ерөнхий тохируулгууд']]],
                ['record' => ['keyword' => 'please-enter-title', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Please enter Title!'], 'mn' => ['text' => 'Гарчиг оруулна уу!']]],
                ['record' => ['keyword' => 'please-select-an-action', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Үйлдлээ сонгоно уу'], 'en' => ['text' => 'Please select an action']]],
                ['record' => ['keyword' => 'no-record-selected', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'No record selected'], 'mn' => ['text' => 'Бичлэг сонгогдоогүй байна']]],
                ['record' => ['keyword' => 'select-files', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Файлуудыг сонгох'], 'en' => ['text' => 'Select Files']]],
                ['record' => ['keyword' => 'upload-files', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Файлуудыг илгээх'], 'en' => ['text' => 'Upload Files']]],
                ['record' => ['keyword' => 'pl-upload-failed', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Файлыг серверлүү хуулах явцад алдаа гарлаа'], 'en' => ['text' => 'One of uploads failed. Please retry']]],
                ['record' => ['keyword' => 'image-files', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Image Files'], 'mn' => ['text' => 'Зургийн файлууд']]],
                ['record' => ['keyword' => 'pdf-files', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'PDF Files'], 'mn' => ['text' => 'PDF файлууд']]],
                ['record' => ['keyword' => 'insert-to-content', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Агуулгад нэмэх'], 'en' => ['text' => 'Insert to content']]],
                ['record' => ['keyword' => 'invalid-response', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Invalid response'], 'mn' => ['text' => 'Алдаатай хариу']]],
                ['record' => ['keyword' => 'connection-error', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Холболтын алдаа'], 'en' => ['text' => 'Connection error']]],
                ['record' => ['keyword' => 'invalid-request-data', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Required fields failed to validate, please check your inputs & try again!'], 'mn' => ['text' => 'Мэдээлэл буруу оруулсан байна. Анхааралтай бөглөөд дахин оролдоно уу']]],
                ['record' => ['keyword' => 'generate-reports', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Generate Reports'], 'mn' => ['text' => 'Тайлан гаргах']]],
                ['record' => ['keyword' => 'system-settings', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'System Settings'], 'mn' => ['text' => 'Системийн тохируулга']]],
                ['record' => ['keyword' => 'u-have-some-form-errors', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'You have some form errors. Please check below'], 'mn' => ['text' => 'Та мэдээллийг алдаатай бөглөсөн байна. Доорх талбаруудаа шалгана уу']]],
                ['record' => ['keyword' => 'ur-form-validation-is-successful!', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Your form validation is successful!'], 'mn' => ['text' => 'Та мэдээллийг амжилтай бөглөсөн байна!']]],
                ['record' => ['keyword' => 'active-record-shown', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'active record will shown on site'], 'mn' => ['text' => 'идэвхитэй бичлэг сайт дээр харагдана']]],
                ['record' => ['keyword' => 'delete-record-ask', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Та энэхүү бичлэгийг устгахдаа итгэлтэй байна уу?'], 'en' => ['text' => 'Are you sure to delete this record?']]],
                ['record' => ['keyword' => 'edit-record', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Edit Record'], 'mn' => ['text' => 'Бичлэг засах']]],
                ['record' => ['keyword' => 'add-record', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Add Record'], 'mn' => ['text' => 'Бичлэг нэмэх']]],
                ['record' => ['keyword' => 'empty-code', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Empty Code!'], 'mn' => ['text' => 'Код заавал өгөгдөх ёстой!']]],
                ['record' => ['keyword' => 'empty-id', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Дугаар заавал өгөгдөх ёстой!'], 'en' => ['text' => 'Empty ID!']]],
                ['record' => ['keyword' => 'empty-keyword', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Empty Keyword!'], 'mn' => ['text' => 'Түлхүүр үгийг заавал бичих ёстой!']]],
                ['record' => ['keyword' => 'incomplete-values', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Шаардлагатай талбаруудын утгыг бүрэн оруулна уу!'], 'en' => ['text' => 'Please enter values for required fields!']]],
                ['record' => ['keyword' => 'inline-table', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Inline Table'], 'mn' => ['text' => 'Хүснэгт дотор']]],
                ['record' => ['keyword' => 'invalid-table-name', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Table name is not valid!'], 'mn' => ['text' => 'Хүснэгтийн нэр буруу байна!']]],
                ['record' => ['keyword' => 'invalid-values', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Утга буруу байна!'], 'en' => ['text' => 'Invalid values!']]],
                ['record' => ['keyword' => 'keyword-existing', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Keyword existing in'], 'mn' => ['text' => 'Түлхүүр үг давхцаж байна']]],
                ['record' => ['keyword' => 'record-error-unknown', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Unknown error occurred while processing the request on the server'], 'mn' => ['text' => 'Бичлэгийн явцад алдаа гарлаа']]],
                ['record' => ['keyword' => 'record-insert-success', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Бичлэг амжилттай нэмэгдлээ'], 'en' => ['text' => 'Record successfully added']]],
                ['record' => ['keyword' => 'record-insert-error', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Error occurred while inserting record'], 'mn' => ['text' => 'Бичлэг нэмэх явцад алдаа гарлаа']]],
                ['record' => ['keyword' => 'record-keyword-error', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Түлхүүр үг давхцах боломжгүй'], 'en' => ['text' => 'It looks like [keyword] belongs to an existing record']]],
                ['record' => ['keyword' => 'record-update-success', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Record successfully edited'], 'mn' => ['text' => 'Бичлэг амжилттай засагдлаа']]],
                ['record' => ['keyword' => 'record-update-error', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Error occurred while updating record'], 'mn' => ['text' => 'Бичлэг засах явцад алдаа гарлаа']]],
                ['record' => ['keyword' => 'duplicate-records', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Duplicate records'], 'mn' => ['text' => 'Бичлэгүүд давхцаж байна']]],
                ['record' => ['keyword' => 'record-not-found', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Record not found'], 'mn' => ['text' => 'Бичлэг олдсонгүй']]],
                ['record' => ['keyword' => 'something-went-wrong', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['mn' => ['text' => 'Ямар нэгэн саатал учирлаа'], 'en' => ['text' => 'Looks like something went wrong']]],
                ['record' => ['keyword' => 'error-oops', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Oops..'], 'mn' => ['text' => 'Өө хөөрхий..']]],
                ['record' => ['keyword' => 'error-we-working', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'We\'re working on it'], 'mn' => ['text' => 'Алдааг удахгүй засах болно']]],
                ['record' => ['keyword' => 'view-record', 'created_at' => '2019-07-02 00:41:27'], 'content' => ['mn' => ['text' => 'Бичлэг харах'], 'en' => ['text' => 'View record']]],
                ['record' => ['keyword' => 'updated-by', 'created_at' => '2019-07-02 02:23:57'], 'content' => ['mn' => ['text' => 'Өөрчилсөн хэрэглэгч'], 'en' => ['text' => 'Modified by']]],
                ['record' => ['keyword' => 'date-modified', 'created_at' => '2019-07-02 02:25:20'], 'content' => ['mn' => ['text' => 'Өөрчлөгдсөн огноо'], 'en' => ['text' => 'Date modified']]],
                ['record' => ['keyword' => 'system-no-permission', 'created_at' => '2019-07-02 02:25:20'], 'content' => ['mn' => ['text' => 'Уучлаарай, таньд энэ мэдээлэлд хандах эрх олгогдоогүй байна!'], 'en' => ['text' => 'Access Denied, You don\'t have permission to access on this resource!']]],
                ['record' => ['keyword' => 'website-total-report', 'created_at' => '2019-07-02 02:25:20'], 'content' => ['mn' => ['text' => 'Веб нийт хандалтын тайлан'], 'en' => ['text' => 'Website total access report']]],
                ['record' => ['keyword' => 'website-mounthly-report', 'created_at' => '2020-02-08 18:28:37'], 'content' => ['mn' => ['text' => 'Веб хандалтын сарын тайлан'], 'en' => ['text' => 'Website mounthly report']]],
                ['record' => ['keyword' => 'google-analytics', 'created_at' => '2020-02-08 18:33:35'], 'content' => ['mn' => ['text' => 'Гүүгл аналитик'], 'en' => ['text' => 'Google Analytics']]],
                ['record' => ['keyword' => 'copy-text-from', 'created_at' => '2020-02-08 22:02:21'], 'content' => ['mn' => ['text' => 'Текст хуулбарлах хэл'], 'en' => ['text' => 'Copy texts from']]],
                ['record' => ['keyword' => 'enter-language-details', 'created_at' => '2019-06-06 18:14:19'], 'content' => ['mn' => ['text' => 'Хэлний мэдээллийг оруулна уу'], 'en' => ['text' => 'Provide language details']]],
                ['record' => ['keyword' => 'lang-code-existing', 'created_at' => '2019-06-06 18:14:19'], 'content' => ['mn' => ['text' => 'Хэлний кодыг системд ашиглаж байгаа тул өөр код сонгоно уу!'], 'en' => ['text' => 'Хэлний кодыг системд ашиглаж байгаа тул өөр код сонгоно уу!']]],
                ['record' => ['keyword' => 'lang-existing', 'created_at' => '2019-06-06 18:14:19'], 'content' => ['mn' => ['text' => 'Системд хэлийг ашиглаж байгаа тул өөр хэл сонгоно уу!'], 'en' => ['text' => 'Системд хэлийг ашиглаж байгаа тул өөр хэл сонгоно уу!']]],
                ['record' => ['keyword' => 'lang-name-existing', 'created_at' => '2019-06-06 18:14:19'], 'content' => ['mn' => ['text' => 'Системд хэлний нэрийг ашиглаж байгаа тул өөр нэр ашиглана уу!'], 'en' => ['text' => 'Системд хэлний нэрийг ашиглаж байгаа тул өөр нэр ашиглана уу!']]],
                ['record' => ['keyword' => 'language-added', 'created_at' => '2019-06-06 18:14:19'], 'content' => ['mn' => ['text' => 'Системд шинэ хэл нэмлээ'], 'en' => ['text' => 'Системд шинэ хэл нэмлээ']]],
                ['record' => ['keyword' => 'select-text-settings', 'created_at' => '2019-06-06 18:14:19'], 'content' => ['mn' => ['text' => 'Текстийн тохиргоог сонгоно уу'], 'en' => ['text' => 'Select text settings']]],
                ['record' => ['keyword' => 'texted-tables:', 'created_at' => '2019-06-06 18:14:19'], 'content' => ['mn' => ['text' => 'Текстийг хуулсан хүснэгтүүд:'], 'en' => ['text' => 'Tables of text copied:']]],
                ['record' => ['keyword' => 'dont-have-account-yet', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Don\'t have an account yet?'], 'mn' => ['text' => 'Хэрэглэгч болж амжаагүй байна уу?']]],
                ['record' => ['keyword' => 'username-or-email', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Username or Email'], 'mn' => ['text' => 'Нэр эсвэл имейл']]],
                ['record' => ['keyword' => 'enter-account-details', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Enter your account details below:'], 'mn' => ['text' => 'Нэвтрэх эрхийн мэдээлэл бөглөнө үү:']]],
                ['record' => ['keyword' => 'enter-email-below', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Enter your e-mail address!'], 'mn' => ['text' => 'Бүртгэлтэй имэйл хаягаа доор бичнэ үү!']]],
                ['record' => ['keyword' => 'enter-personal-details', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['mn' => ['text' => 'Та доор хэсэгт хувийн мэдээллээ оруулна уу!'], 'en' => ['text' => 'Enter your personal details below!']]],
                ['record' => ['keyword' => 'enter-username', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Enter your username'], 'mn' => ['text' => 'Хэрэглэгчийн нэрээ оруулна уу']]],
                ['record' => ['keyword' => 'enter-password', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Enter your password'], 'mn' => ['text' => 'Нууц үгээ оруулна уу']]],
                ['record' => ['keyword' => 'enter-username-password', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Enter your username and password'], 'mn' => ['text' => 'Хэрэглэгчийн нэр ба нууц үгээ оруулна уу']]],
                ['record' => ['keyword' => 'error-account-inactive', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['mn' => ['text' => 'Нэвтрэх эрх идэвхигүй байна'], 'en' => ['text' => 'User is not active']]],
                ['record' => ['keyword' => 'error-incorrect-credentials', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Invalid username or password'], 'mn' => ['text' => 'Нэвтрэх нэр эсвэл нууц үг буруу байна']]],
                ['record' => ['keyword' => 'error-password-empty', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Please enter password'], 'mn' => ['text' => 'Нууц үг талбарыг оруулна уу']]],
                ['record' => ['keyword' => 'error-username-empty', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Please enter username'], 'mn' => ['text' => 'Нэвтрэх нэр талбарыг оруулна уу']]],
                ['record' => ['keyword' => 'enter-email-empty', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Please enter email address'], 'mn' => ['text' => 'Имейл хаягыг оруулна уу']]],
                ['record' => ['keyword' => 'forgot-password', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Forgot password?'], 'mn' => ['text' => 'Нууц үгээ мартсан уу?']]],
                ['record' => ['keyword' => 'or-login-with', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Or login with'], 'mn' => ['text' => 'Эсвэл энүүгээр']]],
                ['record' => ['keyword' => 'please-login', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Sign In To Dashboard'], 'mn' => ['text' => 'Хэрэглэгчийн эрхээр нэвтэрнэ']]],
                ['record' => ['keyword' => 'remember-me', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Remember me'], 'mn' => ['text' => 'Намайг сана!']]],
                ['record' => ['keyword' => 'reset-email-sent', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'An reset e-mail has been sent.<br />Please check your email for further instructions!'], 'mn' => ['text' => 'Нууц үгийг шинэчлэх зааврыг амжилттай илгээлээ.<br />Та заасан имейл хаягаа шалгаж зааврын дагуу нууц үгээ шинэчлэнэ үү!']]],
                ['record' => ['keyword' => 'forgotten-password-reset', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['mn' => ['text' => 'Нууц үг дахин тааруулах'], 'en' => ['text' => 'Forgotten password reset']]],
                ['record' => ['keyword' => 'set-new-password', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Set new password'], 'mn' => ['text' => 'Шинээр нууц үг тааруулах']]],
                ['record' => ['keyword' => 'retype-password', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Re-type Password'], 'mn' => ['text' => 'Нууц үгээ дахин бичнэ']]],
                ['record' => ['keyword' => 'set-new-password-success', 'created_at' => '2018-11-12 00:21:45'], 'content' => ['en' => ['text' => 'Your password has been changed successfully! Thank you'], 'mn' => ['text' => 'Нууц үгийг шинээр тохирууллаа. Шинэ нууц үгээ ашиглана уу']]],
                ['record' => ['keyword' => 'fill-new-password', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Please fill a new password!'], 'mn' => ['text' => 'Шинэ нууц үгийг оруулна уу!']]],
                ['record' => ['keyword' => 'password-must-confirm', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Please re-enter the password'], 'mn' => ['text' => 'Нууц үгийг давтан бичих хэрэгтэй']]],
                ['record' => ['keyword' => 'password-must-match', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Password entries must match'], 'mn' => ['text' => 'Нууц үгийг давтан бичихдээ зөв оруулах хэрэгтэй']]],
                ['record' => ['keyword' => 'to-complete-registration-check-email', 'created_at' => '2019-06-06 18:14:22'], 'content' => ['en' => ['text' => 'Thank you. To complete your registration please check your email'], 'mn' => ['text' => 'Танд баярлалаа. Бүртгэлээ баталгаажуулахын тулд заасан имейлээ шалгана уу']]],
                ['record' => ['keyword' => 'active-account-can-login', 'created_at' => '2018-12-03 17:12:31'], 'content' => ['en' => ['text' => 'only active users can login'], 'mn' => ['text' => 'зөвхөн идэвхитэй хэрэглэгч системд нэвтэрч чадна']]],
                ['record' => ['keyword' => 'create-new-account', 'created_at' => '2018-12-03 17:12:31'], 'content' => ['en' => ['text' => 'Create new account'], 'mn' => ['text' => 'Хэрэглэгч шинээр үүсгэх']]],
                ['record' => ['keyword' => 'change-avatar', 'created_at' => '2018-12-03 17:12:31'], 'content' => ['en' => ['text' => 'Change Avatar'], 'mn' => ['text' => 'Хөрөг солих']]],
                ['record' => ['keyword' => 'change-password', 'created_at' => '2018-12-03 17:12:31'], 'content' => ['en' => ['text' => 'Change Password'], 'mn' => ['text' => 'Нууц үг']]],
                ['record' => ['keyword' => 'edit-account', 'created_at' => '2018-12-03 17:12:31'], 'content' => ['en' => ['text' => 'Edit account information'], 'mn' => ['text' => 'Хэрэглэгчийн мэдээлэл өөрчлөх']]],
                ['record' => ['keyword' => 'new-account', 'created_at' => '2018-12-03 17:12:31'], 'content' => ['mn' => ['text' => 'Шинэ хэрэглэгч'], 'en' => ['text' => 'New Account']]],
                ['record' => ['keyword' => 'personal-info', 'created_at' => '2018-12-03 17:12:31'], 'content' => ['en' => ['text' => 'Personal Info'], 'mn' => ['text' => 'Хувийн мэдээлэл']]],
                ['record' => ['keyword' => 'new-password', 'created_at' => '2018-12-03 17:12:31'], 'content' => ['mn' => ['text' => 'Шинэ нууц үг'], 'en' => ['text' => 'New Password']]],
                ['record' => ['keyword' => 'account-role', 'created_at' => '2018-12-03 17:12:31'], 'content' => ['mn' => ['text' => 'Хэрэглэгчийн дүр'], 'en' => ['text' => 'Account Role']]],
                ['record' => ['keyword' => 'retype-new-password', 'created_at' => '2018-12-03 17:12:31'], 'content' => ['en' => ['text' => 'Re-type New Password'], 'mn' => ['text' => 'Шинэ нууц үгийг давтах']]],
                ['record' => ['keyword' => 'account-email-exists', 'created_at' => '2018-12-03 17:12:31'], 'content' => ['en' => ['text' => 'It looks like email address belongs to an existing account'], 'mn' => ['text' => 'Заасан имэйл хаяг өөр хэрэглэгч дээр бүртгэгдсэн байна']]],
                ['record' => ['keyword' => 'account-exists', 'created_at' => '2018-12-03 17:12:31'], 'content' => ['en' => ['text' => 'It looks like information belongs to an existing account'], 'mn' => ['text' => 'Заасан мэдээлэл бүхий хэрэглэгч аль хэдийн бүртгэгдсэн байна']]],
                ['record' => ['keyword' => 'password-reset-request', 'created_at' => '2019-06-16 23:02:46'], 'content' => ['mn' => ['text' => 'Нууц үгээ шинэчлэх хүсэлт'], 'en' => ['text' => 'Password reset request']]],
                ['record' => ['keyword' => 'request-new-account', 'created_at' => '2019-06-17 18:38:30'], 'content' => ['mn' => ['text' => 'Хэрэглэгчээр бүртгүүлэх хүсэлт'], 'en' => ['text' => 'Request a new account']]]
            ]);
        } catch (\Exception $ex) {
            // do nothing
        }
    }
}
