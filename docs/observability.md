# Observabilité

> Stub — à compléter en Phase 7. Référence faisant foi :
> [cahier des charges](cdc_friday_duck.md) (§26).

## Objectif

Diagnostiquer : énergie incohérente, café refusé, vote non clôturé, accessoire non
appliqué, fuite mémoire worker, latence, événement Mercure non publié, séquence
visuelle non initialisée (§26.1).

## Pile

OpenTelemetry → Collector / Grafana Alloy → Tempo (traces), Loki (logs),
métriques → dashboards et alertes Grafana (§21, §26).

## Spans métier (§26.2)

`friday.current.resolve`, `coffee.contribution.validate`,
`coffee.contribution.persist`, `energy.recalculate`, `accessory.vote.close`,
`mercure.update.publish`, …

## Métriques (§26.3–§26.5)

- **Techniques :** durée/nombre de requêtes HTTP, erreurs, durée DB, Messenger
  (traités/échoués), mémoire worker, publications Mercure.
- **Métier :** `duck.energy`, `duck.coffee.total`, `duck.overcaffeination.total`,
  `duck.accessory.winner`, `duck.friday.unique_visitors`, …
- **Front (minimal) :** init Theatre.js, état connexion Mercure, cibles SVG
  manquantes — sans donnée personnelle inutile.

## Alertes (§26.7)

Critique : « Nous sommes vendredi, mais le canard est DORMANT. » Énergie hors
`0–100`. Vote ouvert après l'heure de clôture. Taux d'échec Mercure / Theatre.js.
Mémoire worker en hausse durable.

## À compléter

- [ ] Configuration du Collector et des exporteurs
- [ ] Définition des dashboards Grafana (§37.4)
- [ ] Règles d'alerte (seuils)
