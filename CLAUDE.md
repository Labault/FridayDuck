# CLAUDE.md

Contexte projet pour Claude Code — **Le Canard du Vendredi**.

> **Principe directeur : l'humour est dans le produit, la rigueur est dans le
> code.** Le produit est volontairement inutile ; son exécution doit être
> sérieuse.

La référence fonctionnelle et technique complète est
[`docs/cdc_friday_duck.md`](docs/cdc_friday_duck.md). Les sections (§N) ci-dessous
y renvoient.

## Stack

- **Langage :** PHP 8.4+ — **Framework :** Symfony 7.4 (monolithe modulaire)
- **Serveur :** FrankenPHP (mode worker) — **Datastore :** PostgreSQL (Doctrine ORM)
- **Front :** Twig + SVG inline, Theatre.js (`@theatre/core`) piloté par Stimulus
- **Temps réel :** Mercure — **Async :** Messenger + Scheduler — **IDs :** symfony/uid

## Invariants non négociables

Chaque session doit les respecter. Ce ne sont pas des préférences.

1. **Le serveur est l'unique source de vérité — temporelle ET métier.** Le
   navigateur ne décide de **rien** : ni du vendredi courant, ni de l'énergie, ni
   du quota de café, ni du gagnant du vote, ni du conseil actif. Stimulus,
   Theatre.js et le SVG n'ont aucune autorité (§7.1, §15.5, §27.3).

2. **Horloge injectable.** Le temps passe exclusivement par
   `App\Domain\Shared\Clock\ClockInterface` (`SystemClock` en prod, `FrozenClock`
   en test, `ConfigurableClock` en local via `APP_FAKE_NOW`).
   **INTERDICTION absolue d'appeler `new \DateTimeImmutable()` / `new \DateTime()`
   dans un service de Domaine** (§7.3).

3. **Fuseau métier : `Europe/Paris`.** Bascule vendredi/dormant à `00:00:00`
   (§7.2). `APP_FAKE_NOW` simule un vendredi en dev/préprod — **doit être
   neutralisé en production** (§7.4).

4. **Idempotence obligatoire** sur café, vote et réaction.
   - Café : clé `coffee:{visitor-id}:{friday}:{client-action-id}`, contrainte
     `UNIQUE(idempotency_key)` (§8.6, §23.3). Un double-clic / rejeu réseau ne
     compte **jamais** deux cafés.
   - Vote & réaction : `UNIQUE(friday_edition_id, visitor_id)` (§23.6, §23.8).
   - Messages planifiés : clés `friday-open:<date>`, `accessory-close:<date>`,
     `accessory-winner:<date>`, `friday-close:<date>`, `weekly-report:<ISO-week>`
     (§25.3). Le Scheduler déclenche mais ne fait pas foi : la règle métier
     revalide toujours (§25.2).

5. **Frontières de couches strictes** (§30) :
   - `Domain` = **PHP pur**. Aucun `use Symfony\...`, aucun `use Doctrine\...`,
     aucune fuite ORM/HTTP. Définit des **interfaces** (ports).
   - `Application` orchestre (commandes/requêtes/handlers) au-dessus du `Domain`.
   - `Infrastructure` implémente les ports (Doctrine sous `Persistence/`, Mercure,
     Messenger, horloge, OTel). **Aucune logique métier ici.**
   - `Presentation` (HTTP/Console) : points d'entrée fins.

6. **Services *stateless*.** Workers FrankenPHP : **aucun état visiteur partagé
   entre deux requêtes** dans un service. Toute fuite d'état est un bug (§22.2,
   §34).

7. **Qualité verte avant tout commit.** PHPStan **niveau 9** et PHP-CS-Fixer
   (`@Symfony`) doivent passer. Lancer `make qa` avant de committer.

## Commandes

Tout passe par `make` (les outils viennent de la machine, pas du dépôt) :

- `make qa` — toutes les vérifications qualité
- `make stan` — PHPStan (niveau 9) · `make cs` / `make cs-fix` — PHP-CS-Fixer
- `make rector` / `make rector-fix` — Rector · `make test` — tests
- `make lint` — tous les hooks pre-commit · `make fix` — auto-fixes
- `make hooks` — installer les hooks git (pre-commit + commit-msg)

Symfony : `php bin/console …` (ex. `cache:clear`, `lint:container`,
`debug:router`).

## Conventions

- **Commits :** Conventional Commits + Gitmoji optionnel
  (`✨ feat(scope): sujet`). Hook `commit-msg` (`scripts/lint-commit-msg.sh`).
- **Secrets :** jamais committés. `.env` est ignoré ; la référence committée est
  [`.env.example`](.env.example). `gitleaks` tourne en local et en CI.
- **Qualité :** la chaîne (pre-commit, PHPStan, CS-Fixer, Rector, CI) est déposée
  par [bootstrap-web-setup](https://github.com/Labault/bootstrap-web-setup). Pour
  la mettre à jour : `bootstrap reconcile`.
- **Theatre.js Studio** ne doit **jamais** être livré dans le bundle public de
  production (§15.4, §31.2).

## Statut

Fondations posées (squelette Symfony + qualité + architecture en couches). Le
domaine métier n'est pas encore implémenté — voir la feuille de route (§32).
