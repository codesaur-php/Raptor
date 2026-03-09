<?php

namespace Raptor\Content;

use codesaur\Router\Router;

/**
 * Class ContentsRouter
 *
 * Контент модулийн бүх маршрутыг (files, news, pages, references, settings)
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
     *  - PUT     -> засварлах
     *  - DELETE  -> идэвхгүй болгох (soft delete)
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

        // Файлын мэдээлэл шинэчлэх
        $this->PUT('/dashboard/files/{table}/{uint:id}', [FilesController::class, 'update'])->name('files-update');

        // Файлыг идэвхгүй болгох (soft delete)
        $this->DELETE('/dashboard/files/{table}/deactivate', [FilesController::class, 'deactivate'])->name('files-deactivate');

        // Private файл унших (зөвхөн нэвтэрсэн хэрэглэгчдэд, PUBLIC web дээр харагдахгүй гэсэн үг)
        $this->GET('/dashboard/private/file', [PrivateFilesController::class, 'read'])->name('private-files-read');
        
        
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

        // Мэдээ унших (blog хэлбэрээр)
        $this->GET('/dashboard/news/read/{slug}', [NewsController::class, 'read'])->name('news-read');

        // Мэдээг харах UI
        $this->GET('/dashboard/news/view/{uint:id}', [NewsController::class, 'view'])->name('news-view');

        // Мэдээг идэвхгүй болгох SOFT DELETE
        $this->DELETE('/dashboard/news/deactivate', [NewsController::class, 'deactivate'])->name('news-deactivate');

        // Мэдээний жишиг датаг цэвэрлэж production эхлүүлэх
        $this->DELETE('/dashboard/news/reset', [NewsController::class, 'reset'])->name('news-sample-reset');


        /* ------------------------------
         * PAGES - Хуудас
         * ------------------------------ */

        // Хуудасны жагсаалтын хүснэгт
        $this->GET('/dashboard/pages', [PagesController::class, 'index'])->name('pages');

        // Хуудасны навигацийн мод бүтэц
        $this->GET('/dashboard/pages/nav', [PagesController::class, 'nav'])->name('pages-nav');

        // Хуудасны жагсаалт JSON
        $this->GET('/dashboard/pages/list', [PagesController::class, 'list'])->name('pages-list');

        // Хуудас шинээр нэмэх
        $this->GET_POST('/dashboard/pages/insert', [PagesController::class, 'insert'])->name('page-insert');

        // Хуудас засварлах
        $this->GET_PUT('/dashboard/pages/{uint:id}', [PagesController::class, 'update'])->name('page-update');

        // Хуудас унших (blog хэлбэрээр)
        $this->GET('/dashboard/pages/read/{slug}', [PagesController::class, 'read'])->name('page-read');

        // Хуудас харах
        $this->GET('/dashboard/pages/view/{uint:id}', [PagesController::class, 'view'])->name('page-view');

        // Хуудас идэвхгүй болгох SOFT DELETE
        $this->DELETE('/dashboard/pages/deactivate', [PagesController::class, 'deactivate'])->name('page-deactivate');

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

        // Лавлагаанг идэвхгүй болгох SOFT DELETE
        $this->DELETE('/dashboard/references/deactivate', [ReferencesController::class, 'deactivate'])->name('reference-deactivate');


        /* ------------------------------
         * SETTINGS - Тохируулга
         * ------------------------------ */

        // Системийн тохиргоо харах/засах хуудас
        $this->GET('/dashboard/settings', [SettingsController::class, 'index'])->name('settings');

        // Тохируулга шинэчлэх
        $this->POST('/dashboard/settings', [SettingsController::class, 'post']);

        // Тохиргооны файл upload хийх
        $this->POST('/dashboard/settings/files', [SettingsController::class, 'files'])->name('settings-files');
        
        

        /**
         * MOEDIT AI API
         *
         * moedit editor-ийн AI товчинд зориулсан API.
         */
        $this->POST('/dashboard/content/moedit/ai', [AIHelper::class, 'moeditAI'])->name('moedit-ai');
    }
}
