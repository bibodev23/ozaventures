<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link internal messages to outings when needed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD outing_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_MESSAGE_OUTING ON message (outing_id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_MESSAGE_OUTING FOREIGN KEY (outing_id) REFERENCES outing (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_MESSAGE_OUTING');
        $this->addSql('DROP INDEX IDX_MESSAGE_OUTING');
        $this->addSql('ALTER TABLE message DROP outing_id');
    }
}
