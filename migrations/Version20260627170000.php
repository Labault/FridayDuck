<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

/**
 * Phase 5 — conseil catastrophique + réactions.
 *
 * Crée advice (catalogue §23.7, seedé à la main §11.1/§11.4 — pas d'IA §11.5) et
 * advice_reaction (§23.8, UNIQUE(edition, visitor), MUTABLE avec updated_at).
 * Ajoute à friday_edition le conseil du jour figé (advice_id), la séquence
 * d'advice (DISTINCTE) et les 3 compteurs de réactions dénormalisés.
 */
final class Version20260627170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create advice, advice_reaction; add advice columns to friday_edition; seed advice catalogue.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE advice (id VARCHAR(26) NOT NULL, text TEXT NOT NULL, slug VARCHAR(128) NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_advice_slug ON advice (slug)');

        $this->addSql('CREATE TABLE advice_reaction (id VARCHAR(26) NOT NULL, friday_edition_id VARCHAR(26) NOT NULL, visitor_id VARCHAR(26) NOT NULL, reaction VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_advice_reaction_edition_visitor ON advice_reaction (friday_edition_id, visitor_id)');

        $this->addSql('ALTER TABLE friday_edition ADD advice_id VARCHAR(26) DEFAULT NULL');
        foreach (['advice_sequence', 'concerning_count', 'already_done_count', 'taking_notes_count'] as $column) {
            $this->addSql(\sprintf('ALTER TABLE friday_edition ADD %s INT NOT NULL DEFAULT 0', $column));
            $this->addSql(\sprintf('ALTER TABLE friday_edition ALTER %s DROP DEFAULT', $column));
        }

        $this->seedCatalogue();
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE friday_edition DROP taking_notes_count');
        $this->addSql('ALTER TABLE friday_edition DROP already_done_count');
        $this->addSql('ALTER TABLE friday_edition DROP concerning_count');
        $this->addSql('ALTER TABLE friday_edition DROP advice_sequence');
        $this->addSql('ALTER TABLE friday_edition DROP advice_id');
        $this->addSql('DROP TABLE advice_reaction');
        $this->addSql('DROP TABLE advice');
    }

    private function seedCatalogue(): void
    {
        foreach ($this->catalogue() as [$slug, $text]) {
            $this->addSql(
                'INSERT INTO advice (id, text, slug, active, created_at) VALUES (?, ?, ?, true, ?)',
                [(new Ulid())->toBase32(), $text, $slug, '2026-06-27 00:00:00'],
            );
        }
    }

    /**
     * Pool éditorial (§11.1 référence + §11.4 exemples), écrit à la main (§11.5).
     *
     * @return list<array{0: string, 1: string}>
     */
    private function catalogue(): array
    {
        return [
            ['deploy-friday-evening', 'Déploie à 16 h 58. Ça crée des souvenirs avec l’équipe.'],
            ['ci-is-wrong', 'Si les tests échouent uniquement en CI, c’est probablement la CI qui a tort.'],
            ['bug-fixed-by-waiting', 'Un bug qui ne se reproduit plus depuis dix minutes est techniquement corrigé.'],
            ['blame-the-cache', 'Quand personne ne comprend le problème, accuse le cache.'],
            ['watch-all-weekend', 'Ne corrige jamais un vendredi ce que tu peux surveiller tout le week-end.'],
            ['update-deps-before-demo', 'Le meilleur moment pour mettre à jour toutes les dépendances, c’est juste avant la démonstration.'],
            ['no-console-no-errors', 'Tant que personne n’ouvre la console, il n’y a aucune erreur JavaScript.'],
            ['temporary-filename', 'Mets « temporaire » dans le nom du fichier. Personne n’osera le supprimer.'],
        ];
    }
}
