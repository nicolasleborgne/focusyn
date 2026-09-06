<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260906074150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE calendar_feeds (organization_id UUID NOT NULL, token VARCHAR(43) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, rotated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_367DF64132C8A3DE ON calendar_feeds (organization_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_367DF6415F37A13B ON calendar_feeds (token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE calendar_feeds');
    }
}
