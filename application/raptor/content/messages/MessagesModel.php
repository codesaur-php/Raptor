<?php

namespace Raptor\Content;

use codesaur\DataObject\Column;
use codesaur\DataObject\Constants;
use codesaur\DataObject\Model;

/**
 * Class MessagesModel
 *
 * Холбоо барих хуудаснаас ирсэн мессежүүдийг хадгалах model.
 *
 * @package Raptor\Content
 */
class MessagesModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('name', 'varchar', 255),
            new Column('phone', 'varchar', 50),
            new Column('email', 'varchar', 255),
            new Column('message', 'text'),
            new Column('code', 'varchar', Constants::DEFAULT_CODE_LENGTH),
           (new Column('is_read', 'tinyint'))->default(0),
            new Column('replied_note', 'text'),
            new Column('created_at', 'datetime')
        ]);

        $this->setTable('messages');
    }

    /**
     * Хүснэгтийг анх үүсгэх үед ажиллах hook.
     *
     * @return void
     */
    protected function __initial()
    {
        $table = $this->getName();
        $this->exec("CREATE INDEX {$table}_idx_read ON $table (is_read)");
        $this->exec("CREATE INDEX {$table}_idx_created ON $table (created_at DESC)");
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
}
