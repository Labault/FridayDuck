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

**Prérequis :** Docker (via OrbStack, jamais Docker Desktop) pour faire tourner
l'app ; PHP 8.4+ et Composer sur la machine pour l'outillage qualité.

```sh
# 1. Configuration locale
cp .env.example .env        # ajuster DATABASE_URL, MERCURE_*, APP_FAKE_NOW… au besoin

# 2. Démarrer la stack (app FrankenPHP + PostgreSQL) en arrière-plan
make up                     # build + up + healthcheck bloquant

# 3. Outillage qualité (les outils viennent de la machine, pas du dépôt)
composer install            # nécessaire pour PHPStan / CS-Fixer / tests en local
make qa                     # lint + PHPStan (niveau 9) + tests
```

Une fois `make up` terminé, l'app répond sur **`https://localhost`** (certificat
auto-signé par FrankenPHP — à accepter une fois dans le navigateur).

### Ports & accès local

| Service                    | URL / port              | Quoi                                              |
| -------------------------- | ----------------------- | ------------------------------------------------- |
| **App (dev)**              | `https://localhost`     | l'app — FrankenPHP en mode worker (HTTP `80` redirige vers HTTPS `443`) |
| PostgreSQL                 | `localhost:5432`        | base de données                                   |
| E2E — `app-friday`         | `http://localhost:8081` | fixture de test, horloge **gelée un vendredi matin** |
| E2E — `app-afternoon`      | `http://localhost:8082` | fixture de test, vendredi après-midi              |
| E2E — `app-dormant`        | `http://localhost:8083` | fixture de test, jour dormant (le canard dort)    |

> ⚠️ **Piège :** `localhost:8081`–`8083` sont les instances de la **stack E2E**
> (`compose.e2e.yaml`), à horloge figée pour des tests déterministes — **pas** ta
> stack de dev. Elles sont buildées au lancement de la suite E2E et peuvent servir
> un ancien code. Pour voir tes changements, c'est **`https://localhost`**.

Les ports sont surchargeables via `HTTP_PORT` / `HTTPS_PORT` / `POSTGRES_PORT`
(voir `.env.example`).

### Commandes `make`

| Commande              | Effet                                                       |
| --------------------- | ----------------------------------------------------------- |
| `make up` / `make down` | Démarrer / arrêter la stack de dev (app + base)           |
| `make logs`           | Suivre les logs de l'app (`docker compose logs -f app`)     |
| `make sh`             | Ouvrir un shell dans le conteneur app                       |
| `make qa`             | Toutes les vérifs : `lint` + `stan` + `test`                |
| `make stan`           | PHPStan niveau 9                                            |
| `make cs` / `make cs-fix` | PHP-CS-Fixer (vérif / correction)                       |
| `make rector` / `make rector-fix` | Rector (aperçu / application)                   |
| `make fix`            | Auto-fix complet (CS-Fixer + Rector)                        |
| `make hooks`          | Installer les hooks git (pre-commit + commit-msg)           |

> Le serveur est l'**unique source de vérité** temporelle et métier. En
> développement, un vendredi peut être simulé via `APP_FAKE_NOW` (voir
> `.env.example` et §7.4). Cette variable doit être **neutralisée en production**.
>
> **Worker FrankenPHP :** le kernel (routeur compris) est chargé une fois au boot
> et gardé en mémoire. Après un changement de route ou de config compilée, un
> `make down && make up` est nécessaire pour que le worker reparte à neuf — éditer
> le code seul ne suffit pas.

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
