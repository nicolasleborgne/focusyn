<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ouvre un abonnement aux organisations créées avant que Billing n'existe.
 *
 * Sans cela, elles n'auraient aucun droit du tout : `entitledPlan()` ne peut
 * répondre que si un abonnement existe, et l'écran d'abonnement répondrait 404.
 *
 * L'essai est daté depuis la **création de l'organisation**, pas depuis
 * aujourd'hui : offrir quatorze jours neufs à des comptes déjà anciens serait
 * un cadeau involontaire, et fausserait la première facturation.
 */
final class Version20260906125654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Abonnement d\'essai pour les organisations antérieures à Billing.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO subscriptions (id, organization_id, plan, status, trial_ends_at, period_ends_at, seats, customer_reference, subscription_reference)
            SELECT gen_random_uuid(), o.id, 'free', 'trialing', o.created_at + INTERVAL '14 days', NULL, 1, NULL, NULL
            FROM organizations o
            WHERE NOT EXISTS (SELECT 1 FROM subscriptions s WHERE s.organization_id = o.id)
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Rien : on ne saurait plus distinguer les abonnements comblés ici de
        // ceux ouverts normalement depuis.
    }
}
