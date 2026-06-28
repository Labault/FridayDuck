<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 6a — Scheduler proactif.
 *
 * Crée processed_message (dédup des annonces de cycle par clé, §25.3) et
 * weekly_report (bilan hebdomadaire figé, §12.5, UNIQUE(iso_week)). Aucune
 * nouvelle colonne sur friday_edition : le statut et les colonnes vote/conseil
 * existent déjà ; le Scheduler ne fait que les piloter via les résolveurs.
 */
final class Version20260627180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create processed_message and weekly_report tables (proactive scheduler).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE processed_message (message_key VARCHAR(128) NOT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (message_key))');

        $this->addSql('CREATE TABLE weekly_report (id VARCHAR(26) NOT NULL, friday_date DATE NOT NULL, iso_week VARCHAR(16) NOT NULL, peak_energy INT NOT NULL, coffee_count INT NOT NULL, overcaffeination_count INT NOT NULL, unique_visitors INT NOT NULL, winner_accessory_code VARCHAR(64) DEFAULT NULL, advice_slug VARCHAR(128) DEFAULT NULL, concerning_count INT NOT NULL, already_done_count INT NOT NULL, taking_notes_count INT NOT NULL, generated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_weekly_report_iso_week ON weekly_report (iso_week)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE weekly_report');
        $this->addSql('DROP TABLE processed_message');
    }
}
