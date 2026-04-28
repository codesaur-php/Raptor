<?php

namespace Raptor\Content;

use codesaur\Router\Router;

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
        $this->GET('/dashboard/files', [FilesController::class, 'index'])->name('files');

        // Файлын модуль/төрөл тус бүрийн жагсаалт JSON
        $this->GET('/dashboard/files/list/{table}', [FilesController::class, 'list'])->name('files-list');

        // Файл upload хийх
        $this->POST('/dashboard/files/upload', [FilesController::class, 'upload'])->name('files-upload');

        // Файл upload хийгээд мэдээллийн сан хүснэгтэд бүртгэх
        $this->POST('/dashboard/files/post/{table}', [FilesController::class, 'post'])->name('files-post');

        // Файл сонгох modal UI
        $this->GET('/dashboard/files/modal/{table}', [FilesController::class, 'modal'])->name('files-modal');

        // Файлын мэдээлэл шинэчлэх (partial update)
        $this->PATCH('/dashboard/files/{table}/{uint:id}', [FilesController::class, 'update'])->name('files-update');

        // Файлыг устгах
        $this->DELETE('/dashboard/files/{table}/delete', [FilesController::class, 'delete'])->name('files-delete');

        // Private файл унших (зөвхөн нэвтэрсэн хэрэглэгчдэд, PUBLIC web дээр харагдахгүй гэсэн үг)
        $this->GET('/dashboard/private/file', [PrivateFilesController::class, 'read']);
        
        
        /* ------------------------------
         * NEWS - Мэдээлэл
         * ------------------------------ */

        // Мэдээний жагсаалтын хүснэгт
        $this->GET('/dashboard/news', [NewsController::class, 'index'])->name('news');

        // Мэдээний JSON list
        $this->GET('/dashboard/news/list', [NewsController::class, 'list'])->name('news-list');

        // Мэдээ нэмэх (GET form + POST submit)
        $this->GET_POST('/dashboard/news/insert', [NewsController::class, 'insert'])->name('news-insert');

        // Мэдээг засварлах (GET form + PUT update)
        $this->GET_PUT('/dashboard/news/{uint:id}', [NewsController::class, 'update'])->name('news-update');

        // Мэдээг харах UI
        $this->GET('/dashboard/news/view/{uint:id}', [NewsController::class, 'view']);

        /* ------------------------------
         * COMMENTS - Сэтгэгдлүүд (бүх мэдээний)
         * ------------------------------ */

        // Сэтгэгдлүүдийн жагсаалт
        $this->GET('/dashboard/news/comments', [CommentsController::class, 'index'])->name('comments');

        // Сэтгэгдлүүдийн JSON list
        $this->GET('/dashboard/news/comments/list', [CommentsController::class, 'list'])->name('comments-list');

        // Сэтгэгдэл дэлгэрэнгүй - news ID-аар (?comment_id= query param-аар focus)
        $this->GET('/dashboard/news/comments/{uint:id}', [CommentsController::class, 'view'])->name('comments-view');

        // Мэдээнд админ сэтгэгдэл бичих
        $this->POST('/dashboard/news/{uint:id}/comment', [CommentsController::class, 'comment'])->name('news-comment');

        // Мэдээний сэтгэгдэлд хариулт бичих
        $this->POST('/dashboard/news/comment/{uint:id}/reply', [CommentsController::class, 'reply'])->name('news-comment-reply');

        // Сэтгэгдлийг устгах
        $this->DELETE('/dashboard/news/comments/delete', [CommentsController::class, 'delete'])->name('comments-delete');

        // Мэдээг устгах
        $this->DELETE('/dashboard/news/delete', [NewsController::class, 'delete'])->name('news-delete');

        // Мэдээний жишиг датаг цэвэрлэж production эхлүүлэх
        $this->DELETE('/dashboard/news/reset', [NewsController::class, 'reset'])->name('news-sample-reset');


        /* ------------------------------
         * PAGES - Хуудас
         * ------------------------------ */

        // Хуудасны навигацийн мод бүтэц (үндсэн хуудас)
        $this->GET('/dashboard/pages', [PagesController::class, 'nav'])->name('pages');

        // Хуудасны жагсаалтын хүснэгт
        $this->GET('/dashboard/pages/table', [PagesController::class, 'index'])->name('pages-table');

        // Хуудасны жагсаалт JSON
        $this->GET('/dashboard/pages/list', [PagesController::class, 'list'])->name('pages-list');

        // Хуудас шинээр нэмэх
        $this->GET_POST('/dashboard/pages/insert', [PagesController::class, 'insert'])->name('page-insert');

        // Хуудас засварлах
        $this->GET_PUT('/dashboard/pages/{uint:id}', [PagesController::class, 'update'])->name('page-update');

        // Хуудас харах
        $this->GET('/dashboard/pages/view/{uint:id}', [PagesController::class, 'view']);

        // Хуудас устгах
        $this->DELETE('/dashboard/pages/delete', [PagesController::class, 'delete'])->name('page-delete');

        // Хуудасны жишиг датаг цэвэрлэж production эхлүүлэх
        $this->DELETE('/dashboard/pages/reset', [PagesController::class, 'reset'])->name('pages-sample-reset');


        /* ------------------------------
         * REFERENCES - Лавлагааны хүснэгтүүд
         * ------------------------------ */

        // Лавлагааны үндсэн хуудас
        $this->GET('/dashboard/references', [ReferencesController::class, 'index'])->name('references');

        // Тухайн лавлагаа хүснэгтэд record нэмэх
        $this->GET_POST('/dashboard/references/{table}', [ReferencesController::class, 'insert'])->name('reference-insert');

        // Лавлагааны хүснэгтийн мөр засах
        $this->GET_PUT('/dashboard/references/{table}/{uint:id}', [ReferencesController::class, 'update'])->name('reference-update');

        // Лавлагааны хүснэгтийн мөр харах
        $this->GET('/dashboard/references/view/{table}/{uint:id}', [ReferencesController::class, 'view'])->name('reference-view');

        // Лавлагааг устгах
        $this->DELETE('/dashboard/references/delete', [ReferencesController::class, 'delete'])->name('reference-delete');


        /* ------------------------------
         * SETTINGS - Тохируулга
         * ------------------------------ */

        // Системийн тохиргоо харах/засах хуудас
        $this->GET('/dashboard/settings', [SettingsController::class, 'index'])->name('settings');

        // Тохируулга шинэчлэх
        $this->POST('/dashboard/settings', [SettingsController::class, 'post']);

        // Тохиргооны файл upload хийх
        $this->POST('/dashboard/settings/files', [SettingsController::class, 'files'])->name('settings-files');

        // .env утга шинэчлэх (email notify toggle, хаяг)
        $this->PATCH('/dashboard/settings/env', [SettingsController::class, 'updateEnv'])->name('settings-env');


        /* ------------------------------
         * MESSAGES - Холбоо барих мессежүүд
         * ------------------------------ */

        // Мессежүүдийн жагсаалтын хуудас
        $this->GET('/dashboard/messages', [MessagesController::class, 'index'])->name('messages');

        // Мессежүүдийн JSON list
        $this->GET('/dashboard/messages/list', [MessagesController::class, 'list'])->name('messages-list');

        // Мессежийг харах modal
        $this->GET('/dashboard/messages/view/{uint:id}', [MessagesController::class, 'view'])->name('messages-view');

        // Мессежийг хариулсан гэж тэмдэглэх (partial update)
        $this->PATCH('/dashboard/messages/replied/{uint:id}', [MessagesController::class, 'markReplied'])->name('messages-replied');

        // Мессежийг устгах
        $this->DELETE('/dashboard/messages/delete', [MessagesController::class, 'delete'])->name('messages-delete');


        /**
         * MOEDIT AI API
         *
         * moedit editor-ийн AI товчинд зориулсан API.
         */
        $this->POST('/dashboard/content/moedit/ai', [AIHelper::class, 'moeditAI'])->name('moedit-ai');
    }
}
