<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 2a-i — persistance et identité.
 *
 * Crée friday_edition (§23.1), anonymous_visitor (§23.2) et friday_visit
 * (§23.9), avec leurs contraintes d'UNICITÉ (idempotence : résoudre-ou-créer,
 * hash de visiteur, une visite par édition). Aucune colonne vote/conseil/gagnant
 * ni énergie au-delà des compteurs : elles viendront avec leurs phases.
 *
 * Les agrégats sont référencés PAR IDENTITÉ (friday_visit porte les colonnes
 * friday_edition_id / visitor_id sans clé étrangère ORM) : frontières d'agrégat
 * indépendantes, intégrité assurée côté application (on ne trace une visite que
 * pour une édition et un visiteur déjà résolus). Le schéma reste ainsi aligné
 * sur le mapping (diffs stables).
 */
final class Version20260627144037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create friday_edition, anonymous_visitor and friday_visit tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE anonymous_visitor (id VARCHAR(26) NOT NULL, anonymous_identifier_hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, total_visits INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_anonymous_visitor_hash ON anonymous_visitor (anonymous_identifier_hash)');

        $this->addSql('CREATE TABLE friday_edition (id VARCHAR(26) NOT NULL, friday_date DATE NOT NULL, timezone VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL, energy INT NOT NULL, energy_version INT NOT NULL, coffee_target INT NOT NULL, coffee_count INT NOT NULL, overcaffeination_count INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_friday_edition_date_tz ON friday_edition (friday_date, timezone)');

        $this->addSql('CREATE TABLE friday_visit (id VARCHAR(26) NOT NULL, friday_edition_id VARCHAR(26) NOT NULL, visitor_id VARCHAR(26) NOT NULL, first_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_friday_visit_edition_visitor ON friday_visit (friday_edition_id, visitor_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE friday_visit');
        $this->addSql('DROP TABLE friday_edition');
        $this->addSql('DROP TABLE anonymous_visitor');
    }
}
