<?php

namespace Raptor\Trash;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class TrashModel
 *
 * Устгагдсан бичлэгүүдийг хадгалах загвар.
 * Ямар ч хүснэгтээс устгасан өгөгдлийг JSON хэлбэрээр хадгалж,
 * шаардлагатай үед сэргээх боломж олгоно.
 *
 * `log_table` багана нь сэргээгдэх бичлэгийн log channel-ийн нэрийг хадгална
 * (жишээ: 'products', 'news', 'content'). restore() үед энэ утгыг шууд
 * `$this->log()`-д дамжуулан тухайн модулийн log table-д "restored" мөр бичнэ.
 *
 * @package Raptor\Trash
 */
class TrashModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('table_name', 'varchar', 128))->notNull(),
           (new Column('log_table', 'varchar', 64))->notNull(),
            new Column('original_id', 'bigint'),
            new Column('record_data', 'mediumtext'),
            new Column('deleted_by', 'bigint'),
            new Column('deleted_at', 'datetime'),
        ]);

        $this->setTable('trash');
    }

    protected function __initial()
    {
        $table = $this->getName();
        $this->exec("CREATE INDEX {$table}_idx_table ON $table (table_name)");
        $this->exec("CREATE INDEX {$table}_idx_deleted ON $table (deleted_at DESC)");
    }

    /**
     * Устгагдсан бичлэгийг trash-д хадгалах.
     *
     * @param string $logTable   Сэргээх үед бичих log channel-ийн нэр (== `$this->log()`-ийн эхний параметр)
     * @param string $tableName  Эх хүснэгтийн нэр
     * @param int    $originalId Анхны бичлэгийн ID
     * @param array  $recordData Бичлэгийн бүрэн өгөгдөл
     * @param int    $deletedBy  Устгасан хэрэглэгчийн ID
     * @return array
     */
    public function store(
        string $logTable,
        string $tableName,
        int $originalId,
        array $recordData,
        int $deletedBy
    ): array {
        $result = $this->insert([
            'table_name'  => $tableName,
            'log_table'   => $logTable,
            'original_id' => $originalId,
            'record_data' => \json_encode($recordData, \JSON_UNESCAPED_UNICODE),
            'deleted_by'  => $deletedBy,
            'deleted_at'  => \date('Y-m-d H:i:s'),
        ]);

        // Trash badge-д зориулан лог бичих
        try {
            $logger = new \Raptor\Log\Logger($this->pdo);
            $logger->setTable('trash');
            $logger->log(\Psr\Log\LogLevel::ALERT, "[$tableName] #$originalId -> trash", [
                'action'      => 'store',
                'log_table'   => $logTable,
                'table_name'  => $tableName,
                'original_id' => $originalId,
                'auth_user'   => ['id' => $deletedBy],
            ]);
        } catch (\Throwable $e) {
            // Лог бичих амжилтгүй бол чимээгүй алгасна
        }

        return $result;
    }
}
