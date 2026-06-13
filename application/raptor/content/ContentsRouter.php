<?php

namespace Raptor\Content;

use codesaur\Router\Router;

use Raptor\CsrfMiddleware;

/**
 * Class ContentsRouter
 *
 * Контент модулийн бүх маршрутыг (files, news, pages, references, settings, messages)
 * нэг дор бүртгэн удирддаг төв Router класс.
 *
 * Энэ класс нь Raptor-ийн Dashboard хэсэгт байрлах:
 *  - Файлын менежмент
 *  - Мэдээлэл (News)
 *  - Хуудас (Pages)
 *  - Лавлагаа (References)
 *  - Системийн тохиргоо (Settings)
 * зэрэг модулиудын API болон Dashboard UI-д зориулсан маршрутуудыг бүртгэнэ.
 *
 * @package Raptor\Content
 */
class ContentsRouter extends Router
{
    /**
     * ContentsRouter constructor.
     *
     * Контент модулийн бүх дотоод маршрутыг энд нэг мөрөнд бүртгэнэ.
     * Маршрут бүр нь RESTful зарчмыг дагаж:
     *  - GET     -> мэдээлэл авах
     *  - POST    -> шинэ мэдээлэл нэмэх / файл илгээх
     *  - PUT     -> бүтнээр засварлах
     *  - PATCH   -> хэсэгчлэн шинэчлэх (status, toggle, нэг талбар)
     *  - DELETE  -> устгах
     *  - GET_POST, GET_PUT -> формтой хуудсууд
     *
     * Энэ router нь Raptor-ийн Contents удирлага интерфэйсийг бүрдүүлдэг үндсэн бүртгэгч юм.
     */
    public function __construct()
    {
        /* ------------------------------
         * FILES - Файлын менежмент
         * ------------------------------ */

        // Файлуудын үндсэн хуудас
        $this->GET('/files', [FilesController::class, 'index'])->name('files');

        // Файлын модуль/төрөл тус бүрийн жагсаалт JSON
        $this->GET('/files/list/{table}', [FilesController::class, 'list'])->name('files-list');

        // Файл upload хийх
        $this->POST('/files/upload', [FilesController::class, 'upload'])->name('files-upload')->middleware([CsrfMiddleware::class]);

        // Файл upload хийгээд мэдээллийн сан хүснэгтэд бүртгэх
        $this->POST('/files/post/{table}', [FilesController::class, 'post'])->name('files-post')->middleware([CsrfMiddleware::class]);

        // Файл сонгох modal UI
        $this->GET('/files/modal/{table}', [FilesController::class, 'modal'])->name('files-modal');

        // Файлын мэдээлэл шинэчлэх (partial update)
        $this->PATCH('/files/{table}/{uint:id}', [FilesController::class, 'update'])->name('files-update')->middleware([CsrfMiddleware::class]);

        // Файлыг устгах
        $this->DELETE('/files/{table}/delete', [FilesController::class, 'delete'])->name('files-delete')->middleware([CsrfMiddleware::class]);

        // Protected файл унших (зөвхөн нэвтэрсэн хэрэглэгчдэд, PUBLIC web дээр харагдахгүй гэсэн үг)
        $this->GET('/protected/file', [ProtectedFilesController::class, 'read'])->name('protected-file-read');
        
        
        /* ------------------------------
         * NEWS - Мэдээлэл
         * ------------------------------ */

        // Мэдээний жагсаалтын хүснэгт
        $this->GET('/news', [NewsController::class, 'index'])->name('news');

        // Мэдээний JSON list
        $this->GET('/news/list', [NewsController::class, 'list'])->name('news-list');

        // Мэдээ нэмэх (GET form + POST submit)
        $this->GET_POST('/news/insert', [NewsController::class, 'insert'])->name('news-insert')->middleware([CsrfMiddleware::class]);

        // Мэдээг засварлах (GET form + PUT update)
        $this->GET_PUT('/news/{uint:id}', [NewsController::class, 'update'])->name('news-update')->middleware([CsrfMiddleware::class]);

        // Мэдээг харах UI
        $this->GET('/news/view/{uint:id}', [NewsController::class, 'view'])->name('news-view');

        /* ------------------------------
         * COMMENTS - Сэтгэгдлүүд (бүх мэдээний)
         * ------------------------------ */

        // Сэтгэгдлүүдийн жагсаалт
        $this->GET('/news/comments', [CommentsController::class, 'index'])->name('comments');

        // Сэтгэгдлүүдийн JSON list
        $this->GET('/news/comments/list', [CommentsController::class, 'list'])->name('comments-list');

        // Сэтгэгдэл дэлгэрэнгүй - news ID-аар (?comment_id= query param-аар focus)
        $this->GET('/news/comments/{uint:id}', [CommentsController::class, 'view'])->name('comments-view');

        // Мэдээнд админ сэтгэгдэл бичих
        $this->POST('/news/{uint:id}/comment', [CommentsController::class, 'comment'])->name('news-comment')->middleware([CsrfMiddleware::class]);

        // Мэдээний сэтгэгдэлд хариулт бичих
        $this->POST('/news/comment/{uint:id}/reply', [CommentsController::class, 'reply'])->name('news-comment-reply')->middleware([CsrfMiddleware::class]);

        // Сэтгэгдлийг устгах
        $this->DELETE('/news/comments/delete', [CommentsController::class, 'delete'])->name('comments-delete')->middleware([CsrfMiddleware::class]);

        // Мэдээг устгах
        $this->DELETE('/news/delete', [NewsController::class, 'delete'])->name('news-delete')->middleware([CsrfMiddleware::class]);

        // Мэдээний жишиг датаг цэвэрлэж production эхлүүлэх
        $this->DELETE('/news/reset', [NewsController::class, 'reset'])->name('news-sample-reset')->middleware([CsrfMiddleware::class]);


        /* ------------------------------
         * PAGES - Хуудас
         * ------------------------------ */

        // Хуудасны навигацийн мод бүтэц (үндсэн хуудас)
        $this->GET('/pages', [PagesController::class, 'nav'])->name('pages');

        // Хуудасны жагсаалтын хүснэгт
        $this->GET('/pages/table', [PagesController::class, 'index'])->name('pages-table');

        // Хуудасны жагсаалт JSON
        $this->GET('/pages/list', [PagesController::class, 'list'])->name('pages-list');

        // Хуудас шинээр нэмэх
        $this->GET_POST('/pages/insert', [PagesController::class, 'insert'])->name('page-insert')->middleware([CsrfMiddleware::class]);

        // Хуудас засварлах
        $this->GET_PUT('/pages/{uint:id}', [PagesController::class, 'update'])->name('page-update')->middleware([CsrfMiddleware::class]);

        // Хуудас харах
        $this->GET('/pages/view/{uint:id}', [PagesController::class, 'view'])->name('page-view');

        // Хуудас устгах
        $this->DELETE('/pages/delete', [PagesController::class, 'delete'])->name('page-delete')->middleware([CsrfMiddleware::class]);

        // Хуудасны жишиг датаг цэвэрлэж production эхлүүлэх
        $this->DELETE('/pages/reset', [PagesController::class, 'reset'])->name('pages-sample-reset')->middleware([CsrfMiddleware::class]);


        /* ------------------------------
         * REFERENCES - Лавлагааны хүснэгтүүд
         * ------------------------------ */

        // Лавлагааны үндсэн хуудас
        $this->GET('/references', [ReferencesController::class, 'index'])->name('references');

        // Тухайн лавлагаа хүснэгтэд record нэмэх
        $this->GET_POST('/references/{table}', [ReferencesController::class, 'insert'])->name('reference-insert')->middleware([CsrfMiddleware::class]);

        // Лавлагааны хүснэгтийн мөр засах
        $this->GET_PUT('/references/{table}/{uint:id}', [ReferencesController::class, 'update'])->name('reference-update')->middleware([CsrfMiddleware::class]);

        // Лавлагааны хүснэгтийн мөр харах
        $this->GET('/references/view/{table}/{uint:id}', [ReferencesController::class, 'view'])->name('reference-view');

        // Лавлагааг устгах
        $this->DELETE('/references/delete', [ReferencesController::class, 'delete'])->name('reference-delete')->middleware([CsrfMiddleware::class]);


        /* ------------------------------
         * SETTINGS - Тохируулга
         * ------------------------------ */

        // Системийн тохиргоо харах/засах хуудас
        $this->GET('/settings', [SettingsController::class, 'index'])->name('settings');

        // Тохируулга шинэчлэх
        $this->POST('/settings', [SettingsController::class, 'post'])->middleware([CsrfMiddleware::class]);

        // Тохиргооны файл upload хийх
        $this->POST('/settings/files', [SettingsController::class, 'files'])->name('settings-files')->middleware([CsrfMiddleware::class]);

        // .env утга шинэчлэх (email notify toggle, хаяг)
        $this->PATCH('/settings/env', [SettingsController::class, 'updateEnv'])->name('settings-env')->middleware([CsrfMiddleware::class]);


        /* ------------------------------
         * MESSAGES - Холбоо барих мессежүүд
         * ------------------------------ */

        // Мессежүүдийн жагсаалтын хуудас
        $this->GET('/messages', [MessagesController::class, 'index'])->name('messages');

        // Мессежүүдийн JSON list
        $this->GET('/messages/list', [MessagesController::class, 'list'])->name('messages-list');

        // Мессежийг харах modal
        $this->GET('/messages/view/{uint:id}', [MessagesController::class, 'view'])->name('messages-view');

        // Мессежийг хариулсан гэж тэмдэглэх (partial update)
        $this->PATCH('/messages/replied/{uint:id}', [MessagesController::class, 'markReplied'])->name('messages-replied')->middleware([CsrfMiddleware::class]);

        // Мессежийг устгах
        $this->DELETE('/messages/delete', [MessagesController::class, 'delete'])->name('messages-delete')->middleware([CsrfMiddleware::class]);


        /**
         * MOEDIT AI API
         *
         * moedit editor-ийн AI товчинд зориулсан API.
         */
        $this->POST('/content/moedit/ai', [AIHelper::class, 'moeditAI'])->name('moedit-ai')->middleware([CsrfMiddleware::class]);
    }
}
