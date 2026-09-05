<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fiches éditoriales des obsessions.
 *
 * Facultatives : une obsession existe dès qu'une note la mentionne, cette table
 * ne contient que ce qu'on a choisi d'en écrire.
 */
final class Version20260905181208 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fiches éditoriales des obsessions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE obsessions (organization_id UUID NOT NULL, name VARCHAR(60) NOT NULL, slug VARCHAR(60) NOT NULL, blurb VARCHAR(220) DEFAULT NULL, points JSON NOT NULL, described_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_obsessions_org_slug ON obsessions (organization_id, slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE obsessions');
    }
}
