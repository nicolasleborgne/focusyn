<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La boîte de réception : ce qui est entré sans être encore rangé.
 */
final class Version20260906144446 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table des captures : la boîte de réception.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE captures (organization_id UUID NOT NULL, kind VARCHAR(10) NOT NULL, title VARCHAR(70) NOT NULL, body TEXT NOT NULL, source VARCHAR(20) NOT NULL, captured_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_captures_org_captured ON captures (organization_id, captured_at)');
    }

    public function down(Schema $schema): void
    {
        // Une boîte se vide : rien à conserver, tout ce qui méritait de
        // l'être est déjà devenu note ou tâche.
        $this->addSql('DROP TABLE captures');
    }
}
