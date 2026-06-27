# Déploiement

> Stub — à compléter en Phase 9. Référence faisant foi :
> [cahier des charges](cdc_friday_duck.md) (§31).

## CI/CD (§31)

- **Backend :** `composer validate`, install, formatage, PHPStan, tests
  unitaires/intégration, audit, migrations de test.
- **Front :** install, TypeScript, lint, tests, vérification import état
  Theatre.js, optimisation SVG, validation des IDs obligatoires, build,
  **contrôle de l'absence de `@theatre/studio`** dans le bundle public.
- **End-to-end :** Docker Compose, horloge simulée, Playwright, captures.

GitHub Actions : voir `.github/workflows/` (déposés par
[bootstrap-web-setup](https://github.com/Labault/bootstrap-web-setup)).

## Image de production (§31.4)

FrankenPHP, assets compilés, sans outils de dev superflus, version exposée,
health check. **`APP_FAKE_NOW` doit être neutralisé en production** (§7.4).

## Après déploiement (§31.5)

Smoke test, contrôle de l'état courant, endpoint santé, version annotée dans
Grafana, initialisation d'animation vérifiée, rollback disponible.

## À compléter

- [ ] Dockerfile FrankenPHP multi-étapes
- [ ] Docker Compose (dev + e2e)
- [ ] Procédure de rollback et sauvegardes (§37.4)
- [ ] Gestion des secrets (§27.6)
