<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 7a — propagation de trace bout-en-bout.
 *
 * Ajoute outbox.traceparent (W3C) : capturé dans la trace de la requête au moment
 * de l'écriture de la ligne, restauré par le relais à la publication → le span de
 * publish devient enfant de la trace d'origine, par-dessus la frontière async.
 * ADDITIF à 6b : aucune autre modification du schéma outbox.
 */
final class Version20260628120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add outbox.traceparent column (end-to-end trace propagation).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE outbox ADD traceparent VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE outbox DROP traceparent');
    }
}
