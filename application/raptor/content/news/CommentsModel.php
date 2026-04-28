<?php

namespace Raptor\Content;

use codesaur\DataObject\Column;
use codesaur\DataObject\Model;

/**
 * Class CommentsModel
 *
 * Мэдээний сэтгэгдлүүдийг хадгалах model.
 *
 * @package Raptor\Content
 */
class CommentsModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('news_id', 'bigint'),
            new Column('parent_id', 'bigint'),
            new Column('created_by', 'bigint'),
            new Column('name', 'varchar', 255),
            new Column('email', 'varchar', 255),
            new Column('comment', 'text'),
            new Column('created_at', 'datetime')
        ]);

        $this->setTable('news_comments');
    }

    /**
     * Хүснэгтийг анх үүсгэх үед ажиллах hook.
     *
     * @return void
     */
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        
        $table = $this->getName();
        $news = (new NewsModel($this->pdo))->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec(
            "ALTER TABLE $table ADD CONSTRAINT {$table}_fk_news_id
             FOREIGN KEY (news_id) REFERENCES $news(id)
             ON DELETE CASCADE ON UPDATE CASCADE"
        );
        $this->exec(
            "ALTER TABLE $table ADD CONSTRAINT {$table}_fk_parent_id
             FOREIGN KEY (parent_id) REFERENCES $table(id)
             ON DELETE CASCADE ON UPDATE CASCADE"
        );
        $this->exec(
            "ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by
             FOREIGN KEY (created_by) REFERENCES $users(id)
             ON DELETE SET NULL ON UPDATE CASCADE"
        );
        
        $this->setForeignKeyChecks(true);

        $this->exec("CREATE INDEX {$table}_idx_news_id ON $table (news_id)");
        $this->exec("CREATE INDEX {$table}_idx_created ON $table (created_at)");
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
