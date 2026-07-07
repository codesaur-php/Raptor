<?php

namespace Raptor\Authentication;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;
use codesaur\DataObject\Constants;

/**
 * Имэйл баталгаажуулах холбоос хэдэн цагийн туршид хүчинтэй байхыг заана.
 * .env дээр RAPTOR_SIGNUP_VERIFY_HOURS утгаар тохируулж болно (default 48).
 */
\define('RAPTOR_SIGNUP_VERIFY_HOURS', (int) ($_ENV['RAPTOR_SIGNUP_VERIFY_HOURS'] ?? 48));

/**
 * SignupModel - Шинэ хэрэглэгч үүсгэх хүсэлтийн (signup requests) модель.
 *
 * Энэ хүснэгт нь хэрэглэгчийн бүртгэлийн мэдээллийг
 * шууд UsersModel рүү оруулахын өмнөх "үргэлжлүүлэн баталгаажуулах шаардлагатай"
 * завсрын шатны хүсэлт хэлбэрээр хадгалдаг.
 *
 * Яагаад тусдаа хүснэгт хэрэгтэй вэ?
 * ---------------------------------------------------------------
 * - Шинэ хэрэглэгч шууд идэвхжих ёсгүй (security requirement)
 * - Имэйл баталгаажуулалт (double opt-in) + Admin баталгаажуулалт хийх боломжтой
 * - username/email UNIQUE тул нэг хүн давтан хүсэлт өгч спамдэхээс сэргийлнэ
 *   (татгалзсан хүсэлтийн мөр устгагдтал ижил нэр/хаягаар дахин хүсэлт өгөх боломжгүй)
 * - Бүртгэлийн урьдчилсан мэдээллийг audit trail хэлбэрээр хадгалдаг
 *
 * Хүсэлтийн амьдралын мөчлөг (status + verified_at):
 * ---------------------------------------------------------------
 *   1) Хүсэлт орж ирэхэд:  status='pending', verified_at=NULL, token=санамсаргүй hex
 *   2) Имэйл баталгаажихад: verified_at=огноо (админы жагсаалтад зөвхөн
 *      verified_at NOT NULL хүсэлтүүд харагдана)
 *   3) Админ баталвал:      status='approved' (user_id-д шинэ хэрэглэгч холбогдоно)
 *      Админ татгалзвал:    status='rejected' (мөр UNIQUE тул дахин хүсэлт өгөхийг
 *      хаана; админ Trash руу бүрэн устгаснаар дахин хүсэлт өгөх боломж нээгдэнэ)
 *
 * Хүснэгтийн бүтэц:
 * ---------------------------------------------------------------
 * id            - bigint, primary key
 * user_id       - UsersModel.id рүү FK (approve хийсний дараа холбогдоно)
 * username      - хүсэлт гаргагчийн нэр (UNIQUE)
 * password      - bcrypt хэш хэлбэрээр хадгалах
 * email         - имэйл хаяг (UNIQUE)
 * code          - localization хэлний код (жишээ: "mn", "en")
 * status        - хүсэлтийн төлөв: pending | approved | rejected
 * token         - имэйл баталгаажуулах санамсаргүй токен (64 hex тэмдэгт)
 * verified_at   - имэйл баталгаажсан огноо (NULL = баталгаажаагүй)
 * created_at    - үүсгэсэн огноо
 * updated_at    - шинэчилсэн огноо
 * updated_by    - өөрчилсөн хэрэглэгчийн id (FK -> users.id)
 *
 * ForeignKey холбоосууд:
 * ---------------------------------------------------------------
 * - signup.user_id       -> users.id
 *       ON DELETE SET NULL
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
    /** Хүсэлтийн төлвүүд */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Модель үүсэх үед баганууд болон хүснэгтийн нэр тохируулах.
     *
     * @param \PDO $pdo  PDO instance (MySQL/PostgreSQL)
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id',          'bigint'))->primary(),
            new Column('user_id',     'bigint'),
           (new Column('username',    'varchar', 128))->unique(),
           (new Column('password',    'varchar', 255))->default(''),
           (new Column('email',       'varchar', 128))->unique(),
            new Column('code',        'varchar', Constants::DEFAULT_CODE_LENGTH),
           (new Column('status',      'varchar', 16))->default(self::STATUS_PENDING),
           (new Column('token',       'varchar', 64))->default(''),
            new Column('verified_at', 'datetime'),
            new Column('created_at',  'datetime'),
            new Column('updated_at',  'datetime'),
            new Column('updated_by',  'bigint')
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

        // Хайлтын гүйцэтгэлийг сайжруулах индексүүд
        $this->exec("CREATE INDEX {$table}_idx_user_id ON $table (user_id)");
        // Имэйл баталгаажуулах линк дээр дарахад токеноор хайдаг
        $this->exec("CREATE INDEX {$table}_idx_token ON $table (token)");
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
