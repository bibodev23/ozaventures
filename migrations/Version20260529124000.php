<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529124000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add exact child age for dashboard age distribution';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE child ADD age INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE child DROP age');
    }
}
