<?php

namespace Raptor\Authentication;

use codesaur\Router\Router;

/**
 * Class LoginRouter
 *
 * Dashboard хэсгийн нэвтрэх үйлдлүүдийн бүх маршрутыг (routes)
 * тодорхойлдог router класс. Энэ нь хэрэглэгчийн нэвтрэх,
 * гарах, нууц үг сэргээх, бүртгүүлэх, хэл солих зэрэг 
 * authentication-тэй холбоотой бүх URL чиглүүлэлтийг aжуулна.
 *
 * Raptor-ийн Router анги нь:
 *   - GET, POST замууд үүсгэх
 *   - Dynamic параметр дэмжих {code}, {uint:id}
 *   - Маршрут бүрт нэр өгөх (name)
 * боломжуудыг олгодог.
 *
 * @package Raptor\Authentication
 */
class LoginRouter extends Router
{
    /**
     * LoginRouter constructor.
     *
     * Энд authentication-тэй холбоотой маршрут бүрийг тодорхойлно.
     * Бүх зам "/dashboard/login..." хэлбэртэй бөгөөд LoginController-ийн
     * харгалзах action-уудтай шууд холбогдоно.
     */
    public function __construct()
    {
        /**
         * ---------------------------------------------------------------
         * 1. Login хуудас (GET)
         * ---------------------------------------------------------------
         * Хэрэглэгч нэвтрэх нүүр хуудас руу орно.
         */
        $this->GET('/login', [LoginController::class, 'index'])->name('login');

        /**
         * ---------------------------------------------------------------
         * 2. Нэвтрэх оролдлого (POST)
         * ---------------------------------------------------------------
         * Хэрэглэгч username/password илгээж нэвтрэхийг оролдоно.
         */
        $this->POST('/login/try', [LoginController::class, 'entry'])->name('entry');

        /**
         * ---------------------------------------------------------------
         * 3. Гарах (GET)
         * ---------------------------------------------------------------
         * Session болон JWT-г цэвэрлээд хэрэглэгчийг гарах.
         */
        $this->GET('/login/logout', [LoginController::class, 'logout'])->name('logout');

        /**
         * ---------------------------------------------------------------
         * 4. Нууц үг сэргээх (POST)
         * ---------------------------------------------------------------
         * Хэрэглэгч email/username оруулж "Forgot password" хүсэлт үүсгэнэ.
         */
        $this->POST('/login/forgot', [LoginController::class, 'forgot'])->name('login-forgot');

        /**
         * ---------------------------------------------------------------
         * 5. Бүртгүүлэх (POST)
         * ---------------------------------------------------------------
         * Шинэ хэрэглэгч нэр, имэйл, нууц үгийн мэдээлэл өгч signup хийх.
         */
        $this->POST('/login/signup', [LoginController::class, 'signup'])->name('signup');

        /**
         * ---------------------------------------------------------------
         * 6. Хэл солих (GET)
         * ---------------------------------------------------------------
         * Системд ажиллах хэлийг солих. Хоёр төлөвт ажиллана:
         *   - Нэвтрээгүй (login хуудас): зөвхөн session-ий хэлийг солино.
         *     LoginController::language() нь auth шаарддаггүй тул
         *     JWTAuthMiddleware-д 'login' segment нь login-redirect-ээс
         *     чөлөөлөгдсөн (anonymous-аар controller руу унадаг).
         *   - Нэвтэрсэн (dashboard): session-ий хэлийг солихоос гадна
         *     хэрэглэгчийн profile-д ('code') хадгалж, өөрчлөлтийг лог-д бичнэ.
         * Dynamic parameter: {code} - хэлний код (жишээ: mn, en)
         * Жишээ: GET /dashboard/login/language/mn
         */
        $this->GET('/login/language/{code}', [LoginController::class, 'language'])->name('language');

        /**
         * ---------------------------------------------------------------
         * 7. Сэргээх линк дээр дараад шинэ нууц үг тохируулах (POST)
         * ---------------------------------------------------------------
         */
        $this->POST('/login/set/password', [LoginController::class, 'setPassword'])->name('login-set-password');

        /**
         * ---------------------------------------------------------------
         * 8. Байгууллага сонгох (GET)
         * ---------------------------------------------------------------
         * Хэрэглэгч хэд хэдэн байгууллагад хамааралтай бол
         * нэвтэрсэн үедээ аль байгууллагаар ажиллахаа сонгох алхам.
         *
         * Dynamic parameter:
         *    {uint:id} -> зөвхөн unsigned integer
         *
         * Жишээ: GET /dashboard/login/organization/12
         */
        $this->GET('/login/organization/{uint:id}', [LoginController::class, 'selectOrganization'])->name('login-select-organization');
    }
}
