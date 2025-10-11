<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251011232127 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE outing (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE outing_kid (outing_id INT NOT NULL, kid_id INT NOT NULL, INDEX IDX_7F86ACA0AF4C7531 (outing_id), INDEX IDX_7F86ACA06A973770 (kid_id), PRIMARY KEY(outing_id, kid_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE outing_kid ADD CONSTRAINT FK_7F86ACA0AF4C7531 FOREIGN KEY (outing_id) REFERENCES outing (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE outing_kid ADD CONSTRAINT FK_7F86ACA06A973770 FOREIGN KEY (kid_id) REFERENCES kid (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE outing_kid DROP FOREIGN KEY FK_7F86ACA0AF4C7531');
        $this->addSql('ALTER TABLE outing_kid DROP FOREIGN KEY FK_7F86ACA06A973770');
        $this->addSql('DROP TABLE outing');
        $this->addSql('DROP TABLE outing_kid');
    }
}
