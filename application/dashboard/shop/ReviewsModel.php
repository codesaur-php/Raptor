<?php

namespace Dashboard\Shop;

use codesaur\DataObject\Column;
use codesaur\DataObject\Model;

/**
 * Class ReviewsModel
 *
 * Бүтээгдэхүүний үнэлгээ (review/rating) хадгалах model.
 *
 * @package Dashboard\Shop
 */
class ReviewsModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('product_id', 'bigint'),
            new Column('created_by', 'bigint'),
            new Column('name', 'varchar', 255),
            new Column('email', 'varchar', 255),
           (new Column('rating', 'tinyint'))->default(5),
            new Column('comment', 'text'),
            new Column('created_at', 'datetime')
        ]);

        $this->setTable('products_reviews');
    }

    /**
     * {@inheritdoc}
     *
     * created_at талбарыг автоматаар тохируулна.
     */
    public function insert(array $record, ?array $content = null): array
    {
        $record['created_at'] = \date('Y-m-d H:i:s');
        return parent::insert($record, $content);
    }

    /**
     * Хүснэгтийг анх үүсгэх үед ажиллах hook.
     *
     * @return void
     */
    protected function __initial()
    {        
        $table = $this->getName();
        $products = (new ProductsModel($this->pdo))->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec(
            "ALTER TABLE $table ADD CONSTRAINT {$table}_fk_product_id
             FOREIGN KEY (product_id) REFERENCES $products(id)
             ON DELETE CASCADE ON UPDATE CASCADE"
        );
        $this->exec(
            "ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by
             FOREIGN KEY (created_by) REFERENCES $users(id)
             ON DELETE SET NULL ON UPDATE CASCADE"
        );        

        $this->exec("CREATE INDEX {$table}_idx_product ON $table (product_id)");
        $this->exec("CREATE INDEX {$table}_idx_product_rating ON $table (product_id, rating)");
        $this->exec("CREATE INDEX {$table}_idx_created ON $table (created_at)");
    }
}
