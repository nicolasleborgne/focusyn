<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Notes et rattachements aux obsessions.
 *
 * `notes.organization_id` est le discriminant de cloisonnement, sans clé
 * étrangère vers `organizations` : Notebook et Organization sont deux contextes
 * bornés distincts.
 */
final class Version20260905163814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notes et obsessions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE note_obsessions (name VARCHAR(60) NOT NULL, slug VARCHAR(60) NOT NULL, note_id UUID NOT NULL, PRIMARY KEY (slug, note_id))');
        $this->addSql('CREATE INDEX IDX_2E16F5E326ED0855 ON note_obsessions (note_id)');
        $this->addSql('CREATE INDEX idx_note_obsessions_slug ON note_obsessions (slug)');
        $this->addSql('CREATE TABLE notes (organization_id UUID NOT NULL, author_id UUID NOT NULL, title VARCHAR(200) NOT NULL, body TEXT NOT NULL, written_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_notes_org_updated ON notes (organization_id, updated_at)');
        $this->addSql('ALTER TABLE note_obsessions ADD CONSTRAINT FK_2E16F5E326ED0855 FOREIGN KEY (note_id) REFERENCES notes (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE note_obsessions DROP CONSTRAINT FK_2E16F5E326ED0855');
        $this->addSql('DROP TABLE note_obsessions');
        $this->addSql('DROP TABLE notes');
    }
}
