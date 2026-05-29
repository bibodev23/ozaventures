<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526204500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add age group assignment to animators';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE animator ADD age_group VARCHAR(12) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE animator DROP age_group');
    }
}
