<?php

namespace Dashboard\Shop;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class OrdersModel
 *
 * Захиалгын (`orders`) хүснэгттэй ажиллах өгөгдлийн загвар (Model) класс.
 *
 * @package Dashboard\Shop
 */
class OrdersModel extends Model
{
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

        $this->setTable('orders');
    }

    protected function __initial()
    {
        $table = $this->getName();

        if ($this->getDriverName() != 'sqlite') {
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
        }

        $this->exec("CREATE INDEX {$table}_idx_active_status ON $table (is_active, status)");
    }

    public function insert(array $record): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');

        return parent::insert($record);
    }
}
