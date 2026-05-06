<?php

namespace Raptor\Development;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class DevRequestModel
 * ------------------------------------------------------------------
 * Хөгжүүлэлтийн хүсэлтийн өгөгдлийн загвар (Model).
 *
 * `dev_requests` хүснэгтэд хандаж CRUD үйлдлүүд гүйцэтгэнэ.
 *
 * @package Raptor\Development
 */
class DevRequestModel extends Model
{
    /**
     * DevRequestModel constructor.
     *
     * @param \PDO $pdo Database connection instance
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('title', 'varchar', 255),
            new Column('content', 'text'),
           (new Column('status', 'varchar', 16))->default('pending'),
            new Column('assigned_to', 'bigint'),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);

        $this->setTable('dev_requests');
    }

    /**
     * Анхны тохиргоо: FK constraint болон индекс үүсгэх.
     *
     * @return void
     */
    protected function __initial()
    {
        $table = $this->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_assigned_to FOREIGN KEY (assigned_to) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");

        // Хүсэлтийн шүүлтийн гүйцэтгэлийг сайжруулах индекс
        $this->exec("CREATE INDEX {$table}_idx_status ON $table (status)");
    }

    /**
     * Шинэ хөгжүүлэлтийн хүсэлт үүсгэх.
     *
     * @param array $record Бичлэгийн өгөгдөл
     * @return array Амжилттай бол бичлэгийн массив
     */
    public function insert(array $record): array
    {
        if (!isset($record['created_at'])) {
            $record['created_at'] = \date('Y-m-d H:i:s');
        }
        return parent::insert($record);
    }

    /**
     * Хөгжүүлэлтийн хүсэлтийг шинэчлэх.
     *
     * @param int   $id     Бичлэгийн ID
     * @param array $record Шинэчлэх өгөгдөл
     * @return array Амжилттай бол шинэчилсэн бичлэг
     */
    public function updateById(int $id, array $record): array
    {
        if (!isset($record['updated_at'])) {
            $record['updated_at'] = \date('Y-m-d H:i:s');
        }
        return parent::updateById($id, $record);
    }
}
