#!/usr/bin/env bash
#
# Le Canard du Vendredi — installation des sauvegardes sur l'HÔTE (§37.4).
#
# Idempotent. Pose les scripts dans /opt/canard-backup, les units dans
# /etc/systemd/system, et amorce l'EnvironmentFile root-only dans /etc/canard-backup.
# Active les timers quotidien + hebdo ; laisse le restore-test mensuel DÉSACTIVÉ
# (à allumer à la main quand tu veux). À lancer sur le VPS après un déploiement :
#     sudo ops/backup/install.sh        (ou `make backup-install`)
set -euo pipefail

log()  { printf '\033[1;36m▶ %s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m✓ %s\033[0m\n' "$*"; }
err()  { printf '\033[1;31m✗ %s\033[0m\n' "$*" >&2; }

[ "$(id -u)" = 0 ] || { err "À lancer en root (sudo)."; exit 1; }

SRC="$(cd "$(dirname "$0")" && pwd)"
OPT_DIR=/opt/canard-backup
ETC_DIR=/etc/canard-backup
UNIT_DIR=/etc/systemd/system
ENV_FILE="${ETC_DIR}/backup.env"

# ── 1. Scripts → /opt/canard-backup ──────────────────────────────────────────
log "Installation des scripts dans ${OPT_DIR}…"
install -d -m 755 "$OPT_DIR"
for s in backup.sh check.sh restore-test.sh alert.sh; do
  install -m 755 "${SRC}/${s}" "${OPT_DIR}/${s}"
done
install -m 644 "${SRC}/README.md" "${OPT_DIR}/README.md"
ok "Scripts installés."

# ── 2. Units systemd → /etc/systemd/system ───────────────────────────────────
log "Installation des units systemd…"
for u in "${SRC}"/systemd/*.service "${SRC}"/systemd/*.timer; do
  install -m 644 "$u" "${UNIT_DIR}/$(basename "$u")"
done
systemctl daemon-reload
ok "Units installées et rechargées."

# ── 3. EnvironmentFile root-only → /etc/canard-backup ─────────────────────────
install -d -m 700 "$ETC_DIR"
if [ ! -f "$ENV_FILE" ]; then
  install -m 600 "${SRC}/backup.env.dist" "$ENV_FILE"
  err "→ ${ENV_FILE} créé depuis le modèle. RENSEIGNE les secrets (RESTIC_PASSWORD, repo)"
  err "  AVANT le premier run. Tant qu'il contient CHANGE_ME, le backup échouera."
else
  ok "${ENV_FILE} déjà présent — laissé tel quel (secrets préservés)."
fi

# ── 4. Activer les timers (quotidien + hebdo). Restore-test laissé DÉSACTIVÉ ──
log "Activation des timers quotidien + hebdomadaire…"
systemctl enable --now canard-backup.timer
systemctl enable --now canard-backup-check.timer
ok "Timers actifs."

cat <<'EOF'

  ── Prochaines étapes (manuelles) ─────────────────────────────────────────────
  1. Renseigner /etc/canard-backup/backup.env (RESTIC_PASSWORD, repo, alertes).
  2. Wiring SSH vers le Storage Box : voir /opt/canard-backup/README.md
      (clé dédiée, known_hosts, ajout de la clé publique côté Hetzner).
  3. Premier run à la main :
        systemctl start canard-backup.service
        journalctl -u canard-backup.service -n 50 --no-pager
      Puis vérifier la présence du snapshot :
        set -a; . /etc/canard-backup/backup.env; set +a; restic snapshots
  4. (Optionnel) Restore-test mensuel automatisé :
        systemctl enable --now canard-restore-test.timer

  Prochain déclenchement des timers :  systemctl list-timers 'canard-*'
EOF
ok "Installation terminée."
