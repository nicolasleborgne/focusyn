<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Double authentification TOTP et rattachement de comptes externes.
 */
final class Version20260905161348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Double authentification TOTP et comptes externes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_oauth_identities (provider VARCHAR(20) NOT NULL, external_id VARCHAR(191) NOT NULL, linked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_15EAC18EA76ED395 ON user_oauth_identities (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_oauth_provider_external ON user_oauth_identities (provider, external_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_oauth_user_provider ON user_oauth_identities (user_id, provider)');
        $this->addSql('ALTER TABLE user_oauth_identities ADD CONSTRAINT FK_15EAC18EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users ADD totp_secret VARCHAR(64) DEFAULT NULL');
        // Ajoutée en trois temps : une colonne NOT NULL sans valeur par défaut
        // échouerait sur une table déjà peuplée. Le schéma final reste sans
        // valeur par défaut, conformément au mapping.
        $this->addSql('ALTER TABLE users ADD backup_codes JSON DEFAULT NULL');
        $this->addSql("UPDATE users SET backup_codes = '[]'");
        $this->addSql('ALTER TABLE users ALTER COLUMN backup_codes SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_oauth_identities DROP CONSTRAINT FK_15EAC18EA76ED395');
        $this->addSql('DROP TABLE user_oauth_identities');
        $this->addSql('ALTER TABLE users DROP totp_secret');
        $this->addSql('ALTER TABLE users DROP backup_codes');
    }
}
