<?php

namespace Raptor\User;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class UsersModel
 * --------------------------------------------------------------------
 *  `users` хүснэгтийн ORM загвар.
 *
 *  Энэхүү модель нь хэрэглэгчийн үндсэн мэдээлэл
 *  (нэвтрэх нэр, имэйл, овог нэр, утас, профайл зураг, идэвхтэй эсэх,
 *  мөн бүртгэл болон шинэчлэлийн мета өгөгдөл)-ийг удирдана.
 *
 *  DataObject\Model дээр суурилсан  
 *  MySQL/PostgreSQL аль алинд нь ажиллахад бэлэн  
 *  created_at автоматаар populate хийнэ  
 *  Анхны админыг __initial() үед үүсгэнэ  
 * 
 *  * **PDO Injection тухай тэмдэглэл**
 * --------------------------------------------------------------
 * Raptor Framework нь PDO instance-ийг дараах дарааллаар inject хийдэг:
 *
 *   Middleware  ->  ServerRequest::withAttribute('pdo')  
 *                 -> Controller::$pdo  
 *                 -> Model::__construct(\PDO $pdo)
 *
 * Иймээс энэхүү `$pdo` нь *middleware injection-ээр дамжсан баталгаатай
 * холболт* бөгөөд Model анги зөвхөн өгөгдөлтэй ажиллахад анхаарна.
 *
 * Framework-ийн DI container ашигладаггүй, request-scope injection юм  
 * Нэг request дотор нэг л PDO instance ажиллана  
 *
 * @package Raptor\User
 */
class UsersModel extends Model
{
    /**
     * UsersModel constructor.
     *
     * @param \PDO $pdo
     *      Middleware -> ServerRequest -> Controller-аас дамжиж ирсэн
     *      **баталгаатай PDO instance**.
     *
     *      Энэхүү constructor нь:
     *        * хүснэгтийн бүх багануудыг тодорхойлно  
     *        * primary / unique constraint-уудыг тохируулна  
     *        * model-тэй холбоно 
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),

           (new Column('username', 'varchar', 143))->unique(),
           (new Column('password', 'varchar', 255))->default(''),

            new Column('first_name', 'varchar', 128),
            new Column('last_name', 'varchar', 128),
            new Column('phone', 'varchar', 128),

           (new Column('email', 'varchar', 143))->unique(),

            new Column('photo', 'varchar', 255),      // public img uri
            new Column('photo_file', 'varchar', 255), // physical img file location
            new Column('photo_size', 'int'),          // img size by bytes

            new Column('code', 'varchar', 2),         // хэлний код, locale гэх мэт
           (new Column('is_active', 'tinyint'))->default(1),

            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),

            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint'),
        ]);

        $this->setTable('users');
    }

    // <editor-fold defaultstate="collapsed" desc="__initial">
    /**
     * __initial()
     * -------------------------------------------------------------
     *  Model-ийг анх ажилуулж sql хүснэгтийг бодитоор үүсгэх үед ажиллах нэмэлт логик.
     *
     *  Энд системийн анхны хэрэглэгч болох:
     *      username: admin
     *      email: admin@example.com
     *      password: password (bcrypt)
     *  гэсэн default админыг автоматаар үүсгэнэ.
     * 
     * Үндсэн зорилго бол систем "хоосон" байх үеийн суурь хэрэглэгчийг автоматаар үүсгэх юм.
     */
    protected function __initial()
    {
        $table = $this->getName();

        // Хайлт, шүүлтийн гүйцэтгэлийг сайжруулах индекс
        $this->exec("CREATE INDEX {$table}_idx_active_name ON $table (is_active, first_name, last_name)");

        $now   = \date('Y-m-d H:i:s');
        $pass  = \password_hash('password', \PASSWORD_BCRYPT);
        $passQ = $this->quote($pass);
        $query =
            "INSERT INTO $table(created_at, username, password, first_name, last_name, email) ".
            "VALUES('$now', 'admin', $passQ, 'Admin', 'System', 'admin@example.com')";
        $this->exec($query);
    }
    // </editor-fold>

    // =====================================================================
    //  CRUD override - created_at автоматаар бөглөх
    // =====================================================================
    /**
     * insert()
     * ---------------------------------------------------------
     *  Бичлэг шинээр үүсгэх үед created_at утгыг автоматаар populate
     *  хийдэг override функц (хэрвээ шинэ утгууд дотор агуулагдаагүй бол).
     *
     * @param array $record
     * @return array|false
     */
    public function insert(array $record): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record);
    }
}
