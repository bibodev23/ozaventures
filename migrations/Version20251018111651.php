<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251018111651 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE outing ADD created_by_id INT NOT NULL, ADD updated_by_id INT DEFAULT NULL, ADD created_at DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE outing ADD CONSTRAINT FK_F2A10625B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE outing ADD CONSTRAINT FK_F2A10625896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_F2A10625B03A8386 ON outing (created_by_id)');
        $this->addSql('CREATE INDEX IDX_F2A10625896DBBDE ON outing (updated_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE outing DROP FOREIGN KEY FK_F2A10625B03A8386');
        $this->addSql('ALTER TABLE outing DROP FOREIGN KEY FK_F2A10625896DBBDE');
        $this->addSql('DROP INDEX IDX_F2A10625B03A8386 ON outing');
        $this->addSql('DROP INDEX IDX_F2A10625896DBBDE ON outing');
        $this->addSql('ALTER TABLE outing DROP created_by_id, DROP updated_by_id, DROP created_at, DROP updated_at');
    }
}
