<?php

namespace Raptor\Template;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class AdminBadgeSeenModel
 * ---------------------------------------------------------------
 * Admin Badge Seen - Badge уншсан төлвийн загвар
 *
 * Dashboard sidebar-ийн badge системд admin бүрийн module бүрийг
 * сүүлд хэзээ үзсэн мэдээллийг хадгалах загвар (admin_badge_seen хүснэгт).
 * Admin + module хос бүрт яг 1 мөр байна (UNIQUE index).
 *
 * Баганууд:
 * ---------------------------------------------------------------
 * id              - Primary key (bigint, auto increment)
 * admin_id        - Нэвтэрсэн admin-ий хэрэглэгчийн ID
 * module          - Module-ийн path (жишээ: '/dashboard/news', '/dashboard/manual')
 * checked_at      - Admin тухайн module-ийн sidebar линк дээр сүүлд дарсан
 *                   datetime. BadgeController::list() энэ цагаас хойшхи
 *                   log бичлэгүүдийг тоолж badge тоог гаргана
 * last_seen_count - File-count badge-д зориулсан тоолуур. Manual болон
 *                   migrations гэх мэт log-д бичигддэггүй module-уудад
 *                   admin сүүлд үзэх үедээ хавтас дахь файлын тоог энд
 *                   хадгална. Дараагийн list() дуудалт дээр одоогийн
 *                   файлын тоо > last_seen_count бол зөрүүг badge-ээр
 *                   харуулна. Log-д суурилсан module-уудад 0 байна
 *
 * @package Raptor\Template
 */
class AdminBadgeSeenModel extends Model
{
    /**
     * Загварын баганууд болон хүснэгтийн нэрийг тохируулна.
     *
     * Хүснэгт анхны ашиглалтад Model framework автоматаар үүсгэнэ.
     * __initial() method нь UNIQUE index нэмнэ.
     *
     * @param \PDO $pdo PDO холболтын instance
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('admin_id', 'bigint'),
            new Column('module', 'varchar', 100),
            new Column('checked_at', 'datetime'),
           (new Column('last_seen_count', 'int'))->default(0)
        ]);

        $this->setTable('admin_badge_seen');
    }

    /**
     * Хүснэгт анх үүсэх үед ажиллах initial тохиргоо.
     *
     * admin_id + module хосоор UNIQUE index үүсгэнэ.
     * Нэг admin нэг module-д зөвхөн нэг бичлэгтэй байхыг баталгаажуулна.
     *
     * @return void
     */
    protected function __initial()
    {
        $table = $this->getName();
        $this->exec("CREATE UNIQUE INDEX {$table}_uq_admin_module ON $table (admin_id, module)");
    }
}
