<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les routines : ce qu'on refait, et la trace de ce qui a été fait.
 */
final class Version20260906150200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Routines, leurs lignes et leurs cochages par période.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE routine_items (text VARCHAR(240) NOT NULL, position INT NOT NULL, days JSON NOT NULL, rank INT DEFAULT NULL, added_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, routine_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C906E521F27A94C7 ON routine_items (routine_id)');
        $this->addSql('CREATE TABLE routine_ticks (item_id UUID NOT NULL, period VARCHAR(12) NOT NULL, ticked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, routine_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A1D2E264F27A94C7 ON routine_ticks (routine_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_routine_ticks_item_period ON routine_ticks (item_id, period)');
        $this->addSql('CREATE TABLE routines (organization_id UUID NOT NULL, name VARCHAR(80) NOT NULL, cadence VARCHAR(10) NOT NULL, obsession VARCHAR(60) DEFAULT NULL, opened_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_routines_org_opened ON routines (organization_id, opened_at)');
        $this->addSql('ALTER TABLE routine_items ADD CONSTRAINT FK_C906E521F27A94C7 FOREIGN KEY (routine_id) REFERENCES routines (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE routine_ticks ADD CONSTRAINT FK_A1D2E264F27A94C7 FOREIGN KEY (routine_id) REFERENCES routines (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE routine_items DROP CONSTRAINT FK_C906E521F27A94C7');
        $this->addSql('ALTER TABLE routine_ticks DROP CONSTRAINT FK_A1D2E264F27A94C7');
        $this->addSql('DROP TABLE routine_items');
        $this->addSql('DROP TABLE routine_ticks');
        $this->addSql('DROP TABLE routines');
    }
}
