<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526200500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align animator username index name with Doctrine metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IF EXISTS uniq_70fbd26df85e0677 RENAME TO UNIQ_60BF9208F85E0677');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IF EXISTS uniq_60bf9208f85e0677 RENAME TO UNIQ_70FBD26DF85E0677');
    }
}
