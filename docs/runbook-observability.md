# Runbook — stack d'observabilité prod (Phase 9)

> Stack SÉPARÉ et long-lived sur le VPS, réseau Docker externe partagé
> `observability`. **Indépendant de push-to-deploy** : `deploy.sh` ne le touche
> pas. Accès UIs par **tunnel SSH uniquement** — seul Grafana publie un port, sur
> `127.0.0.1:3000`. Référence : [`docs/observability.md`](observability.md) (stack
> local prouvé), promu ici en prod.

## Composants

OTel Collector · Tempo (traces, rétention 14 j) · Prometheus (métriques, rétention
30 j) · Alertmanager → ntfy · Grafana. Pas de Loki en prod (logs → journald).

Le Collector collecte AUSSI les métriques **hôte** (`hostmetrics` : disque,
mémoire, CPU) et **conteneurs** (`docker_stats` : `container.uptime`) — d'où le
montage de `/` en `/hostfs` et du socket Docker, en lecture seule, sur le seul
Collector. Aucune alerte « app down / pas de trafic » : le canard dort du lundi au
jeudi par concept.

## Mise en service (une seule fois)

```bash
# 1. Réseau partagé d'ingestion (comme `web`). DOIT exister avant le prochain
#    deploy de l'app (compose.prod.yaml le référence en external).
docker network create observability

# 2. Secrets root-only (jamais committés).
cp observability/prod/obs.env.dist              observability/prod/obs.env
cp observability/prod/ntfy-alertmanager.scfg.dist observability/prod/ntfy-alertmanager.scfg
chmod 600 observability/prod/obs.env observability/prod/ntfy-alertmanager.scfg
#   → obs.env : GF_SECURITY_ADMIN_PASSWORD (openssl rand -base64 24)
#   → *.scfg  : topic ntfy (le MÊME que les backups) + access-token

# 3. Démarrer le stack.
make obs-prod-up        # = docker compose -f compose.observability.prod.yaml up -d --wait
```

L'app se repointe via `OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318`
dans `.env.prod.local` (cf. `.env.prod.local.dist`). Au prochain `make deploy`,
l'app/worker/relay joignent le réseau `observability` et émettent leur télémétrie.
L'export est **non bloquant** : si ce stack est arrêté, l'app boote quand même et
`/health/ready` répond 200.

## Convergence des alertes (une seule boîte ntfy)

- **Alertmanager → ntfy** : via le pont `ntfy-alertmanager` (webhook → topic).
- **Backups → ntfy** : les backups restic alertent via **healthchecks.io** (qui
  garde son dead-man's-switch : il alerte aussi si AUCUN ping n'arrive). Pour tout
  réunir au même endroit, ajouter dans le projet healthchecks.io une **intégration
  ntfy** pointant le **même topic**. `ops/backup/alert.sh` n'est pas modifié.

Résultat : Alertmanager (infra VPS) et healthchecks.io (backups) déversent dans le
même topic ntfy → une seule notification « le VPS a un souci » sur le téléphone.

## Ouvrir Grafana (tunnel SSH)

```bash
ssh -L 3000:localhost:3000 vps      # `vps` = ton alias SSH
# puis, en local : http://localhost:3000   (admin / mot de passe de obs.env)
```

Aucun autre service n'est joignable depuis l'hôte : Prometheus, Tempo,
Alertmanager et le Collector n'ont aucun port publié. Grafana les interroge en
interne sur le réseau `obs-internal`.

### Vérifier la surface réseau

```bash
ss -tlnp | grep -E ':(3000|9090|9093|4317|4318|8889|3200)'
# Attendu : SEULE la ligne 127.0.0.1:3000 (Grafana). Rien d'autre en écoute.
```

## Tester une alerte (bout en bout vers ntfy)

Deux options sans rien casser :

```bash
# A. Seuil disque bidon : abaisser temporairement le seuil à 1 % dans
#    observability/prod/prometheus-rules.infra.yml (HostDiskFillingUp > 1), puis :
docker compose -f compose.observability.prod.yaml restart prometheus
#    → l'alerte passe Pending puis Firing → notif ntfy. RÉTABLIR le seuil ensuite.

# B. Restart-loop sur un conteneur jetable :
docker run -d --name loop-test --restart=always alpine sh -c 'sleep 2; exit 1'
#    → container_uptime reste < 300 → ContainerRestartLoop Firing (~après for:5m).
docker rm -f loop-test    # nettoyer une fois la notif reçue.
```

État des alertes : Grafana (Alerting) ou, si besoin de debug, les pages internes
d'Alertmanager/Prometheus via un tunnel ponctuel
(`ssh -L 9093:obs_alertmanager:9093 ...` n'est pas possible directement — passer
par `docker exec`/`docker logs obs_alertmanager`).

## Ajouter une règle d'alerte plus tard

1. Éditer `observability/prod/prometheus-rules.infra.yml` (un bloc `alert:` de plus,
   avec `expr`, `for:`, `severity`, et un `runbook:` pointant une ancre d'ici).
2. Recharger Prometheus :
   `docker compose -f compose.observability.prod.yaml restart prometheus`
   (ou `kill -HUP` du process si le lifecycle reload est activé).
3. Garder le cap : **infra uniquement**. Pas d'alerte « app down / pas de trafic ».

## Où sont les secrets

| Secret | Fichier (root-only, gitignoré) |
|---|---|
| Mot de passe admin Grafana | `observability/prod/obs.env` |
| Topic + token ntfy | `observability/prod/ntfy-alertmanager.scfg` |
| (Backups : repo restic, healthchecks.io) | `/etc/canard-backup/backup.env` |

## Procédures par alerte

### disque-qui-se-remplit

Identifier le gros consommateur (`docker system df`, `du -xhd1 /var/lib/docker`,
logs). Purger images/volumes orphelins (`docker system prune`), vérifier la
rétention Prometheus/Tempo. Critique > 90 % : agir avant l'arrêt des écritures.

### memoire-haute

`docker stats` pour le coupable. Le worker recycle déjà sa mémoire
(`--memory-limit`). Si c'est le stack d'obs, ajuster les `mem_limit` du compose.

### restart-loop-conteneur

`docker logs <container_name>` pour la cause de crash. NB : le recyclage horaire du
worker (`--time-limit=3600`) n'est PAS un restart-loop (uptime ~3600 s).

### collector-injoignable

`docker logs obs_otel_collector`. Souvent : socket Docker/`/hostfs` mal monté, ou
le Collector OOM (relever `mem_limit`). Tant que ça dure, les alertes infra de type
« valeur > seuil » sont aveugles.
