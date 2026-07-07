<?php

namespace Raptor\Organization;

use codesaur\Router\Router;

use Raptor\CsrfMiddleware;

/**
 * Class OrganizationRouter
 *
 * Байгууллагын модулийн бүх маршрут (route)-ийг бүртгэдэг Router класс.
 * Энэ класс нь Raptor Framework-ийн Router-ийг өргөтгөж,
 * байгууллагатай холбоотой CRUD болон хэрэглэгчийн жагсаалт харах
 * зэрэг үйлдлүүдийн URL маршрут, HTTP арга, контроллерийн холбоосыг тодорхойлно.
 *
 * Хариуцдаг үндсэн чиг үүргүүд:
 *  - Байгууллагын жагсаалт үзүүлэх
 *  - Байгууллага шинээр бүртгэх
 *  - Байгууллагын мэдээлэл засах
 *  - Байгууллагын дэлгэрэнгүй харах
 *  - Байгууллагыг идэвхгүй болгох (deactivate)
 *  - Хэрэглэгчийн бүртгэлтэй байгууллагын жагсаалт дуудах
 *
 * @package Raptor\Organization
 */
class OrganizationRouter extends Router
{
    /**
     * OrganizationRouter constructor.
     *
     * Энд байгууллагатай холбоотой бүх маршрут бүртгэгдэнэ.
     * Raptor Router-ийн боломжийг ашиглан GET, POST, PUT, DELETE зэрэг
     * HTTP аргуудыг нэгтгэн маршрутын нэртэй нь хамт тодорхойлж байна.
     */
    public function __construct()
    {
        /**
         * --------------------------------------------------------------
         * Байгууллагын үндсэн удирдлага хуудас (Dashboard)
         * --------------------------------------------------------------
         * URL:      /dashboard/organizations
         * Method:   GET
         * Action:   OrganizationController::index()
         * Name:     organizations
         */
        $this->GET('/organizations', [OrganizationController::class, 'index'])->name('organizations');

        /**
         * --------------------------------------------------------------
         * Байгууллагын жагсаалтын өгөгдөл (AJAX list API)
         * --------------------------------------------------------------
         * URL:      /dashboard/organizations/list
         * Method:   GET
         * Action:   OrganizationController::list()
         * Name:     organizations-list
         */
        $this->GET('/organizations/list', [OrganizationController::class, 'list'])->name('organizations-list');

        /**
         * --------------------------------------------------------------
         * Байгууллага шинээр бүртгэх (insert)
         * --------------------------------------------------------------
         * URL:      /dashboard/organizations/insert
         * Methods:  GET, POST
         * Action:   OrganizationController::insert()
         * Name:     organization-insert
         */
        $this->GET_POST('/organizations/insert', [OrganizationController::class, 'insert'])->name('organization-insert')->middleware([CsrfMiddleware::class]);

        /**
         * --------------------------------------------------------------
         * Байгууллагын мэдээлэл шинэчлэх (update)
         * --------------------------------------------------------------
         * URL:      /dashboard/organizations/update/{id}
         * Methods:  GET, PUT
         * Action:   OrganizationController::update()
         * Param:    id - uint төрөлтэй
         * Name:     organization-update
         */
        $this->GET_PUT('/organizations/update/{uint:id}', [OrganizationController::class, 'update'])->name('organization-update')->middleware([CsrfMiddleware::class]);

        /**
         * --------------------------------------------------------------
         * Байгууллагын дэлгэрэнгүй харах (view)
         * --------------------------------------------------------------
         * URL:      /dashboard/organizations/view/{id}
         * Method:   GET
         * Action:   OrganizationController::view()
         * Name:     organization-view
         */
        $this->GET('/organizations/view/{uint:id}', [OrganizationController::class, 'view'])->name('organization-view');

        /**
         * --------------------------------------------------------------
         * Байгууллагыг идэвхгүй болгох (SOFT DELETE)
         * --------------------------------------------------------------
         * URL:      /dashboard/organizations/deactivate
         * Method:   DELETE
         * Action:   OrganizationController::deactivate()
         * Name:     organization-deactivate
         */
        $this->DELETE('/organizations/deactivate', [OrganizationController::class, 'deactivate'])->name('organization-deactivate')->middleware([CsrfMiddleware::class]);

        /**
         * --------------------------------------------------------------
         * Идэвхгүй байгууллагыг бүрэн устгах (HARD DELETE)
         * --------------------------------------------------------------
         */
        $this->DELETE('/organizations/delete', [OrganizationController::class, 'delete'])->name('organization-delete')->middleware([CsrfMiddleware::class]);
    }
}
