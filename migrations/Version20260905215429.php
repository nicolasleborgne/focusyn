<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Choix de confidentialité, par personne.
 *
 * Pas de colonne organization_id : ces choix suivent la personne, pas
 * l'organisation dans laquelle elle travaille.
 */
final class Version20260905215429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Choix de confidentialité';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE privacy_choices (retention VARCHAR(30) NOT NULL, granted_consents JSON NOT NULL, opened_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, subject_id UUID NOT NULL, PRIMARY KEY (subject_id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE privacy_choices');
    }
}
