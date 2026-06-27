# Architecture

> Stub — à compléter au fil des phases. Référence faisant foi :
> [cahier des charges](cdc_friday_duck.md) (§21–§25, §30).

## Vue d'ensemble

Monolithe modulaire Symfony servi par FrankenPHP (mode worker), PostgreSQL,
Mercure pour le temps réel, Messenger/Scheduler pour l'asynchrone. Schéma global
en §21.

## Exécution : FrankenPHP en mode worker (§22.2)

L'application est servie par **FrankenPHP (Caddy + PHP embarqué)** en **mode
worker**. Le runtime FrankenPHP est **natif** depuis `symfony/runtime` ≥ 7.4
(`Symfony\Component\Runtime\Runner\FrankenPhpWorkerRunner`) : `public/index.php`
le détecte automatiquement. **Aucun `APP_RUNTIME` ni paquet
`runtime/frankenphp-symfony` n'est nécessaire** — ce paquet ne servait que pour
Symfony < 7.4. Le worker est déclaré dans `frankenphp/Caddyfile`
(`php { worker { file ./public/index.php } }`).

### Le piège du mode worker : services *stateless* (§22.2)

> En mode worker, **le kernel Symfony est amorcé une seule fois puis réutilisé
> pour des milliers de requêtes successives**, dans le même process PHP. Les
> services sont donc instanciés une fois et **gardés en mémoire entre les
> requêtes**.

Conséquence non négociable : **aucun service ne doit retenir d'état lié à un
visiteur ou à une requête** (identité du visiteur, énergie courante, vendredi
résolu, panier de vote…). Un champ d'objet rempli pendant la requête A serait
encore là pendant la requête B d'un **autre** visiteur — fuite d'état, et bug de
sécurité.

Règles pratiques :

- Services `final readonly`, dépendances injectées, **aucune propriété mutable**
  portant de l'état requête/visiteur.
- L'état requête transite par les arguments (Request, DTO, Message), jamais par
  des attributs de service partagés.
- Le temps passe par `ClockInterface` (jamais `new \DateTimeImmutable()`), ce qui
  évite aussi de figer « maintenant » dans un singleton (§7.3).
- En cas de doute, traiter toute propriété non-`readonly` d'un service comme un
  bug potentiel (§34).

En **dev**, le worker tourne en mode `watch` (`FRANKENPHP_WORKER_CONFIG=watch`) :
il redémarre à chaque changement de fichier, ce qui masque commodément certaines
fuites d'état — raison de plus pour les tester explicitement plutôt que de s'y
fier.

### Hub Mercure co-localisé (§21)

Le hub Mercure n'est **pas** un conteneur séparé : c'est le **module Mercure
intégré à Caddy**, configuré par la directive `mercure` du `frankenphp/Caddyfile`
(publisher protégé par JWT, abonnés anonymes autorisés — cookie anonyme §5). Le
diagramme §21 le dessine à part pour la lisibilité, mais il vit dans le même
process FrankenPHP. L'app publie en interne (`MERCURE_URL`, vhost `app:80`), le
navigateur s'abonne via l'URL publique (`MERCURE_PUBLIC_URL`).

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

## Domaine temporel (Phase 1, §7)

Premier morceau de métier — **pur, calculé, sans persistance**.

- **`Domain\Shared\Clock\ClockInterface`** (`now(): \DateTimeImmutable`) : unique
  source de vérité temporelle (§7.3). Implémentations dans `Infrastructure\Clock` :
  - `SystemClock` — **seul** endroit qui lit l'horloge réelle ;
  - `FrozenClock` — instant figé (tests) ;
  - `ConfigurableClock` — lit `APP_FAKE_NOW` (dev/préprod).
- **`Domain\Friday\FridayCalendar`** : à partir de `clock->now()` ramené au fuseau
  métier, calcule `FridayState` (`active`, vendredi de l'édition, `FridayStatus`
  AWAKE/DORMANT). Bascule à `00:00:00` Europe/Paris (§7.2). Le fuseau est injecté
  en un point unique (`app.business_timezone`).
- **`APP_FAKE_NOW`** (§7.4) : la liaison `ClockInterface` ne devient
  `ConfigurableClock` qu'`when@dev/@preprod/@test` ; en **prod** elle reste
  `SystemClock` → la variable est **neutralisée par construction**. Garde-fou
  défensif supplémentaire (`FakeClockProductionGuard`) + visibilité dans la barre
  de debug (`BusinessClockDataCollector`) + journalisation.
- **`GET /api/friday/current`** (Presentation → Application → Domaine) : réponse
  réduite `{ active, date, timezone, status }`, calculée, jamais persistée
  (sous-ensemble du §24.1 ; energy/vote/advice/visitor en Phase 2+).

## Modèle de données

Tables et contraintes d'unicité (idempotence) en §23.

## À compléter

- [ ] Diagrammes C4 / séquence (création du café, clôture du vote)
- [ ] Détail des bus Messenger et des transports
- [ ] Stratégie de migration Doctrine
