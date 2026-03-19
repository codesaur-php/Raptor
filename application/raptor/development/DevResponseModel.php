<?php

namespace Raptor\Development;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class DevResponseModel
 * ------------------------------------------------------------------
 * Хөгжүүлэлтийн хүсэлтийн хариултуудын (thread) модель.
 *
 * Нэг хүсэлтэд олон хариулт бичигдэх боломжтой.
 * Хариулт бүр хэн, хэзээ бичсэн мэдээлэлтэй.
 *
 * @package Raptor\Development
 */
class DevResponseModel extends Model
{
    /**
     * DevResponseModel constructor.
     *
     * PDO instance-г оноож, хариултын хүснэгтийн бүх багануудыг тодорхойлно.
     * Хүснэгтийн нэрийг 'dev_requests_responses' гэж тохируулна.
     *
     * @param \PDO $pdo Database connection instance
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('request_id', 'bigint'))->notNull(),
            new Column('content', 'text'),
           (new Column('status', 'varchar', 16))->default(''),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint')
        ]);

        $this->setTable('dev_requests_responses');
    }

    /**
     * Анхны тохиргоо (initial setup).
     *
     * Хүснэгт анх үүсэх үед foreign key constraint-уудыг үүсгэнэ:
     *   - request_id -> dev_requests(id) ON DELETE CASCADE
     *   - created_by -> users(id) ON DELETE SET NULL
     *
     * Мөн request_id дээр index үүсгэнэ.
     *
     * @return void
     */
    protected function __initial()
    {
        $table = $this->getName();

        $this->setForeignKeyChecks(false);

        $requests = (new DevRequestModel($this->pdo))->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();

        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_request_id FOREIGN KEY (request_id) REFERENCES $requests(id) ON DELETE CASCADE ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");

        $this->setForeignKeyChecks(true);

        $this->exec("CREATE INDEX {$table}_idx_request_id ON $table (request_id)");
    }

    /**
     * Шинэ хариулт үүсгэх.
     *
     * Хариултын бичлэг үүсгэх үед created_at талбарыг
     * автоматаар бөглөнө (хэрэв өгөгдөөгүй бол).
     *
     * @param array $record Хариултын мэдээлэл
     * @return array|false Амжилттай бол үүссэн бичлэгийн массив, бусад тохиолдолд false
     */
    public function insert(array $record): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record);
    }
}
