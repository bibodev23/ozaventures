<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add important child profile information for direction follow-up';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE child ADD legal_guardians TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE child ADD legal_guardian_phones VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE child ADD allergies TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE child ADD photo_permission BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE child ADD important_notes TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE child DROP legal_guardians');
        $this->addSql('ALTER TABLE child DROP legal_guardian_phones');
        $this->addSql('ALTER TABLE child DROP allergies');
        $this->addSql('ALTER TABLE child DROP photo_permission');
        $this->addSql('ALTER TABLE child DROP important_notes');
    }
}
