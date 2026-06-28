# Runbook

> Stub — à compléter en Phase 6/9. Référence faisant foi :
> [cahier des charges](cdc_friday_duck.md) (§25.4, §26.7, §37.4).

## Philosophie

Le Scheduler déclenche des commandes mais **ne constitue pas l'unique preuve de
l'état** : une requête le vendredi peut créer ou réparer l'édition, un vote après
14 h est refusé par la règle métier même si la clôture a pris du retard, et un
rattrapage est toujours possible (§25.2).

## Incidents fréquents

### « Vendredi mais canard DORMANT » (critique)

1. Vérifier l'horloge serveur et le fuseau `Europe/Paris`.
2. Vérifier que `APP_FAKE_NOW` n'est pas positionné en production (§7.4).
3. Déclencher/rejouer la commande de préparation de l'édition.

### Vote toujours ouvert après 14 h

1. La règle métier doit déjà refuser les votes. Vérifier la commande de clôture.
2. Rejouer `accessory-close:<date>` (idempotent, §25.3).

### Mémoire worker en hausse

1. Vérifier l'absence d'état partagé entre requêtes (services *stateless*).
2. Recycler les workers FrankenPHP.

## Cycle proactif (Phase 6a, §25.1)

```bash
# Worker : génère les messages récurrents du cycle + traitement async.
bin/console messenger:consume scheduler_default async -vv
# Préparer les tables de transport Doctrine (une fois).
bin/console messenger:setup-transports
```

Étapes (heure murale Europe/Paris) : jeudi 23:55 préparer ; vendredi 00:00 ouvrir ;
14:00 clore le vote ; 14:01 publier le gagnant ; 23:55 préparer le bilan ;
samedi 00:00 fermer ; 00:05 générer le bilan.

## Rattrapage après un Scheduler indisponible (§25.2, invariant D)

```bash
bin/console app:friday:repair 2026-07-03
```

Amène l'édition à l'état correct selon l'horloge (prépare ; clôt le vote si après
14:00 ; ferme si après samedi minuit) et émet les annonces MANQUANTES une fois.
Sûr à rejouer — il invoque le MÊME aiguilleur de cycle que le Scheduler.

## File d'échec Messenger (§25.4)

- Inspecter : `bin/console messenger:failed:show` puis `… <id> -vv` (cause).
- Rejouer : `bin/console messenger:failed:retry` — écarter : `messenger:failed:remove <id>`.
- **Éviter un doublon au rejeu** : les annonces de cycle sont dédupliquées par clé
  (`processed_message` : `friday-open:<date>`, `accessory-winner:<date>`,
  `friday-close:<date>`) et le bilan par `UNIQUE(iso_week)`. Un message rejoué ne
  ré-applique donc rien.

## À compléter

- [ ] Procédures détaillées de rejeu et de rollback
- [ ] Liens vers dashboards et alertes Grafana
- [ ] Sauvegardes et restauration PostgreSQL
