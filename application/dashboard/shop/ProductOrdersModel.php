<?php

namespace Dashboard\Shop;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class ProductOrdersModel
 * ---------------------------------------------------------------
 * Захиалгын (`products_orders`) хүснэгттэй ажиллах өгөгдлийн загвар (Model) класс.
 *
 * Үндсэн боломжууд:
 *   - Захиалгын хүснэгтийн багануудыг тодорхойлох
 *   - FK constraint-уудыг анхны тохиргоонд үүсгэх (users, products)
 *   - Шинэ захиалга үүсгэх үед created_at талбарыг автоматаар бөглөх
 *
 * Хүснэгтийн талбарууд:
 *   - id (bigint, primary) - Захиалгын өвөрмөц дугаар
 *   - product_id (bigint) - Бүтээгдэхүүний ID (FK -> products)
 *   - product_title (varchar 255) - Бүтээгдэхүүний нэр
 *   - customer_name (varchar 128) - Захиалагчийн нэр
 *   - customer_email (varchar 128) - Захиалагчийн имэйл
 *   - customer_phone (varchar 32) - Захиалагчийн утас
 *   - message (text) - Захиалагчийн тэмдэглэл
 *   - quantity (int, default: 1) - Тоо ширхэг
 *   - code (varchar 2) - Хэлний код
 *   - status (varchar 32, default: 'new') - Захиалгын төлөв
 *   - is_active (tinyint, default: 1) - Идэвхтэй эсэх
 *
 * @package Dashboard\Shop
 */
class ProductOrdersModel extends Model
{
    /**
     * ProductOrdersModel constructor.
     *
     * PDO instance-г оноож, захиалгын хүснэгтийн бүх багануудыг тодорхойлно.
     * Хүснэгтийн нэрийг 'products_orders' гэж тохируулна.
     *
     * @param \PDO $pdo Database connection instance
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('product_id', 'bigint'),
            new Column('product_title', 'varchar', 255),
            new Column('customer_name', 'varchar', 128),
            new Column('customer_email', 'varchar', 128),
            new Column('customer_phone', 'varchar', 32),
            new Column('message', 'text'),
           (new Column('quantity', 'int'))->default(1),
            new Column('code', 'varchar', 2),
           (new Column('status', 'varchar', 32))->default('new'),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);

        $this->setTable('products_orders');
    }

    /**
     * Анхны тохиргоо (initial setup).
     *
     * Хүснэгт анх үүсэх үед foreign key constraint-уудыг автоматаар үүсгэнэ:
     *   - created_by, updated_by -> users(id)
     *   - product_id -> products(id)
     *
     * @return void
     */
    protected function __initial()
    {
        $table = $this->getName();

        $this->setForeignKeyChecks(false);

        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $products = (new ProductsModel($this->pdo))->getName();

        $constraints = [
            'created_by' => "{$table}_fk_created_by",
            'updated_by' => "{$table}_fk_updated_by"
        ];

        foreach ($constraints as $column => $constraint) {
            $this->exec(
                "ALTER TABLE $table " .
                "ADD CONSTRAINT $constraint " .
                "FOREIGN KEY ($column) " .
                "REFERENCES $users(id) " .
                "ON DELETE SET NULL " .
                "ON UPDATE CASCADE"
            );
        }

        $this->exec(
            "ALTER TABLE $table " .
            "ADD CONSTRAINT {$table}_fk_product_id " .
            "FOREIGN KEY (product_id) " .
            "REFERENCES $products(id) " .
            "ON DELETE SET NULL " .
            "ON UPDATE CASCADE"
        );

        $this->setForeignKeyChecks(true);

        $this->exec("CREATE INDEX {$table}_idx_active_status ON $table (is_active, status)");
        $this->exec("CREATE INDEX {$table}_idx_product_id ON $table (product_id)");
    }

    /**
     * Шинэ захиалга үүсгэх.
     *
     * Захиалгын бичлэг үүсгэх үед created_at талбарыг автоматаар бөглөнө.
     *
     * @param array $record Захиалгын мэдээлэл
     * @return array|false Амжилттай бол үүссэн бичлэгийн массив, бусад тохиолдолд false
     */
    public function insert(array $record): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');

        return parent::insert($record);
    }
}
