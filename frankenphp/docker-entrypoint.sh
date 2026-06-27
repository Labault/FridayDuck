#!/bin/sh
set -e

# Entrypoint FrankenPHP — Le Canard du Vendredi.
# Prépare l'application avant de lancer le serveur (ou une commande console).

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
  # En dev, le code est monté : installer les vendors si absents.
  if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
    composer install --prefer-dist --no-progress --no-interaction
  fi

  # Affiche la version du projet (ou une erreur d'amorçage exploitable).
  php bin/console -V

  if [ -n "${DATABASE_URL:-}" ]; then
    echo 'Waiting for database to be ready...'
    attempts=60
    until php bin/console dbal:run-sql -q 'SELECT 1' >/dev/null 2>&1; do
      attempts=$((attempts - 1))
      if [ "$attempts" -le 0 ]; then
        echo 'The database is not up or not reachable.' >&2
        exit 1
      fi
      echo "Still waiting for database... $attempts attempts left."
      sleep 1
    done
    echo 'The database is now ready and reachable.'

    # Le Scheduler déclenche le métier ; la MÉCANIQUE de migration, elle,
    # tourne ici au démarrage. La synchro du stockage de métadonnées est
    # toujours sûre sur une base vide ; le migrate ne s'applique que s'il
    # existe des migrations (aucune à ce stade — Phase 1+).
    php bin/console doctrine:migrations:sync-metadata-storage --no-interaction
    if [ -n "$(find ./migrations -iname '*.php' -print -quit 2>/dev/null)" ]; then
      php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing --allow-no-migration
    fi
  fi

  echo 'PHP app ready!'
fi

exec docker-php-entrypoint "$@"
