# Architecture

> Stub — à compléter au fil des phases. Référence faisant foi :
> [cahier des charges](cdc_friday_duck.md) (§21–§25, §30).

## Vue d'ensemble

Monolithe modulaire Symfony servi par FrankenPHP (mode worker), PostgreSQL,
Mercure pour le temps réel, Messenger/Scheduler pour l'asynchrone. Schéma global
en §21.

## Couches (§30)

Le code source suit une architecture en couches stricte sous `src/` :

| Couche           | Rôle                                                        | Dépendances autorisées          |
| ---------------- | ----------------------------------------------------------- | ------------------------------- |
| `Domain`         | Cœur métier : entités, objets-valeur, événements, ports     | **PHP pur uniquement**          |
| `Application`    | Cas d'usage : commandes, requêtes, handlers, vues           | `Domain`                        |
| `Infrastructure` | Adaptateurs : Doctrine, Mercure, Messenger, horloge, OTel   | `Domain`, `Application`, vendors |
| `Presentation`   | Points d'entrée : HTTP, Console                              | `Application`, `Domain`         |

### Frontières non négociables

- Le `Domain` ne contient **aucune** dépendance Symfony ni Doctrine.
- L'horloge passe par `App\Domain\Shared\Clock\ClockInterface` : interdiction
  d'appeler `new \DateTimeImmutable()` dans un service de domaine (§7.3).
- Doctrine vit dans `Infrastructure/Persistence` (mapping ORM, entités).
- Services *stateless* : aucun état visiteur partagé entre requêtes FrankenPHP
  (§22.2).

Voir aussi le [CLAUDE.md](../CLAUDE.md) qui encode ces invariants.

## Pipeline backend (§31.1)

`composer validate` → installation → formatage (PHP-CS-Fixer) → PHPStan
(niveau 9) → tests unitaires → tests d'intégration → audit → migrations de test.

Localement : `make qa`.

## Modèle de données

Tables et contraintes d'unicité (idempotence) en §23.

## À compléter

- [ ] Diagrammes C4 / séquence (création du café, clôture du vote)
- [ ] Détail des bus Messenger et des transports
- [ ] Stratégie de migration Doctrine
