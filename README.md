# Le Canard du Vendredi 🦆☕

> Chaque vendredi, Internet tente de rendre un canard suffisamment caféiné pour
> travailler, choisit comment l'habiller et reçoit en échange un conseil qu'il ne
> faut surtout pas suivre.

Application web expérimentale, humoristique et open source. Le canard dort du
samedi au jeudi ; le vendredi, les visiteurs lui servent collectivement du café
(jauge `0→100`), votent pour son accessoire et réagissent à un conseil
professionnel catastrophique — le tout synchronisé en temps réel.

**Principe directeur : l'humour est dans le produit, la rigueur est dans le code.**

## Statut

🏗️ **Fondations posées (Phase 0/1).** Le dépôt contient le squelette Symfony, la
chaîne qualité et l'architecture en couches. Le domaine métier n'est pas encore
implémenté — voir la [feuille de route](docs/cdc_friday_duck.md) (§32).

## Stack

| Domaine          | Choix                                                   |
| ---------------- | ------------------------------------------------------- |
| Backend          | Symfony 7.4 (monolithe modulaire), PHP 8.4+             |
| Serveur          | FrankenPHP (mode worker — services *stateless*)         |
| Base de données  | PostgreSQL (via Doctrine ORM)                           |
| Rendu            | Twig (rendu serveur), SVG inline                        |
| Animation        | Theatre.js (`@theatre/core` en prod) piloté par Stimulus |
| Temps réel       | Mercure                                                 |
| Asynchrone       | Symfony Messenger + Scheduler                           |
| Identité         | Cookie anonyme, sans compte                             |
| Observabilité    | OpenTelemetry + pile Grafana                            |
| Tests navigateur | Playwright                                              |

Voir l'[ADR 0001](docs/adr/0001-svg-theatrejs-vs-rive.md) pour le choix
SVG + Theatre.js plutôt que Rive.

## Démarrage rapide

```sh
# 1. Dépendances PHP
composer install

# 2. Configuration locale
cp .env.example .env        # puis ajuster DATABASE_URL, MERCURE_*, etc.

# 3. Qualité (les outils viennent de la machine, pas du dépôt)
make qa                     # composer validate + CS-Fixer + PHPStan + tests
```

> Le serveur est l'**unique source de vérité** temporelle et métier. En
> développement, un vendredi peut être simulé via `APP_FAKE_NOW` (voir
> `.env.example` et §7.4). Cette variable doit être **neutralisée en production**.

## Documentation

| Document                                                 | Contenu                                            |
| -------------------------------------------------------- | -------------------------------------------------- |
| [Cahier des charges](docs/cdc_friday_duck.md)            | Référence fonctionnelle et technique complète      |
| [Produit](docs/product.md)                               | Concept, parcours, règles métier                   |
| [Architecture](docs/architecture.md)                     | Couches, frontières, pipeline backend              |
| [Système d'animation](docs/animation-system.md)          | Theatre.js, Stimulus, priorités d'animation        |
| [Conventions SVG](docs/svg-conventions.md)               | Structure, IDs, pivots, accessoires                |
| [Observabilité](docs/observability.md)                   | Traces, métriques, dashboards, alertes             |
| [Déploiement](docs/deployment.md)                        | Image FrankenPHP, CI/CD, rollback                  |
| [Runbook](docs/runbook.md)                               | Exploitation, incidents, rejeu Messenger           |
| [ADR](docs/adr/)                                         | Décisions d'architecture                           |
| [Contribuer](CONTRIBUTING.md)                            | Workflow, commits, hooks                           |

## Licence

Voir [LICENSE](LICENSE).
