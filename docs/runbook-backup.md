# Runbook — Sauvegardes & restauration PostgreSQL

> Référence faisant foi : [cahier des charges](cdc_friday_duck.md) (§37.4).
> Installation & wiring : [`ops/backup/README.md`](../ops/backup/README.md).

Sauvegardes chiffrées off-site (restic → Hetzner Storage Box), au niveau hôte
(timers systemd), **indépendantes** des redéploiements. Ce runbook couvre la
**restauration** — la partie qui compte le jour où ça brûle.

## ⚠️ À lire en premier — RESTIC_PASSWORD

`RESTIC_PASSWORD` (dans `/etc/canard-backup/backup.env`) est **la seule chose qui,
perdue, rend TOUS les backups irrécupérables** : du chiffré illisible, aucune
récupération possible. Il **doit** vivre AUSSI hors-serveur, dans un gestionnaire
de mots de passe. Sans lui, ce runbook ne sert à rien.

## Inventaire & contrôles courants

```bash
# Charger les secrets restic dans le shell courant (root).
set -a; . /etc/canard-backup/backup.env; set +a

restic snapshots                 # lister les sauvegardes (horodatées)
restic stats latest              # taille de la dernière
systemctl list-timers 'canard-*' # prochains déclenchements
journalctl -u canard-backup.service -n 50 --no-pager   # dernier run
```

## Restauration vers une cible JETABLE (jamais la prod)

C'est le chemin **testé et automatisable**. Il restaure le dump le plus récent
dans un PostgreSQL éphémère, le vérifie, puis le détruit — la prod n'est jamais
touchée.

```bash
sudo /opt/canard-backup/restore-test.sh
```

Manuellement, étape par étape (équivalent du script) :

```bash
set -a; . /etc/canard-backup/backup.env; set +a

# 1. PostgreSQL throwaway, supprimé en sortie.
cid="$(docker run -d --rm \
  -e POSTGRES_USER=scratch -e POSTGRES_PASSWORD=scratch -e POSTGRES_DB=scratch \
  postgres:17-alpine)"

# 2. Restaurer le dump le plus récent.
restic dump latest canard-db.sql \
  | docker exec -i "$cid" psql -v ON_ERROR_STOP=1 -U scratch -d scratch

# 3. Vérifier (comptage sur une table connue — ici, tables du schéma public).
docker exec "$cid" psql -tA -U scratch -d scratch \
  -c "SELECT count(*) FROM information_schema.tables WHERE table_schema='public'"

# 4. Détruire la cible.
docker rm -f "$cid"
```

Restaurer un snapshot **précis** (pas le dernier) : `restic snapshots` pour
l'ID, puis `restic dump <id> canard-db.sql | …`.

## Restauration RÉELLE vers la prod (incident grave, geste délibéré)

> ⚠️ Écrase les données de prod. À ne faire qu'en connaissance de cause, après
> avoir confirmé qu'un restore-test passe. **Jamais un vendredi sans raison.**

```bash
set -a; . /etc/canard-backup/backup.env; set +a

# Filet : dump de l'état actuel AVANT d'écraser (au cas où).
docker exec duck_db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB"' \
  > /root/pre-restore-$(date -u +%Y%m%dT%H%M%SZ).sql

# Restauration (le dump contient DROP ... IF EXISTS : il se réécrase proprement).
restic dump latest canard-db.sql \
  | docker exec -i duck_db sh -c 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"'

# Contrôle applicatif.
./scripts/smoke-test.sh https://tibec.labault.dev
```

À distinguer du **dump pré-migration** de `deploy.sh` (`backups/pre-migrate-*.sql`,
filet de rollback de déploiement local) : ici, c'est la sauvegarde off-site restic.

## Échecs & diagnostic

| Symptôme | Piste |
| --- | --- |
| `systemctl start canard-backup.service` échoue | `journalctl -u canard-backup.service` — souvent SSH (clé/known_hosts) ou `CHANGE_ME` resté dans `backup.env`. |
| « Dump SUSPECT : N octets < plancher » | Le garde-fou a fait son travail : pg_dump a produit un flux quasi vide (base down ? mauvais conteneur ?). Le snapshot bidon est supprimé. Vérifier `duck_db`. |
| `restic check` signale une corruption | Ne pas paniquer : les snapshots sains restent restaurables. Cf. `restic check` puis `restic prune`. Relancer un backup frais. |
| Aucune alerte reçue alors que le run a raté | Vérifier `HEALTHCHECK_PING_URL` / `ALERT_WEBHOOK` dans `backup.env` et `journalctl -u 'canard-backup-alert@*'`. |
| Le timer ne s'est jamais lancé | Le dead-man's-switch healthchecks.io alerte sur absence de ping ; `systemctl list-timers 'canard-*'` pour l'état. |

## Test de restauration automatisé (mensuel, optionnel)

Livré mais **désactivé** par défaut. Pour l'allumer :

```bash
systemctl enable --now canard-restore-test.timer
systemctl list-timers 'canard-*'
```

Il restaure vers une cible jetable, vérifie le comptage, et alerte si ça rate —
la preuve récurrente que les backups sont **restaurables**, pas juste présents.
