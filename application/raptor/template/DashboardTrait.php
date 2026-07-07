<?php

namespace Raptor\Template;

use codesaur\Template\FileTemplate;

use Raptor\Organization\OrganizationModel;
use Raptor\Organization\OrganizationUserModel;
use Raptor\User\UsersModel;

/**
 * DashboardTrait - Dashboard UI руу контент байрлуулах,
 * permission-гүй үед alert/modal үзүүлэх, хэрэглэгчийн
 * sidemenu-г динамикаар үүсгэх зэрэг нийтлэг dashboard
 * функциональ байдлыг Controller-д ашиглуулах зориулалттай trait.
 *
 * Үндсэн үүрэг:
 * ---------------------------------------------------------------
 *  - layout()
 *      -> Layout template-ийн замыг тогтоох (developer нь
 *         Application::overrideDashboardLayout()-оор өөрийн
 *         файлаар сольсон байж болно)
 *
 *  - dashboardTemplate()
 *      -> Dashboard layout (dashboard.html) дотор контент оруулах
 *
 *  - dashboardProhibited()  
 *      -> Хэрэглэгч эрхгүй үед dashboard орчинд permission alert үзүүлэх
 *
 *  - modalProhibited()  
 *      -> Modal орчинд permission alert үзүүлэх
 *
 *  - retrieveUsersDetail()  
 *      -> Audit log, organization mapping зэрэг UI-д хэрэглэгчийн
 *         товч мэдээллийн жагсаалтыг авах
 *
 *  - getUserMenu()  
 *      -> Permission, is_visible, organization alias
 *         зэрэг нөхцөлүүдээр хэрэглэгчийн sidemenu-г бүрдүүлэх
 *
 * Энэ trait нь Raptor\Controller-тэй цуг ажиллаж,
 * Raptor Dashboard UI-ийн суурь rendering pipeline-г бүрдүүлнэ.
 */
trait DashboardTrait
{
    /**
     * Layout template-ийн эцсийн замыг тогтооно.
     *
     * Application::overrideDashboardLayout()-оор бүртгэсэн custom файл
     * байвал түүнийг, үгүй бол raptor/template/ доторх default-ийг буцаана.
     * Map нь Application::handle() дээр 'dashboard_layouts' attribute
     * болж ирдэг.
     *
     * @param string $filename Layout файлын нэр (жишээ: 'dashboard.html')
     * @return string Template файлын бүрэн зам
     *
     * @see \Raptor\Application::overrideDashboardLayout() Map-ийг бүртгэдэг тал
     */
    private function layout(string $filename): string
    {
        return $this->getAttribute('dashboard_layouts', [])[$filename]
            ?? __DIR__ . '/' . $filename;
    }

    /**
     * Dashboard Layout ашиглан контент рендерлэх гол метод.
     *
     * Энэхүү функц нь бүх Dashboard төрлийн хуудасны мастер layout юм.
     * Хэрэглэгчийн харагдах UI бүтэц (sidebar, user info, content area)
     * бүгдийг энд төвлөрүүлж, динамикаар бүрдүүлдэг.
     *
     * Процесс:
     * ---------------------------------------------------------------
     * 1) `dashboard.html` мастер layout-ийг template() ашиглан ачаална.
     *    (Developer нь Application::overrideDashboardLayout()-оор өөрийн
     *    layout файл бүртгэсэн бол layout() түүнийг сонгоно.)
     *
     * 2) Хэрэглэгчийн зөвшөөрөлд (RBAC) тулгуурлан харагдах ёстой
     *    sidemenu-г getUserMenu() функцээр тооцож -> `sidemenu` хувьсагчид онооно.
     *
     * 3) Контент хэсэгт харуулах тухайн хуудасны template-г
     *    template($template, $vars) дуудаж -> `content` болгон оруулна.
     *    (Жич: Контент template нь зөвхөн `<main>` хэсэг дотор байрлана.)
     *
     * 4) Системийн тохируулгууд (`settings` аттрибут) - тухайлбал:
     *    footer мэдээлэл, брэндингийн өгөгдөл, favicon, logo зэрэг
     *    layout түвшинд хэрэгтэй бүх өгөгдлийг `$dashboard->set()` ашиглан нэг нэгээр нь оруулна.
     *
     * Товчхондоо:
     * ---------------------------------------------------------------
     *  > Dashboard layout + Dynamic sidebar + Dynamic content + System settings
     *  -> нэг FileTemplate объект болж буцна.
     *
     * @param string $template  Контент template-ийн файл зам
     * @param array  $vars      Контент template-д дамжуулах хувьсагчид
     *
     * @return FileTemplate     Бүрэн бэлтгэгдсэн Dashboard-ийн view объект
     */
    public function dashboardTemplate(string $template, array $vars = []): FileTemplate
    {
        $dashboard = $this->template($this->layout('dashboard.html'));
        $dashboard->set('sidemenu', $this->getUserMenu());
        $dashboard->set('user_organizations', $this->getUserOrganizations());
        $dashboard->set('content', $this->template($template, $vars));
        foreach ($this->getAttribute('settings', []) as $key => $value) {
            $dashboard->set($key, $value);
        }
        return $dashboard;
    }

    /**
     * Dashboard орчинд permission-гүй үед alert харуулах.
     *
     * - Хариу код тохируулна (403 гэх мэт)
     * - Dashboard layout дотор "no-permission" контент байрлуулна
     *
     * @param string|null    $alert Alert текст
     * @param int|string     $code  HTTP статус код
     *
     * @return FileTemplate
     */
    public function dashboardProhibited(?string $alert = null, int|string $code = 0): FileTemplate
    {
        $this->headerResponseCode($code);

        return $this->dashboardTemplate(
            $this->layout('alert-no-permission.html'),
            ['alert' => $alert ?? $this->text('system-no-permission')]
        );
    }

    /**
     * Modal орчинд permission-гүй үед харуулах template.
     *
     * - Dashboard layout ашиглахгүй
     * - Зөвхөн modal-д тохирох жижиг template буцаана
     *
     * @param string|null $alert
     * @param int|string  $code
     *
     * @return FileTemplate
     */
    public function modalProhibited(?string $alert = null, int|string $code = 0): FileTemplate
    {
        $this->headerResponseCode($code);

        return new FileTemplate(
            $this->layout('modal-no-permission.html'),
            [
                'alert' => $alert ?? $this->text('system-no-permission'),
                'close' => $this->text('close')
            ]
        );
    }

    /**
     * Хэрэглэгчдийн товч мэдээллийн жагсаалт авах (audit trail-д ашиглагдана).
     *
     * Оролт:
     *   - Хэдэн ч user_id дамжуулж болно.
     *   - user_id = null эсвэл ямар ч ID дамжуулаагүй бол:
     *         -> users хүснэгт дэх **бүх хэрэглэгчийн** мэдээллийг авна.
     *
     * Гаралт:
     *   [
     *      4 => "batka - Бат Эрдэнэ (bat@example.com)",
     *      9 => "saraa - Сараа Мөнх (saraa@example.com)",
     *   ]
     *
     * Алдаа гарвал хоосон массив буцаана.
     *
     * @param int|null ...$ids
     * @return array [user_id => label]
     */
    protected function retrieveUsersDetail(?int ...$ids)
    {
        $users = [];
        
        try {
            $had_condition = !empty($ids);
            $table = (new UsersModel($this->pdo))->getName();
            $select_users =
                "SELECT id, username, first_name, last_name, email FROM $table";
            // WHERE нөхцөл боловсруулах
            if ($had_condition) {
                $ids = \array_filter($ids, fn($v) => $v !== null);
                if (empty($ids)) {
                    throw new \InvalidArgumentException(__FUNCTION__ . ': invalid arguments!');
                }
                \array_walk($ids, fn(&$v) => $v = "id=$v");
                $select_users .= ' WHERE ' . \implode(' OR ', $ids);
            }

            $pdo_stmt = $this->prepare($select_users);
            if ($pdo_stmt->execute()) {
                while ($row = $pdo_stmt->fetch()) {
                    $users[$row['id']] =
                        "{$row['username']} - {$row['first_name']} {$row['last_name']} ({$row['email']})";
                }
            }
        } catch (\Throwable) {
            // Хүсвэл алдааг development үед логлох боломжтой
        }
        
        return $users;
    }

    /**
     * Хэрэглэгчийн sidemenu-г динамикаар үүсгэх.
     *
     * Шүүлт хийх нөхцөлүүд:
     * ---------------------------------------------------------------
     *  - p.is_visible = 1 -> харагдах боломжтой
     *  - Organization alias тохирох эсэх:
     *        menu.alias != current_user.organization.alias -> skip
     *  - Permission заасан бол:
     *        !isUserCan(menu.permission) -> skip
     *  - Localization: title нь тухайн хэл дээр байх ёстой
     *
     * Menu бүтэц:
     * ---------------------------------------------------------------
     *  [
     *      parent_menu_id => [
     *          'title' => '...',
     *          'submenu' => [
     *              ['title' => '...', 'link' => '...', ...],
     *              ...
     *          ]
     *      ],
     *      ...
     *  ]
     *
     * Эцэст нь submenu хоосон parent-уудыг устгана.
     *
     * @return array Sidemenu-н бүтэц
     */
    public function getUserMenu(): array
    {
        $sidemenu = [];

        try {
            $alias = $this->getUser()->organization['alias'];
            
            $cache = $this->hasService('cache') ? $this->getService('cache') : null;
            $menuKey = "menu.{$this->getLanguageCode()}";
            $rows = $cache?->get($menuKey);
            if ($rows === null) {
                $model = new MenuModel($this->pdo);
                $rows = $model->getRowsByCode(
                    $this->getLanguageCode(),
                    [
                        'ORDER BY' => 'p.position',
                        'WHERE'    => 'p.is_visible=1'
                    ]
                );
                $cache?->set($menuKey, $rows);
            }
            foreach ($rows as $row) {
                $title = $row['localized']['title'] ?? null;

                // Organization alias filter
                if (!empty($row['alias']) && $alias !== $row['alias']) {
                    continue;
                }

                // Permission filter
                if (!empty($row['permission'])
                    && !$this->isUserCan($row['permission'])) {
                    continue;
                }

                // Parent menu
                if ($row['parent_id'] == 0) {
                    if (!isset($sidemenu[$row['id']])) {
                        $sidemenu[$row['id']] = ['title' => $title, 'submenu' => []];
                    } else {
                        $sidemenu[$row['id']]['title'] = $title;
                    }
                }
                // Child menu
                else {
                    unset($row['localized']);
                    $row['title'] = $title;

                    if (!isset($sidemenu[$row['parent_id']])) {
                        $sidemenu[$row['parent_id']] =
                            ['title' => '', 'submenu' => [$row]];
                    } else {
                        $sidemenu[$row['parent_id']]['submenu'][] = $row;
                    }
                }
            }

            // submenu хоосон parent-уудыг устгах
            foreach ($sidemenu as $key => $rows) {
                if (empty($rows['submenu'])) {
                    unset($sidemenu[$key]);
                }
            }
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($e->getMessage());
            }
        }

        return $sidemenu;
    }

    /**
     * Нэвтэрсэн хэрэглэгчийн харьяалагддаг бүх идэвхтэй байгууллагын жагсаалт.
     *
     * Topbar дахь байгууллага сонгох dropdown-д ашиглагдана. Хэрэглэгч өмнө нь
     * хасагдсан байж болох тул зөвхөн is_active=1 байгууллагуудыг буцаана.
     * Одоо нэвтэрсэн байгууллага мөн заавал багтана.
     *
     * system_coder бол cross-tenant superuser тул бүх идэвхтэй байгууллагыг
     * буцаана - JWTAuthMiddleware/LoginController нь coder-т гишүүнчлэл
     * шаардахгүй болсонтой уялдаж, switcher-ээс аль ч байгууллага руу
     * шилжих боломжийг олгоно.
     *
     * id=1 (системийн үндсэн байгууллага) жагсаалтад байвал үргэлж
     * хамгийн эхэнд, бусад нь нэрийн эрэмбээр жагсана.
     *
     * Гаралт:
     *   [
     *      ['id' => 2, 'name' => '...', 'logo' => '...'],
     *      ...
     *   ]
     *
     * @return array
     */
    public function getUserOrganizations(): array
    {
        $orgs = [];

        try {
            $user_id = (int) ($this->getUser()?->profile['id'] ?? 0);
            if ($user_id < 1) {
                return [];
            }

            $orgTable = (new OrganizationModel($this->pdo))->getName();
            if ($this->isUser('system_coder')) {
                // system_coder -> бүх идэвхтэй байгууллага (гишүүнчлэлээс хамаарахгүй)
                $stmt = $this->prepare(
                    "SELECT id, name, logo
                       FROM $orgTable
                      WHERE is_active = 1
                      ORDER BY name"
                );
            } else {
                // Жирийн хэрэглэгч -> зөвхөн харьяалагддаг байгууллагууд
                $ouTable = (new OrganizationUserModel($this->pdo))->getName();
                $stmt = $this->prepare(
                    "SELECT DISTINCT o.id, o.name, o.logo
                       FROM $ouTable ou
                       INNER JOIN $orgTable o ON ou.organization_id = o.id
                      WHERE ou.user_id = :uid AND o.is_active = 1
                      ORDER BY o.name"
                );
                $stmt->bindValue(':uid', $user_id, \PDO::PARAM_INT);
            }
            $stmt->execute();
            $orgs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Одоо нэвтэрсэн идэвхтэй байгууллагыг заавал багтаах
            $current = (int) ($this->getUser()?->organization['id'] ?? 0);
            if ($current > 0
                && !\in_array($current, \array_map('intval', \array_column($orgs, 'id')), true)
            ) {
                $orgs[] = [
                    'id'   => $current,
                    'name' => $this->getUser()?->organization['name'] ?? ('#' . $current),
                    'logo' => $this->getUser()?->organization['logo'] ?? ''
                ];
            }

            // id=1 (үндсэн байгууллага) жагсаалтад байвал үргэлж хамгийн эхэнд.
            // usort нь stable тул бусад нь нэрийн эрэмбээ хадгална.
            \usort(
                $orgs,
                fn($a, $b) => ((int) $b['id'] === 1) <=> ((int) $a['id'] === 1)
            );
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($e->getMessage());
            }
        }

        return $orgs;
    }
}
