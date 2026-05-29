<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529211000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize user and messaging index names';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IF EXISTS idx_api_token_user RENAME TO IDX_7BA2F5EBA76ED395');
        $this->addSql('ALTER INDEX IF EXISTS uniq_app_user_username RENAME TO UNIQ_88BDF3E9F85E0677');
        $this->addSql('ALTER INDEX IF EXISTS uniq_animator_user RENAME TO UNIQ_60BF9208A76ED395');
        $this->addSql('ALTER INDEX IF EXISTS idx_message_recipient_message RENAME TO IDX_2BDFD7F537A1329');
        $this->addSql('ALTER INDEX IF EXISTS idx_message_recipient_recipient RENAME TO IDX_2BDFD7FE92F8F78');
        $this->addSql('ALTER INDEX IF EXISTS idx_mobile_device_token_user RENAME TO IDX_17D083F0A76ED395');
        $this->addSql('ALTER INDEX IF EXISTS idx_message_sender RENAME TO IDX_B6BD307FF624B39D');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IF EXISTS idx_7ba2f5eba76ed395 RENAME TO IDX_API_TOKEN_USER');
        $this->addSql('ALTER INDEX IF EXISTS uniq_88bdf3e9f85e0677 RENAME TO UNIQ_APP_USER_USERNAME');
        $this->addSql('ALTER INDEX IF EXISTS uniq_60bf9208a76ed395 RENAME TO UNIQ_ANIMATOR_USER');
        $this->addSql('ALTER INDEX IF EXISTS idx_2bdfd7f537a1329 RENAME TO IDX_MESSAGE_RECIPIENT_MESSAGE');
        $this->addSql('ALTER INDEX IF EXISTS idx_2bdfd7fe92f8f78 RENAME TO IDX_MESSAGE_RECIPIENT_RECIPIENT');
        $this->addSql('ALTER INDEX IF EXISTS idx_17d083f0a76ed395 RENAME TO IDX_MOBILE_DEVICE_TOKEN_USER');
        $this->addSql('ALTER INDEX IF EXISTS idx_b6bd307ff624b39d RENAME TO IDX_MESSAGE_SENDER');
    }
}
