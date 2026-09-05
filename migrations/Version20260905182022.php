<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Réglages d'affichage par compte.
 */
final class Version20260905182022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réglages d\'affichage par compte';
    }

    public function up(Schema $schema): void
    {
        // En trois temps, comme pour les codes de secours : une colonne
        // NOT NULL sans défaut échouerait sur les comptes existants. Ils
        // reçoivent les réglages d'origine du design.
        $this->addSql('ALTER TABLE users ADD display_preferences JSON DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE users SET display_preferences =
                '{"accent":"slate","proseFont":"serif","density":"comfortable","markOpacity":0.45,"previewPane":true}'
            SQL);
        $this->addSql('ALTER TABLE users ALTER COLUMN display_preferences SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP display_preferences');
    }
}
