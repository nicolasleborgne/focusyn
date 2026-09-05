<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Comptes, organisations et appartenances.
 *
 * Aucune clé étrangère entre `organization_memberships.member_id` et `users` :
 * ce serait souder deux contextes bornés au niveau du schéma et interdire de
 * les séparer plus tard. L'intégrité est tenue par le domaine.
 */
final class Version20260905113301 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Comptes, organisations et appartenances';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organization_memberships (member_id UUID NOT NULL, role VARCHAR(20) NOT NULL, joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, organization_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B606E30D32C8A3DE ON organization_memberships (organization_id)');
        $this->addSql('CREATE INDEX idx_membership_member ON organization_memberships (member_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_membership_org_member ON organization_memberships (organization_id, member_id)');
        $this->addSql('CREATE TABLE organizations (name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL, is_personal BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_organizations_slug ON organizations (slug)');
        $this->addSql('CREATE TABLE users (email VARCHAR(180) NOT NULL, password_hash VARCHAR(255) NOT NULL, registered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');
        $this->addSql('ALTER TABLE organization_memberships ADD CONSTRAINT FK_B606E30D32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_memberships DROP CONSTRAINT FK_B606E30D32C8A3DE');
        $this->addSql('DROP TABLE organization_memberships');
        $this->addSql('DROP TABLE organizations');
        $this->addSql('DROP TABLE users');
    }
}
