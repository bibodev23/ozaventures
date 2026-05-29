<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add login credentials for animators';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE animator ADD username VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE animator ADD password_hash VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE animator ADD must_change_password BOOLEAN DEFAULT true NOT NULL');
        $this->addSql(sprintf(
            "UPDATE animator SET username = lower(regexp_replace(first_name || '.' || last_name || '.' || id::text, '[^a-zA-Z0-9._-]', '', 'g')), password_hash = '%s' WHERE username IS NULL OR password_hash IS NULL",
            '$2y$13$CobcQ.mD3gdvyPLDyljtxOss6VN0LN4f3PWe/RjopsGKTJZDjXbDS',
        ));
        $this->addSql('ALTER TABLE animator ALTER username SET NOT NULL');
        $this->addSql('ALTER TABLE animator ALTER password_hash SET NOT NULL');
        $this->addSql('ALTER TABLE animator ALTER must_change_password DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_70FBD26DF85E0677 ON animator (username)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS UNIQ_70FBD26DF85E0677');
        $this->addSql('ALTER TABLE animator DROP username');
        $this->addSql('ALTER TABLE animator DROP password_hash');
        $this->addSql('ALTER TABLE animator DROP must_change_password');
    }
}
