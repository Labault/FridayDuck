# Sauvegardes PostgreSQL — installation & wiring (§37.4)

Sauvegardes **chiffrées, off-site, avec rétention et restauration testée**, au
niveau **hôte** (timers systemd), indépendantes des redéploiements push-to-deploy.

- **Outil :** restic · **Cible off-site :** Hetzner Storage Box (SFTP)
- **Rétention :** 7 quotidiens + 4 hebdos · **Vérif :** `restic check --read-data` hebdo
- **Restauration :** [`docs/runbook-backup.md`](../../docs/runbook-backup.md)

Le **chiffrement et la rétention sont gérés par restic**, pas par l'app. Les
credentials PostgreSQL **ne quittent jamais** le conteneur `duck_db` : `pg_dump`
lit `$POSTGRES_USER` / `$POSTGRES_DB` en interne (socket local). L'hôte ne détient
que les secrets restic.

## Prérequis sur le VPS

- `restic` installé : `apt-get install -y restic` (ou binaire officiel à jour).
- Le conteneur `duck_db` tourne (stack prod up).
- `docker` accessible par root.

## 1. Clé SSH dédiée au backup (PAS ta clé perso)

Sur le VPS, en root :

```bash
ssh-keygen -t ed25519 -N '' \
  -C 'canard-backup@vps' \
  -f /root/.ssh/canard-backup_ed25519
```

Crée/complète `/root/.ssh/config` avec un alias dédié (port **23** = SFTP Storage
Box). Remplace `uXXXXXX` par ton identifiant Storage Box :

```sshconfig
Host canard-storagebox
    HostName uXXXXXX.your-storagebox.de
    User uXXXXXX
    Port 23
    IdentityFile /root/.ssh/canard-backup_ed25519
    IdentitiesOnly yes
    UserKnownHostsFile /root/.ssh/known_hosts_canard
```

`RESTIC_REPOSITORY=sftp:canard-storagebox:/canard-restic` (dans `backup.env`)
s'appuie sur cet alias : restic y trouve hôte, port, user et clé.

## 2. ÉTAPE MANUELLE — ajouter la clé publique côté Hetzner

> Je ne peux pas le faire à ta place : ça se passe dans la console Hetzner.

Affiche la clé publique :

```bash
cat /root/.ssh/canard-backup_ed25519.pub
```

Puis, au choix :

- **Console Hetzner Robot** → *Storage Box* → *Settings* → **SSH keys** → coller la
  clé publique et enregistrer ; **ou**
- En une commande (active d'abord « SSH support » sur le Storage Box) :

  ```bash
  # ajoute SANS écraser les clés déjà présentes (note le -a)
  cat /root/.ssh/canard-backup_ed25519.pub \
    | ssh -p23 uXXXXXX@uXXXXXX.your-storagebox.de install-ssh-key -a
  ```

## 3. Pré-remplir known_hosts (pas de TOFU, pas de MITM)

Récupère la clé d'hôte du Storage Box **sans l'accepter aveuglément**, puis
**vérifie le fingerprint** contre celui publié par Hetzner avant de t'y fier :

```bash
ssh-keyscan -p 23 uXXXXXX.your-storagebox.de > /root/.ssh/known_hosts_canard

# Affiche les fingerprints récupérés — À COMPARER aux fingerprints officiels
# Hetzner (doc Storage Box / console). S'ils diffèrent : STOP (MITM), ne continue pas.
ssh-keygen -lf /root/.ssh/known_hosts_canard
chmod 600 /root/.ssh/known_hosts_canard /root/.ssh/canard-backup_ed25519
```

Test de connexion (doit réussir SANS prompt interactif) :

```bash
ssh canard-storagebox 'echo OK Storage Box joignable'
```

## 4. Installer les units et les scripts

```bash
sudo ops/backup/install.sh        # ou : make backup-install
```

Renseigne ensuite `/etc/canard-backup/backup.env` (au moins `RESTIC_PASSWORD` et
`RESTIC_REPOSITORY` ; idéalement les URLs healthchecks.io).

## 5. Premier run + critère de réussite

```bash
systemctl start canard-backup.service
journalctl -u canard-backup.service -n 50 --no-pager

# Le snapshot doit apparaître :
set -a; . /etc/canard-backup/backup.env; set +a
restic snapshots

# Intégrité complète (relit les données off-site) :
systemctl start canard-backup-check.service

# Restauration vers cible jetable (ne touche pas la prod) :
systemctl start canard-restore-test.service
journalctl -u canard-restore-test.service -n 30 --no-pager
```

## Alerte & dead-man's-switch

- **healthchecks.io** (free tier) recommandé : un check « daily » pour le backup,
  un « weekly » pour la vérif, un « monthly » pour le restore-test. Colle les ping
  URLs dans `backup.env`. Le succès pingue, l'échec pingue `/fail`, et **HC alerte
  de lui-même si aucun ping n'arrive** dans la fenêtre → attrape même « le timer
  ne s'est jamais lancé », qu'un `OnFailure` ne verrait jamais.
- Alternative générique : `ALERT_WEBHOOK` (Slack/Discord/ntfy) pour l'`OnFailure`.

## ⚠️ RESTIC_PASSWORD = point de défaillance unique

Si tu perds `RESTIC_PASSWORD`, **tous les backups deviennent irrécupérables**
(du chiffré illisible — aucune backdoor, c'est le principe). Stocke-le **hors du
serveur**, dans ton gestionnaire de mots de passe. Le perdre = repartir de zéro.
