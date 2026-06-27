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

## File d'échec Messenger (§25.4)

- Inspecter : `bin/console messenger:failed:show`
- Rejouer : `bin/console messenger:failed:retry`
- Les clés d'idempotence (§25.3) évitent les doublons lors d'un rejeu.

## À compléter

- [ ] Procédures détaillées de rejeu et de rollback
- [ ] Liens vers dashboards et alertes Grafana
- [ ] Sauvegardes et restauration PostgreSQL
