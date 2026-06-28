<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

/**
 * Phase 4a — vote d'accessoire.
 *
 * Crée accessory (catalogue §23.4, seedé avec le pool §10.2),
 * friday_accessory_option (§23.5, UNIQUE(edition, accessory) pour la course de
 * création des 3 options) et accessory_vote (§23.6, UNIQUE(edition, visitor) :
 * un seul vote par visiteur). Ajoute à friday_edition la séquence de résultats
 * (anti-régression, DISTINCTE d'energy_version) et le code du gagnant (figé
 * après 14:00). Référence par identité (pas de clé étrangère ORM).
 */
final class Version20260627160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create accessory, friday_accessory_option, accessory_vote; add vote columns to friday_edition; seed catalogue.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE accessory (id VARCHAR(26) NOT NULL, code VARCHAR(64) NOT NULL, label VARCHAR(128) NOT NULL, description TEXT NOT NULL, slot VARCHAR(16) NOT NULL, svg_group_id VARCHAR(64) NOT NULL, entrance_sequence VARCHAR(64) NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_accessory_code ON accessory (code)');

        $this->addSql('CREATE TABLE friday_accessory_option (id VARCHAR(26) NOT NULL, friday_edition_id VARCHAR(26) NOT NULL, accessory_id VARCHAR(26) NOT NULL, display_order INT NOT NULL, vote_count INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_friday_accessory_option_edition_accessory ON friday_accessory_option (friday_edition_id, accessory_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_friday_accessory_option_edition_order ON friday_accessory_option (friday_edition_id, display_order)');

        $this->addSql('CREATE TABLE accessory_vote (id VARCHAR(26) NOT NULL, friday_edition_id VARCHAR(26) NOT NULL, visitor_id VARCHAR(26) NOT NULL, accessory_id VARCHAR(26) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_accessory_vote_edition_visitor ON accessory_vote (friday_edition_id, visitor_id)');

        // Colonnes vote sur l'édition. DEFAULT transitoire pour backfill des lignes
        // existantes, puis retiré (le mapping insère toujours la valeur explicitement).
        $this->addSql('ALTER TABLE friday_edition ADD results_sequence INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE friday_edition ALTER results_sequence DROP DEFAULT');
        $this->addSql('ALTER TABLE friday_edition ADD winner_accessory_code VARCHAR(64) DEFAULT NULL');

        $this->seedCatalogue();
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE friday_edition DROP winner_accessory_code');
        $this->addSql('ALTER TABLE friday_edition DROP results_sequence');
        $this->addSql('DROP TABLE accessory_vote');
        $this->addSql('DROP TABLE friday_accessory_option');
        $this->addSql('DROP TABLE accessory');
    }

    private function seedCatalogue(): void
    {
        foreach ($this->catalogue() as [$code, $label, $description, $slot]) {
            $dashed = str_replace('_', '-', $code);
            $this->addSql(
                'INSERT INTO accessory (id, code, label, description, slot, svg_group_id, entrance_sequence, active, created_at)'
                .' VALUES (?, ?, ?, ?, ?, ?, ?, true, ?)',
                [
                    (new Ulid())->toBase32(),
                    $code,
                    $label,
                    $description,
                    $slot,
                    'accessory-'.$dashed,
                    'reveal-'.$dashed,
                    '2026-06-27 00:00:00',
                ],
            );
        }
    }

    /**
     * Pool d'accessoires (§10.2). svg_group_id/entrance_sequence pointent vers des
     * groupes/séquences dessinés en 4b — la métadonnée suffit ici.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function catalogue(): array
    {
        return [
            ['cto_glasses', 'Lunettes de soleil de CTO', 'Pour ne pas voir les incidents de production.', 'head'],
            ['too_serious_tie', 'Cravate beaucoup trop sérieuse', 'Un sérieux inversement proportionnel à la journée.', 'body'],
            ['production_cape', 'Cape de production', 'Ne confère aucun pouvoir, sauf celui de tomber en panne.', 'body'],
            ['works_on_my_machine_helmet', 'Casque « Ça marche chez moi »', 'Protège des reproches en réunion de crise.', 'head'],
            ['senior_since_tuesday_badge', 'Badge « Senior depuis mardi »', 'Une séniorité acquise en moins d’une semaine.', 'body'],
            ['kubernetes_beanie', 'Bonnet Kubernetes', 'Tient chaud et orchestre les pensées.', 'head'],
            ['oncall_vest', 'Gilet d’astreinte', 'Réfléchissant, pour être vu sonner à 3 h du matin.', 'body'],
            ['tiny_extinguisher', 'Petit extincteur', 'Pour les incendies métaphoriques du vendredi.', 'hand'],
            ['meeting_noise_cancelling_headset', 'Casque antibruit de réunion', 'Annule le bruit, pas les décisions absurdes.', 'head'],
            ['friday_in_prod_scarf', 'Écharpe « Vendredi en prod »', 'Se porte avec un mélange de fierté et de regret.', 'body'],
        ];
    }
}
