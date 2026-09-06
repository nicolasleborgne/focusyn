<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260906092152 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sessions de connexion : la table du gestionnaire PDO et l\'index que l\'écran des réglages présente.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE login_sessions (user_id UUID NOT NULL, device VARCHAR(120) NOT NULL, opened_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id VARCHAR(128) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_login_sessions_user ON login_sessions (user_id, last_seen_at)');

        // Les sessions passent en base : c'est ce qui permet d'en fermer une
        // depuis un autre appareil. Le schéma est celui qu'attend
        // PdoSessionHandler ; il sait le créer lui-même, mais une migration le
        // rend visible et reproductible.
        $this->addSql('CREATE TABLE sessions (sess_id VARCHAR(128) NOT NULL, sess_data BYTEA NOT NULL, sess_lifetime INTEGER NOT NULL, sess_time INTEGER NOT NULL, PRIMARY KEY (sess_id))');
        $this->addSql('CREATE INDEX idx_sessions_lifetime ON sessions (sess_lifetime)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE login_sessions');
        $this->addSql('DROP TABLE sessions');
    }
}
