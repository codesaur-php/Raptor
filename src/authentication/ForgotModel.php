<?php

namespace Raptor\Authentication;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class ForgotModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            new Column('account', 'bigint', 8),
            new Column('use_id', 'varchar', 255),
            new Column('username', 'varchar', 255),
            new Column('first_name', 'varchar', 255),
            new Column('last_name', 'varchar', 255),
            new Column('email', 'varchar', 128),
            new Column('remote_addr', 'varchar', 46),
            new Column('code', 'varchar', 6),
            new Column('status', 'tinyint', 1, 1),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime')
        ]);
        
        $this->setTable('raptor_forgot', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        $this->exec("ALTER TABLE {$this->getName()} ADD CONSTRAINT {$this->getName()}_fk_account FOREIGN KEY (account) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
    }
}
