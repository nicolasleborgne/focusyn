<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260906095646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Invitations d\'organisation, et alignement du nom d\'index attendu par le gestionnaire de sessions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organization_invitations (organization_id UUID NOT NULL, email VARCHAR(180) NOT NULL, role VARCHAR(20) NOT NULL, token VARCHAR(32) NOT NULL, invited_by UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_137BB4D55F37A13B ON organization_invitations (token)');
        $this->addSql('CREATE UNIQUE INDEX uniq_invitation_org_email ON organization_invitations (organization_id, email)');

        // Symfony décrit lui-même la table des sessions dans le schéma Doctrine,
        // avec ce nom d'index. Le nôtre était un synonyme : on s'aligne, sinon
        // chaque comparaison de schéma proposerait à nouveau ce renommage.
        $this->addSql('ALTER INDEX idx_sessions_lifetime RENAME TO sess_lifetime_idx');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE organization_invitations');
        $this->addSql('ALTER INDEX sess_lifetime_idx RENAME TO idx_sessions_lifetime');
    }
}
