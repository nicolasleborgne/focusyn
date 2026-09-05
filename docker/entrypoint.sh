#!/bin/sh
set -e

# Le conteneur peut jouer trois rôles selon la commande reçue : serveur web,
# worker Messenger, ou tâche ponctuelle (console). Seul le premier démarrage
# du serveur prépare le cache et, si demandé, applique les migrations.

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
    mkdir -p var/cache var/log var/share

    if [ "$APP_ENV" = 'prod' ]; then
        php bin/console cache:warmup --no-interaction
    fi

    if [ "$RUN_MIGRATIONS" = '1' ]; then
        echo "Application des migrations Doctrine…"
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    fi
fi

exec "$@"
