<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260906124356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE subscriptions (organization_id UUID NOT NULL, plan VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, trial_ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, period_ends_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, seats INT NOT NULL, customer_reference VARCHAR(80) DEFAULT NULL, subscription_reference VARCHAR(80) DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4778A0132C8A3DE ON subscriptions (organization_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4778A019C1C8BAF ON subscriptions (subscription_reference)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE subscriptions');
    }
}
