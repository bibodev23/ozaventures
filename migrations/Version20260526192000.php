<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526192000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align index names with Doctrine metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_8246315f9a7c7e4d34f2c7e');
        $this->addSql('ALTER INDEX IF EXISTS idx_8246315f9a7c7e4d RENAME TO IDX_F2A106254EC001D1');
        $this->addSql('ALTER INDEX IF EXISTS idx_503a302bf58d7487 RENAME TO IDX_333D004EAF4C7531');
        $this->addSql('ALTER INDEX IF EXISTS idx_503a302bdd62c21b RENAME TO IDX_333D004EDD62C21B');
        $this->addSql('ALTER INDEX IF EXISTS idx_49f895f4f58d7487 RENAME TO IDX_F9F079CBAF4C7531');
        $this->addSql('ALTER INDEX IF EXISTS idx_49f895f49553c383 RENAME TO IDX_F9F079CB70FBD26D');
        $this->addSql('ALTER INDEX IF EXISTS idx_22b35429a7c7e4d RENAME TO IDX_22B354294EC001D1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IF EXISTS idx_f2a106254ec001d1 RENAME TO IDX_8246315F9A7C7E4D');
        $this->addSql('ALTER INDEX IF EXISTS idx_333d004eaf4c7531 RENAME TO IDX_503A302BF58D7487');
        $this->addSql('ALTER INDEX IF EXISTS idx_333d004edd62c21b RENAME TO IDX_503A302BDD62C21B');
        $this->addSql('ALTER INDEX IF EXISTS idx_f9f079cbaf4c7531 RENAME TO IDX_49F895F4F58D7487');
        $this->addSql('ALTER INDEX IF EXISTS idx_f9f079cb70fbd26d RENAME TO IDX_49F895F49553C383');
        $this->addSql('ALTER INDEX IF EXISTS idx_22b354294ec001d1 RENAME TO IDX_22B35429A7C7E4D');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8246315F9A7C7E4D34F2C7E ON outing (season_id, number)');
    }
}
