<?php

namespace Dashboard\User;

use codesaur\Router\Router;

use Dashboard\CsrfMiddleware;

/**
 * Хэрэглэгчийн модулийн маршрут (route)-уудыг бүртгэгч router класс.
 *
 * Энэ router нь UsersController-ийн бүх CRUD болон RBAC холбоотой үйлдлүүдийг
 * dashboard хэсэгт зориулан HTTP замтай холбож өгөх үүрэгтэй.
 *
 *  Хэрэглэгчийн жагсаалт (index, list)
 *  Шинэ хэрэглэгч үүсгэх / мэдээлэл засварлах / харах
 *  Идэвхгүй болгох
 *  Байгууллага холбох
 *  RBAC дүр холбох
 *  Нууц үг солих
 *  Forgot/Signup хүсэлтүүдийн modal жагсаалт
 *  Signup хүсэлт approve/deactivate
 *
 *  Маршрут бүр нь:
 *      - URL хаяг
 *      - HTTP method (GET, POST, PUT, DELETE)
 *      - Ямар Controller::method руу очих
 *      - name() -> system дотор замыг нэрээр дуудаж ашиглах
 *
 * @package Dashboard\User
 */
class UsersRouter extends Router
{
    /**
     * Хэрэглэгчийн модулийн бүх маршрутуудыг бүртгэх.
     */
    public function __construct()
    {
        /**
         * ----------------------------------------------------------
         * DASHBOARD - Users main list
         * ----------------------------------------------------------
         */

        // Хэрэглэгчийн dashboard жагсаалт харуулах
        $this->GET('/users', [UsersController::class, 'index'])->name('users');

        // Хэрэглэгчдийн жагсаалтыг AJAX-аар авах
        $this->GET('/users/list', [UsersController::class, 'list'])->name('users-list');

        /**
         * ----------------------------------------------------------
         * CREATE / UPDATE / VIEW USER
         * ----------------------------------------------------------
         */
        // Шинэ хэрэглэгч үүсгэх (form үзүүлэх + submit)
        $this->GET_POST('/users/insert', [UsersController::class, 'insert'])->name('user-insert')->middleware([CsrfMiddleware::class]);
        
        // Хэрэглэгчийн мэдээлэл засварлах (form үзүүлэх + update хийх PUT)
        $this->GET_PUT('/users/update/{uint:id}', [UsersController::class, 'update'])->name('user-update')->middleware([CsrfMiddleware::class]);
        
        // Хэрэглэгчийн дэлгэрэнгүй мэдээлэл харах
        $this->GET('/users/view/{uint:id}', [UsersController::class, 'view'])->name('user-view');

        /**
         * ----------------------------------------------------------
         * DELETE / DEACTIVATE
         * ----------------------------------------------------------
         */
        // Хэрэглэгчийг идэвхгүй болгох
        $this->DELETE('/users/deactivate', [UsersController::class, 'deactivate'])->name('user-deactivate')->middleware([CsrfMiddleware::class]);

        // Идэвхгүй хэрэглэгчийг бүрэн устгах
        $this->DELETE('/users/delete', [UsersController::class, 'delete'])->name('user-delete')->middleware([CsrfMiddleware::class]);

        /**
         * ----------------------------------------------------------
         * ORGANIZATION SET
         * ----------------------------------------------------------
         */

        // Хэрэглэгчийг байгууллага дээр холбох / устгах
        $this->GET_POST('/users/set/organization/{uint:id}', [UsersController::class, 'setOrganization'])->name('user-set-organization')->middleware([CsrfMiddleware::class]);

        /**
         * ----------------------------------------------------------
         * ROLE (RBAC) SET
         * ----------------------------------------------------------
         */
        // Хэрэглэгчийн RBAC дүр тохируулах
        $this->GET_POST('/users/set/role/{uint:id}', [UsersController::class, 'setRole'])->name('user-set-role')->middleware([CsrfMiddleware::class]);

        /**
         * ----------------------------------------------------------
         * PASSWORD RESET
         * ----------------------------------------------------------
         */
        // Хэрэглэгчийн нууц үг солих (өөрийнхөө эсвэл system_coder -> бусдын)
        $this->GET_POST('/users/set/password/{uint:id}', [UsersController::class, 'setPassword'])->name('user-set-password')->middleware([CsrfMiddleware::class]);

        /**
         * ----------------------------------------------------------
         * SIGNUP / FORGOT REQUESTS MODALS
         * ----------------------------------------------------------
         */
        // Signup / Forgot хүсэлтийн modal жагсаалт (зөвхөн view)
        $this->GET('/users/requests/{table}/modal', [UsersController::class, 'requestsModal'])->name('user-requests-modal');

        /**
         * ----------------------------------------------------------
         * SIGNUP APPROVAL / REJECTION
         * ----------------------------------------------------------
         */
        // Signup хүсэлтийг батлах -> хэрэглэгч болгож insert хийх
        $this->POST('/users/signup/approve', [UsersController::class, 'signupApprove'])->name('user-signup-approve')->middleware([CsrfMiddleware::class]);

        // Signup хүсэлтийг татгалзах (status -> rejected)
        $this->PUT('/users/signup/reject', [UsersController::class, 'signupReject'])->name('user-signup-reject')->middleware([CsrfMiddleware::class]);

        // Татгалзсан signup хүсэлтийг бүрэн устгах (Trash руу)
        $this->DELETE('/users/signup/delete', [UsersController::class, 'signupDelete'])->name('user-signup-delete')->middleware([CsrfMiddleware::class]);
    }
}
