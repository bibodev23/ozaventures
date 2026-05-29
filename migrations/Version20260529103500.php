<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529103500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align child photo permission default with Doctrine metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE child ALTER photo_permission DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE child ALTER photo_permission SET DEFAULT false');
    }
}
