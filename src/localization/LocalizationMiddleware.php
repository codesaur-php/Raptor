<?php

namespace Raptor\Localization;

use Throwable;
use Exception;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Indoraptor\InternalRequest;

class LocalizationMiddleware implements MiddlewareInterface
{
    function request($indo, string $method, string $pattern, $payload = array())
    {
        try {
            ob_start();
            $indo->handle(new InternalRequest($method, $pattern, $payload));
            $response = json_decode(ob_get_contents(), true);
            ob_end_clean();
        } catch (Throwable $th) {
            ob_end_clean();
            
            $response = array('error' => array('code' => $th->getCode(), 'message' => $th->getMessage()));
        }
        
        if (isset($response['error']['code'])
            && isset($response['error']['message'])
        ) {
            throw new Exception($response['error']['message'], $response['error']['code']);
        }
        
        return $response;
    }
    
    function retrieveLanguage(ServerRequestInterface $request): array
    {
        try {
            return $this->request($request->getAttribute('indo'), 'GET', '/language');
        } catch (Throwable $e) {
            if (defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                error_log($e->getMessage());
            }
            return array('en' => 'English');
        }
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $language = $this->retrieveLanguage($request);
        $sess_lang_key = explode('\\', __NAMESPACE__)[0] . '\\language\\code';
        if (isset($_SESSION[$sess_lang_key])
            && isset($language[$_SESSION[$sess_lang_key]])
        ) {
            $code = $_SESSION[$sess_lang_key];
        } else {
            $code = key($language);
        }
        
        $text = array();
        try {
            
            $this->tryCreateDashboardTexts($request->getAttribute('indo'));
            
            $translations = $this->request(
                $request->getAttribute('indo'), 'POST', '/text/retrieve',
                array('code' => $code, 'table' => ['dashboard', 'default', 'user']));
            foreach ($translations as $translation) {
                
                $text += $translation;
            }
        } catch (Throwable $e) {
            if (defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                error_log($e->getMessage());
            }
        }
        
        return $handler->handle($request->withAttribute('localization',
            array('language' => $language, 'code' => $code, 'text' => $text)));
    }
    
    public function tryCreateDashboardTexts($indo)
    {
        try {
            $this->request($indo, 'INTERNAL', '/text/table/create/dashboard', array(
                array('record' => array('keyword' => 'access-log', 'created_at' => '2018-12-25 17:14:34'), 'content' => array('mn' => array('text' => 'Хандалтын протокол'), 'en' => array('text' => 'Access Log'))),
                array('record' => array('keyword' => 'role', 'created_at' => '2018-12-25 17:14:38'), 'content' => array('mn' => array('text' => 'Үүрэг'), 'en' => array('text' => 'Role'))),
                array('record' => array('keyword' => 'main-image', 'created_at' => '2018-12-25 17:14:39'), 'content' => array('mn' => array('text' => 'Үндсэн зураг'), 'en' => array('text' => 'Main Image'))),
                array('record' => array('keyword' => 'mail-carrier', 'created_at' => '2018-12-25 17:14:40'), 'content' => array('mn' => array('text' => 'Шууданч'), 'en' => array('text' => 'Mail carrier'))),
                array('record' => array('keyword' => 'to-upload', 'created_at' => '2018-12-25 17:14:40'), 'content' => array('en' => array('text' => 'To Upload'), 'mn' => array('text' => 'Байршуулах'))),
                array('record' => array('keyword' => 'accounts-note', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Та энэ хэсэгт системийн хэрэглэгчдийн бүртгэлийг удирдах боломжтой'), 'en' => array('text' => 'Here you can manage system accounts'))),
                array('record' => array('keyword' => 'languages-note', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Here you can add remove system locales'), 'mn' => array('text' => 'Та энэ хэсэгт системийн хэлнүүдийг нэмж хасч өөрчлөх боломжтой'))),
                array('record' => array('keyword' => 'text-note', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Та энэ хэсэгт системийн бүх хэсэгт харагдах текстийг удирдан зохион байгуулах боломжтой'), 'en' => array('text' => 'Here you can manage system-wide texts'))),
                array('record' => array('keyword' => 'title-note', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'title of record'), 'mn' => array('text' => 'бичлэгийн гарчиг'))),
                array('record' => array('keyword' => 'short-note', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'short version of content'), 'mn' => array('text' => 'бичлэгийн хураангуй агуулга'))),
                array('record' => array('keyword' => 'full-note', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'бичлэгийн бүрэн эх'), 'en' => array('text' => 'full version of content'))),
                array('record' => array('keyword' => 'content-image-note', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Зурагны төрөл ба мэдээллийг оруулах шаардлагатай'), 'en' => array('text' => 'Image type and information need to be specified'))),
                array('record' => array('keyword' => 'pages-note', 'created_at' => '2019-06-06 18:14:18'), 'content' => array('mn' => array('text' => 'Та энэхүү хэсэгт вебийн үндсэн мэдээлэл хуудсуудыг удирдах боломжтой'), 'en' => array('text' => 'Here in this area, you can manage website information & pages'))),
                array('record' => array('keyword' => 'news-note', 'created_at' => '2019-06-06 18:14:18'), 'content' => array('en' => array('text' => 'Here in this area, you can manage news, events and announcements'), 'mn' => array('text' => 'Та энэхүү хэсэгт мэдээ мэдээлэл, үйл явдал, заруудыг удирдах боломжтой'))),

                array('record' => array('keyword' => 'general-info', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Ерөнхий мэдээлэл'), 'en' => array('text' => 'General Info'))),
                array('record' => array('keyword' => 'general-tables', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'General Tables'), 'mn' => array('text' => 'Ерөнхий хүснэгтүүд'))),
                array('record' => array('keyword' => 'document-templates', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Баримт бичгийн загварууд'), 'en' => array('text' => 'Document Templates'))),
                array('record' => array('keyword' => 'reference-tables', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Лавлах хүснэгтүүд'), 'en' => array('text' => 'Reference Tables'))),
                array('record' => array('keyword' => 'text-settings', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Text Settings'), 'mn' => array('text' => 'Текстийн тохиргоо'))),
                array('record' => array('keyword' => 'world-countries', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'World Countries'), 'mn' => array('text' => 'Дэлхийн улсууд'))),
                array('record' => array('keyword' => 'get-initial-code', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'үүсгэгч кодыг авах'), 'en' => array('text' => 'get initial code'))),
                array('record' => array('keyword' => 'user-notifications', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'User Notifications'), 'mn' => array('text' => 'Хэрэглэгчийн мэдэгдэл'))),

                array('record' => array('keyword' => 'quick-actions', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Хялбар цэс'), 'en' => array('text' => 'Quick Actions'))),
                array('record' => array('keyword' => 'my-calendar', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Миний календар'), 'en' => array('text' => 'My Calendar'))),
                array('record' => array('keyword' => 'my-profile', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'My Profile'), 'mn' => array('text' => 'Миний профиль'))),
                array('record' => array('keyword' => 'last-login', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Сүүлд нэвтэрсэн'), 'en' => array('text' => 'Last Login'))),

                array('record' => array('keyword' => 'select-a-country', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Улсаа сонгоорой'), 'en' => array('text' => 'Select a Country'))),
                array('record' => array('keyword' => 'sub-pages', 'created_at' => '2019-06-06 18:14:18'), 'content' => array('mn' => array('text' => 'Дэд хуудсууд'), 'en' => array('text' => 'Sub Pages'))),
                array('record' => array('keyword' => 'date-hand', 'created_at' => '2019-06-06 18:14:19'), 'content' => array('en' => array('text' => 'Information date'), 'mn' => array('text' => 'Мэдээллийн огноо'))),
                array('record' => array('keyword' => 'select-image', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Зураг сонгох'), 'en' => array('text' => 'Select an Image'))),
                array('record' => array('keyword' => 'choose-file', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Файлаа сонгоно уу!'), 'en' => array('text' => 'Choose a file!'))),
                array('record' => array('keyword' => 'delete-file-ask', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Are you sure you want to delete this file?'), 'mn' => array('text' => 'Та энэ файлыг устгахдаа итгэлтэй байна уу?'))),
                array('record' => array('keyword' => 'delete-file-success', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Файлыг амжилттай устгалаа'), 'en' => array('text' => 'File deleted successfully'))),
                array('record' => array('keyword' => 'delete-file-error', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Файл устгах явцад алдаа гарлаа'), 'en' => array('text' => 'Error occurred while deleting the file'))),
                array('record' => array('keyword' => 'field-is-required', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'This field is required'), 'mn' => array('text' => 'Талбарын утгыг оруулна уу'))),
                array('record' => array('keyword' => 'confirm-info', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Мэдээллийг баталгаажуулна уу'), 'en' => array('text' => 'Please confirm infomations'))),
                array('record' => array('keyword' => 'account-not-found', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Хэрэглэгчийн мэдээлэл олдсонгүй!'), 'en' => array('text' => 'Account not found!'))),
                array('record' => array('keyword' => 'email-template-not-set', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Email template not found!'), 'mn' => array('text' => 'Цахим захианы загварыг тодорхойлоогүй байна!'))),
                array('record' => array('keyword' => 'emailer-not-set', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Email carrier not found!'), 'mn' => array('text' => 'Шууданчыг тохируулаагүй байна!'))),
                array('record' => array('keyword' => 'email-succesfully-sent', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Email successfully sent to destination'), 'mn' => array('text' => 'Цахим шуудан амжилттай илгээгдлээ'))),
                array('record' => array('keyword' => 'enter-valid-email', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Please enter a valid email address'), 'mn' => array('text' => 'Имэйл хаягыг зөв оруулна уу'))),
                array('record' => array('keyword' => 'no-content-in-time', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Одоогоор харуулах мэдээлэл байхгүй'), 'en' => array('text' => 'There is no content to display'))),
                array('record' => array('keyword' => 'rank-on-site', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'бичлэг сайт дээр харагдах дараалал'), 'en' => array('text' => 'rank of the post'))),
                array('record' => array('keyword' => 'record-successfully-deleted', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Record successfully deleted'), 'mn' => array('text' => 'Бичлэг амжилттай устлаа'))),
                array('record' => array('keyword' => 'created-by', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Үүсгэсэн хэрэглэгч'), 'en' => array('text' => 'Created by'))),
                array('record' => array('keyword' => 'date-created', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Үүссэн огноо'), 'en' => array('text' => 'Date created'))),
                array('record' => array('keyword' => 'more-info', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Дэлгэрэнгүй мэдээлэл'), 'en' => array('text' => 'More Info'))),
                array('record' => array('keyword' => 'invalid-request', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Request is not valid!'), 'mn' => array('text' => 'Хүсэлт буруу байна!'))),
                array('record' => array('keyword' => 'cant-complete-request', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Хүсэлтийг гүйцээх боломжгүй'), 'en' => array('text' => 'Can\'t complete request'))),
                array('record' => array('keyword' => 'show-comment', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Show posted comments'), 'mn' => array('text' => 'Бичигдсэн сэтгэгдлүүдийг харуулна'))),
                array('record' => array('keyword' => 'hide-comment', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Бичигдсэн сэтгэгдлүүдийг харуулахгүй'), 'en' => array('text' => 'Hide posted comments'))),
                array('record' => array('keyword' => 'enable-comment', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Users can comment on this post'), 'mn' => array('text' => 'Зочид сэтгэгдэл үлдээж(бичиж) болно'))),
                array('record' => array('keyword' => 'disable-comment', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Зочид сэтгэгдэл үлдээж(бичиж) болохгүй'), 'en' => array('text' => 'Users cannot comment on this post'))),
                array('record' => array('keyword' => 'settings-general', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'General settings'), 'mn' => array('text' => 'Ерөнхий тохируулгууд'))),
                array('record' => array('keyword' => 'please-enter-title', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Please enter Title!'), 'mn' => array('text' => 'Гарчиг оруулна уу!'))),
                array('record' => array('keyword' => 'please-select-an-action', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Үйлдлээ сонгоно уу'), 'en' => array('text' => 'Please select an action'))),
                array('record' => array('keyword' => 'no-record-selected', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'No record selected'), 'mn' => array('text' => 'Бичлэг сонгогдоогүй байна'))),
                array('record' => array('keyword' => 'select-files', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Файлуудыг сонгох'), 'en' => array('text' => 'Select Files'))),
                array('record' => array('keyword' => 'upload-files', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Файлуудыг илгээх'), 'en' => array('text' => 'Upload Files'))),
                array('record' => array('keyword' => 'pl-upload-failed', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Файлыг серверлүү хуулах явцад алдаа гарлаа'), 'en' => array('text' => 'One of uploads failed. Please retry'))),
                array('record' => array('keyword' => 'image-files', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Image Files'), 'mn' => array('text' => 'Зургийн файлууд'))),
                array('record' => array('keyword' => 'pdf-files', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'PDF Files'), 'mn' => array('text' => 'PDF файлууд'))),
                array('record' => array('keyword' => 'insert-to-content', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Агуулгад нэмэх'), 'en' => array('text' => 'Insert to content'))),
                array('record' => array('keyword' => 'invalid-response', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Invalid response'), 'mn' => array('text' => 'Алдаатай хариу'))),
                array('record' => array('keyword' => 'connection-error', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Холболтын алдаа'), 'en' => array('text' => 'Connection error'))),
                array('record' => array('keyword' => 'invalid-request-data', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Required fields failed to validate, please check your inputs & try again!'), 'mn' => array('text' => 'Мэдээлэл буруу оруулсан байна. Анхааралтай бөглөөд дахин оролдоно уу'))),
                array('record' => array('keyword' => 'generate-reports', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Generate Reports'), 'mn' => array('text' => 'Тайлан гаргах'))),
                array('record' => array('keyword' => 'system-settings', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'System Settings'), 'mn' => array('text' => 'Системийн тохируулга'))),
                array('record' => array('keyword' => 'u-have-some-form-errors', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'You have some form errors. Please check below'), 'mn' => array('text' => 'Та мэдээллийг алдаатай бөглөсөн байна. Доорх талбаруудаа шалгана уу'))),
                array('record' => array('keyword' => 'ur-form-validation-is-successful!', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Your form validation is successful!'), 'mn' => array('text' => 'Та мэдээллийг амжилтай бөглөсөн байна!'))),
                array('record' => array('keyword' => 'active-record-shown', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'active record will shown on site'), 'mn' => array('text' => 'идэвхитэй бичлэг сайт дээр харагдана'))),
                array('record' => array('keyword' => 'delete-record-ask', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Та энэхүү бичлэгийг устгахдаа итгэлтэй байна уу?'), 'en' => array('text' => 'Are you sure to delete this record?'))),
                array('record' => array('keyword' => 'edit-record', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Edit Record'), 'mn' => array('text' => 'Бичлэг засах'))),
                array('record' => array('keyword' => 'add-record', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Add Record'), 'mn' => array('text' => 'Бичлэг нэмэх'))),
                array('record' => array('keyword' => 'empty-code', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Empty Code!'), 'mn' => array('text' => 'Код заавал өгөгдөх ёстой!'))),
                array('record' => array('keyword' => 'empty-id', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Дугаар заавал өгөгдөх ёстой!'), 'en' => array('text' => 'Empty ID!'))),
                array('record' => array('keyword' => 'empty-keyword', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Empty Keyword!'), 'mn' => array('text' => 'Түлхүүр үгийг заавал бичих ёстой!'))),
                array('record' => array('keyword' => 'incomplete-values', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Шаардлагатай талбаруудын утгыг бүрэн оруулна уу!'), 'en' => array('text' => 'Please enter values for required fields!'))),
                array('record' => array('keyword' => 'inline-table', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Inline Table'), 'mn' => array('text' => 'Хүснэгт дотор'))),
                array('record' => array('keyword' => 'invalid-table-name', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Table name is not valid!'), 'mn' => array('text' => 'Хүснэгтийн нэр буруу байна!'))),
                array('record' => array('keyword' => 'invalid-values', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Утга буруу байна!'), 'en' => array('text' => 'Invalid values!'))),
                array('record' => array('keyword' => 'keyword-existing', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Keyword existing in'), 'mn' => array('text' => 'Түлхүүр үг давхцаж байна'))),
                array('record' => array('keyword' => 'record-error-unknown', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Unknown error occurred while processing the request on the server'), 'mn' => array('text' => 'Бичлэгийн явцад алдаа гарлаа'))),
                array('record' => array('keyword' => 'record-insert-success', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Бичлэг амжилттай нэмэгдлээ'), 'en' => array('text' => 'Record successfully added'))),
                array('record' => array('keyword' => 'record-insert-error', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Error occurred while inserting record'), 'mn' => array('text' => 'Бичлэг нэмэх явцад алдаа гарлаа'))),
                array('record' => array('keyword' => 'record-keyword-error', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Түлхүүр үг давхцах боломжгүй'), 'en' => array('text' => 'It looks like [keyword] belongs to an existing record'))),
                array('record' => array('keyword' => 'record-update-success', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Record successfully edited'), 'mn' => array('text' => 'Бичлэг амжилттай засагдлаа'))),
                array('record' => array('keyword' => 'record-update-error', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Error occurred while updating record'), 'mn' => array('text' => 'Бичлэг засах явцад алдаа гарлаа'))),
                array('record' => array('keyword' => 'duplicate-records', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Duplicate records'), 'mn' => array('text' => 'Бичлэгүүд давхцаж байна'))),
                array('record' => array('keyword' => 'record-not-found', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Record not found'), 'mn' => array('text' => 'Бичлэг олдсонгүй'))),
                array('record' => array('keyword' => 'something-went-wrong', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('mn' => array('text' => 'Ямар нэгэн саатал учирлаа'), 'en' => array('text' => 'Looks like something went wrong'))),
                array('record' => array('keyword' => 'error-oops', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Oops..'), 'mn' => array('text' => 'Өө хөөрхий..'))),
                array('record' => array('keyword' => 'error-we-working', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'We\'re working on it'), 'mn' => array('text' => 'Алдааг удахгүй засах болно'))),
                array('record' => array('keyword' => 'view-record', 'created_at' => '2019-07-02 00:41:27'), 'content' => array('mn' => array('text' => 'Бичлэг харах'), 'en' => array('text' => 'View record'))),
                array('record' => array('keyword' => 'updated-by', 'created_at' => '2019-07-02 02:23:57'), 'content' => array('mn' => array('text' => 'Өөрчилсөн хэрэглэгч'), 'en' => array('text' => 'Modified by'))),
                array('record' => array('keyword' => 'date-modified', 'created_at' => '2019-07-02 02:25:20'), 'content' => array('mn' => array('text' => 'Өөрчлөгдсөн огноо'), 'en' => array('text' => 'Date modified'))),
                array('record' => array('keyword' => 'system-no-permission', 'created_at' => '2019-07-02 02:25:20'), 'content' => array('mn' => array('text' => 'Уучлаарай, таньд энэ мэдээлэлд хандах эрх олгогдоогүй байна!'), 'en' => array('text' => 'Access Denied, You don\'t have permission to access on this resource!'))),
                array('record' => array('keyword' => 'website-total-report', 'created_at' => '2019-07-02 02:25:20'), 'content' => array('mn' => array('text' => 'Веб нийт хандалтын тайлан'), 'en' => array('text' => 'Website total access report'))),
                array('record' => array('keyword' => 'website-mounthly-report', 'created_at' => '2020-02-08 18:28:37'), 'content' => array('mn' => array('text' => 'Веб хандалтын сарын тайлан'), 'en' => array('text' => 'Website mounthly report'))),
                array('record' => array('keyword' => 'google-analytics', 'created_at' => '2020-02-08 18:33:35'), 'content' => array('mn' => array('text' => 'Гүүгл аналитик'), 'en' => array('text' => 'Google Analytics'))),
                array('record' => array('keyword' => 'copy-text-from', 'created_at' => '2020-02-08 22:02:21'), 'content' => array('mn' => array('text' => 'Текст хуулбарлах хэл'), 'en' => array('text' => 'Copy texts from'))),
                array('record' => array('keyword' => 'enter-language-details', 'created_at' => '2019-06-06 18:14:19'), 'content' => array('mn' => array('text' => 'Хэлний мэдээллийг оруулна уу'), 'en' => array('text' => 'Provide language details'))),
                array('record' => array('keyword' => 'lang-code-existing', 'created_at' => '2019-06-06 18:14:19'), 'content' => array('mn' => array('text' => 'Хэлний кодыг системд ашиглаж байгаа тул өөр код сонгоно уу!'), 'en' => array('text' => 'Хэлний кодыг системд ашиглаж байгаа тул өөр код сонгоно уу!'))),
                array('record' => array('keyword' => 'lang-existing', 'created_at' => '2019-06-06 18:14:19'), 'content' => array('mn' => array('text' => 'Системд хэлийг ашиглаж байгаа тул өөр хэл сонгоно уу!'), 'en' => array('text' => 'Системд хэлийг ашиглаж байгаа тул өөр хэл сонгоно уу!'))),
                array('record' => array('keyword' => 'lang-name-existing', 'created_at' => '2019-06-06 18:14:19'), 'content' => array('mn' => array('text' => 'Системд хэлний нэрийг ашиглаж байгаа тул өөр нэр ашиглана уу!'), 'en' => array('text' => 'Системд хэлний нэрийг ашиглаж байгаа тул өөр нэр ашиглана уу!'))),
                array('record' => array('keyword' => 'language-added', 'created_at' => '2019-06-06 18:14:19'), 'content' => array('mn' => array('text' => 'Системд шинэ хэл нэмлээ'), 'en' => array('text' => 'Системд шинэ хэл нэмлээ'))),
                array('record' => array('keyword' => 'select-text-settings', 'created_at' => '2019-06-06 18:14:19'), 'content' => array('mn' => array('text' => 'Текстийн тохиргоог сонгоно уу'), 'en' => array('text' => 'Select text settings'))),
                array('record' => array('keyword' => 'texted-tables:', 'created_at' => '2019-06-06 18:14:19'), 'content' => array('mn' => array('text' => 'Текстийг хуулсан хүснэгтүүд:'), 'en' => array('text' => 'Tables of text copied:'))),
                array('record' => array('keyword' => 'dont-have-account-yet', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Don\'t have an account yet?'), 'mn' => array('text' => 'Хэрэглэгч болж амжаагүй байна уу?'))),
                array('record' => array('keyword' => 'username-or-email', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Username or Email'), 'mn' => array('text' => 'Нэр эсвэл имейл'))),
                array('record' => array('keyword' => 'enter-account-details', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Enter your account details below:'), 'mn' => array('text' => 'Нэвтрэх эрхийн мэдээлэл бөглөнө үү:'))),
                array('record' => array('keyword' => 'enter-email-below', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Enter your e-mail address!'), 'mn' => array('text' => 'Бүртгэлтэй имэйл хаягаа доор бичнэ үү!'))),
                array('record' => array('keyword' => 'enter-personal-details', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('mn' => array('text' => 'Та доор хэсэгт хувийн мэдээллээ оруулна уу!'), 'en' => array('text' => 'Enter your personal details below!'))),
                array('record' => array('keyword' => 'enter-username', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Enter your username'), 'mn' => array('text' => 'Хэрэглэгчийн нэрээ оруулна уу'))),
                array('record' => array('keyword' => 'enter-password', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Enter your password'), 'mn' => array('text' => 'Нууц үгээ оруулна уу'))),
                array('record' => array('keyword' => 'enter-username-password', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Enter your username and password'), 'mn' => array('text' => 'Хэрэглэгчийн нэр ба нууц үгээ оруулна уу'))),
                array('record' => array('keyword' => 'error-account-inactive', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('mn' => array('text' => 'Нэвтрэх эрх идэвхигүй байна'), 'en' => array('text' => 'User is not active'))),
                array('record' => array('keyword' => 'error-incorrect-credentials', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Invalid username or password'), 'mn' => array('text' => 'Нэвтрэх нэр эсвэл нууц үг буруу байна'))),
                array('record' => array('keyword' => 'error-password-empty', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Please enter password'), 'mn' => array('text' => 'Нууц үг талбарыг оруулна уу'))),
                array('record' => array('keyword' => 'error-username-empty', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Please enter username'), 'mn' => array('text' => 'Нэвтрэх нэр талбарыг оруулна уу'))),
                array('record' => array('keyword' => 'enter-email-empty', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Please enter email address'), 'mn' => array('text' => 'Имейл хаягыг оруулна уу'))),
                array('record' => array('keyword' => 'forgot-password', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Forgot password?'), 'mn' => array('text' => 'Нууц үгээ мартсан уу?'))),
                array('record' => array('keyword' => 'or-login-with', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Or login with'), 'mn' => array('text' => 'Эсвэл энүүгээр'))),
                array('record' => array('keyword' => 'please-login', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Sign In To Dashboard'), 'mn' => array('text' => 'Хэрэглэгчийн эрхээр нэвтэрнэ'))),
                array('record' => array('keyword' => 'remember-me', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Remember me'), 'mn' => array('text' => 'Намайг сана!'))),
                array('record' => array('keyword' => 'reset-email-sent', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'An reset e-mail has been sent.<br />Please check your email for further instructions!'), 'mn' => array('text' => 'Нууц үгийг шинэчлэх зааврыг амжилттай илгээлээ.<br />Та заасан имейл хаягаа шалгаж зааврын дагуу нууц үгээ шинэчлэнэ үү!'))),
                array('record' => array('keyword' => 'forgotten-password-reset', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('mn' => array('text' => 'Нууц үг дахин тааруулах'), 'en' => array('text' => 'Forgotten password reset'))),
                array('record' => array('keyword' => 'set-new-password', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Set new password'), 'mn' => array('text' => 'Шинээр нууц үг тааруулах'))),
                array('record' => array('keyword' => 'retype-password', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Re-type Password'), 'mn' => array('text' => 'Нууц үгээ дахин бичнэ'))),
                array('record' => array('keyword' => 'set-new-password-success', 'created_at' => '2018-11-12 00:21:45'), 'content' => array('en' => array('text' => 'Your password has been changed successfully! Thank you'), 'mn' => array('text' => 'Нууц үгийг шинээр тохирууллаа. Шинэ нууц үгээ ашиглана уу'))),
                array('record' => array('keyword' => 'fill-new-password', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Please fill a new password!'), 'mn' => array('text' => 'Шинэ нууц үгийг оруулна уу!'))),
                array('record' => array('keyword' => 'password-must-confirm', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Please re-enter the password'), 'mn' => array('text' => 'Нууц үгийг давтан бичих хэрэгтэй'))),
                array('record' => array('keyword' => 'password-must-match', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Password entries must match'), 'mn' => array('text' => 'Нууц үгийг давтан бичихдээ зөв оруулах хэрэгтэй'))),
                array('record' => array('keyword' => 'to-complete-registration-check-email', 'created_at' => '2019-06-06 18:14:22'), 'content' => array('en' => array('text' => 'Thank you. To complete your registration please check your email'), 'mn' => array('text' => 'Танд баярлалаа. Бүртгэлээ баталгаажуулахын тулд заасан имейлээ шалгана уу'))),
                array('record' => array('keyword' => 'active-account-can-login', 'created_at' => '2018-12-03 17:12:31'), 'content' => array('en' => array('text' => 'only active users can login'), 'mn' => array('text' => 'зөвхөн идэвхитэй хэрэглэгч системд нэвтэрч чадна'))),
                array('record' => array('keyword' => 'create-new-account', 'created_at' => '2018-12-03 17:12:31'), 'content' => array('en' => array('text' => 'Create new account'), 'mn' => array('text' => 'Хэрэглэгч шинээр үүсгэх'))),
                array('record' => array('keyword' => 'change-avatar', 'created_at' => '2018-12-03 17:12:31'), 'content' => array('en' => array('text' => 'Change Avatar'), 'mn' => array('text' => 'Хөрөг солих'))),
                array('record' => array('keyword' => 'change-password', 'created_at' => '2018-12-03 17:12:31'), 'content' => array('en' => array('text' => 'Change Password'), 'mn' => array('text' => 'Нууц үг'))),
                array('record' => array('keyword' => 'edit-account', 'created_at' => '2018-12-03 17:12:31'), 'content' => array('en' => array('text' => 'Edit account information'), 'mn' => array('text' => 'Хэрэглэгчийн мэдээлэл өөрчлөх'))),
                array('record' => array('keyword' => 'new-account', 'created_at' => '2018-12-03 17:12:31'), 'content' => array('mn' => array('text' => 'Шинэ хэрэглэгч'), 'en' => array('text' => 'New Account'))),
                array('record' => array('keyword' => 'personal-info', 'created_at' => '2018-12-03 17:12:31'), 'content' => array('en' => array('text' => 'Personal Info'), 'mn' => array('text' => 'Хувийн мэдээлэл'))),
                array('record' => array('keyword' => 'new-password', 'created_at' => '2018-12-03 17:12:31'), 'content' => array('mn' => array('text' => 'Шинэ нууц үг'), 'en' => array('text' => 'New Password'))),
                array('record' => array('keyword' => 'account-role', 'created_at' => '2018-12-03 17:12:31'), 'content' => array('mn' => array('text' => 'Хэрэглэгчийн дүр'), 'en' => array('text' => 'Account Role'))),
                array('record' => array('keyword' => 'retype-new-password', 'created_at' => '2018-12-03 17:12:31'), 'content' => array('en' => array('text' => 'Re-type New Password'), 'mn' => array('text' => 'Шинэ нууц үгийг давтах'))),
                array('record' => array('keyword' => 'account-email-exists', 'created_at' => '2018-12-03 17:12:31'), 'content' => array('en' => array('text' => 'It looks like email address belongs to an existing account'), 'mn' => array('text' => 'Заасан имэйл хаяг өөр хэрэглэгч дээр бүртгэгдсэн байна'))),
                array('record' => array('keyword' => 'account-exists', 'created_at' => '2018-12-03 17:12:31'), 'content' => array('en' => array('text' => 'It looks like information belongs to an existing account'), 'mn' => array('text' => 'Заасан мэдээлэл бүхий хэрэглэгч аль хэдийн бүртгэгдсэн байна'))),
                array('record' => array('keyword' => 'password-reset-request', 'created_at' => '2019-06-16 23:02:46'), 'content' => array('mn' => array('text' => 'Нууц үгээ шинэчлэх хүсэлт'), 'en' => array('text' => 'Password reset request'))),
                array('record' => array('keyword' => 'request-new-account', 'created_at' => '2019-06-17 18:38:30'), 'content' => array('mn' => array('text' => 'Хэрэглэгчээр бүртгүүлэх хүсэлт'), 'en' => array('text' => 'Request a new account')))
            ));
        } catch (Exception $ex) {
            // do nothing
        }
    }
}
