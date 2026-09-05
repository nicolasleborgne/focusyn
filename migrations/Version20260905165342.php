<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Listes de tâches et tâches.
 *
 * `task_lists.organization_id` est le discriminant de cloisonnement, sans clé
 * étrangère : Task et Organization sont deux contextes bornés distincts.
 */
final class Version20260905165342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Listes de tâches';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task_items (text VARCHAR(500) NOT NULL, position INT NOT NULL, is_done BOOLEAN NOT NULL, added_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, task_list_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_26BAF26D224F3C61 ON task_items (task_list_id)');
        $this->addSql('CREATE INDEX idx_task_items_list_position ON task_items (task_list_id, position)');
        $this->addSql('CREATE TABLE task_lists (organization_id UUID NOT NULL, name VARCHAR(120) NOT NULL, opened_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_task_lists_org_opened ON task_lists (organization_id, opened_at)');
        $this->addSql('ALTER TABLE task_items ADD CONSTRAINT FK_26BAF26D224F3C61 FOREIGN KEY (task_list_id) REFERENCES task_lists (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_items DROP CONSTRAINT FK_26BAF26D224F3C61');
        $this->addSql('DROP TABLE task_items');
        $this->addSql('DROP TABLE task_lists');
    }
}
