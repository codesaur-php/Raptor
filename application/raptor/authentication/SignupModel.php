<?php

namespace Raptor\Authentication;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;
use codesaur\DataObject\Constants;

/**
 * SignupModel - Шинэ хэрэглэгч үүсгэх хүсэлтийн (pending signup requests) модель.
 *
 * Энэ хүснэгт нь хэрэглэгчийн бүртгэлийн мэдээллийг
 * шууд UsersModel рүү оруулахын өмнөх "үргэлжлүүлэн баталгаажуулах шаардлагатай"
 * завсрын шатны хүсэлт хэлбэрээр хадгалдаг.
 *
 * Яагаад тусдаа хүснэгт хэрэгтэй вэ?
 * ---------------------------------------------------------------
 * - Шинэ хэрэглэгч шууд идэвхжих ёсгүй (security requirement)
 * - Admin баталгаажуулалт хийх боломжтой
 * - Давхардсан имэйл/нэртэй олон оролдлогыг хянах боломжтой
 * - Бүртгэлийн урьдчилсан мэдээллийг audit trail хэлбэрээр хадгалдаг
 *
 * Хүснэгтийн бүтэц:
 * ---------------------------------------------------------------
 * id            - bigint, primary key
 * user_id       - UsersModel.id рүү FK (approve хийсний дараа холбогдоно)
 * username      - хүсэлт гаргагчийн нэр
 * password      - bcrypt хэш хэлбэрээр хадгалах
 * email         - имэйл хаяг
 * code          - localization хэлний код (жишээ: "mn", "en")
 * is_active     - хүсэлт идэвхтэй эсэх
 * created_at    - үүсгэсэн огноо
 * updated_at    - шинэчилсэн огноо
 * updated_by    - өөрчилсөн хэрэглэгчийн id (FK -> users.id)
 *
 * ForeignKey холбоосууд:
 * ---------------------------------------------------------------
 * - signup.user_id       -> users.id
 *       ON DELETE CASCADE
 *       ON UPDATE CASCADE
 *
 * - signup.updated_by    -> users.id
 *       ON DELETE SET NULL
 *       ON UPDATE CASCADE
 *
 * Data integrity:
 * - Row insert/update үед created_at, updated_at автоматаар тохирно.
 * - PDOTrait логиктой зохицож Model::insert(), updateById() г ашиглана.
 */
class SignupModel extends Model
{
    /**
     * Модель үүсэх үед баганууд болон хүснэгтийн нэр тохируулах.
     *
     * @param \PDO $pdo  PDO instance (MySQL/PostgreSQL)
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id',         'bigint'))->primary(),
            new Column('user_id',    'bigint'),
            new Column('username',   'varchar', 128),
           (new Column('password',   'varchar', 255))->default(''),
            new Column('email',      'varchar', 128),
            new Column('code',       'varchar', Constants::DEFAULT_CODE_LENGTH),
           (new Column('is_active',  'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);

        $this->setTable('signup');
    }

    /**
     * __initial() - Модель анх үүсэхэд FK constraint-уудыг үүсгэх hook.
     *
     * Энэ функц нь DataObject-ийн convention дагуу хүснэгт
     * үүсэх үед автоматаар дуудагддаг (IF NOT EXISTS).
     *
     * Foreign key холбоос:
     *   signup.user_id -> users.id
     *       ON DELETE SET NULL
     *       ON UPDATE CASCADE
     *
     *   signup.updated_by -> users.id
     *       ON DELETE SET NULL
     *       ON UPDATE CASCADE
     *
     * @return void
     */
    protected function __initial()
    {
        $table = $this->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec(
            "ALTER TABLE $table
             ADD CONSTRAINT {$table}_fk_user_id
             FOREIGN KEY (user_id) REFERENCES $users(id)
             ON DELETE SET NULL ON UPDATE CASCADE"
        );
        $this->exec(
            "ALTER TABLE $table
             ADD CONSTRAINT {$table}_fk_updated_by
             FOREIGN KEY (updated_by) REFERENCES $users(id)
             ON DELETE SET NULL ON UPDATE CASCADE"
        );

        // Хайлтын гүйцэтгэлийг сайжруулах индекс
        $this->exec("CREATE INDEX {$table}_idx_user_id ON $table (user_id)");
    }

    /**
     * Insert хийх үед created_at талбарыг автоматаар тохируулах.
     *
     * @param array $record
     * @return array  Амжилттай бол өгөгдөл
     */
    public function insert(array $record): array
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record);
    }

    /**
     * updateById хийх үед updated_at талбарыг тохируулах.
     *
     * @param int   $id
     * @param array $record
     * @return array
     */
    public function updateById(int $id, array $record): array
    {
        $record['updated_at'] ??= \date('Y-m-d H:i:s');
        return parent::updateById($id, $record);
    }
}
