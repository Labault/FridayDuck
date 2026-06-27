<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 2a-ii — mécanique café.
 *
 * Crée coffee_contribution (§23.3). UNIQUE(idempotency_key) garantit qu'un
 * double-clic / rejeu réseau ne compte jamais deux cafés (§8.6) ;
 * INDEX(friday_edition_id, visitor_id) sert le comptage de quota (§8.2).
 * Référence édition/visiteur par identité (intégrité côté application).
 */
final class Version20260627151603 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create coffee_contribution table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE coffee_contribution (id VARCHAR(26) NOT NULL, friday_edition_id VARCHAR(26) NOT NULL, visitor_id VARCHAR(26) NOT NULL, idempotency_key VARCHAR(255) NOT NULL, client_action_id VARCHAR(128) NOT NULL, energy_before INT NOT NULL, energy_after INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_coffee_contribution_idempotency ON coffee_contribution (idempotency_key)');
        $this->addSql('CREATE INDEX idx_coffee_contribution_edition_visitor ON coffee_contribution (friday_edition_id, visitor_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE coffee_contribution');
    }
}
