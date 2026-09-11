# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Image de production Focusyn — FrankenPHP (serveur + PHP dans un seul binaire)
# ---------------------------------------------------------------------------
# L'environnement de développement n'utilise PAS ce fichier : il est fourni par
# devenv (voir devenv.nix). Cette image ne sert qu'au déploiement.

ARG FRANKENPHP_VERSION=1.12
ARG PHP_VERSION=8.4

# --- Socle commun -----------------------------------------------------------
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-alpine AS base

WORKDIR /app

# `apk upgrade` avant `apk add` : l'image de base est figée par son étiquette,
# et son index de paquets vieillit entre deux publications amont. Sans cette
# ligne, `curl` et ses bibliothèques restaient à la version livrée — c'est-à-dire
# avec les failles que grype signale dans la CI, toutes corrigées en amont.
RUN apk upgrade --no-cache \
    && apk add --no-cache acl curl file gettext git libcap tzdata \
    && install-php-extensions \
        apcu \
        intl \
        opcache \
        pdo_pgsql \
        sodium \
        zip

ENV TZ=Europe/Paris \
    SERVER_NAME=:80 \
    COMPOSER_ALLOW_SUPERUSER=1

COPY --link docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY --link docker/entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

# --- Non privilégié, première moitié ---------------------------------------
# Le serveur tournera sous `www-data`, et non sous root : une exécution de code
# arbitraire dans PHP n'est alors pas déjà une prise de contrôle du conteneur.
# Deux conditions, préparées ici :
#   - `setcap` donne au seul binaire le droit d'ouvrir les ports 80 et 443,
#     réservés à root sans cela ;
#   - Caddy écrit ses certificats et sa configuration dans /data et /config,
#     montés en volumes par la production : ils doivent lui appartenir.
#
# Le `USER` lui-même n'est posé qu'à la toute fin de l'étape `prod` : les
# étapes intermédiaires installent encore les dépendances et compilent les
# actifs, ce qui demande d'écrire dans /app.
RUN setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R www-data:www-data /data /config

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

# La sonde interroge le point d'écoute interne, sur la boucle locale : le site
# public ne répond qu'à son propre nom d'hôte (`SERVER_NAME`), et une requête
# adressée à « localhost » n'y trouvait aucun site — le conteneur était
# déclaré mort alors qu'il répondait. Voir le Caddyfile.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:2020/healthz || exit 1

# --- Dépendances ------------------------------------------------------------
# Étape isolée : le cache Docker n'est invalidé que si composer.lock change.
FROM base AS vendor

COPY --from=composer/composer:2-bin /composer /usr/bin/composer
COPY --link composer.json composer.lock symfony.lock ./

RUN composer install \
    --no-cache --no-dev --no-scripts --no-autoloader \
    --prefer-dist --no-progress --no-interaction

# --- Image finale -----------------------------------------------------------
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    FRANKENPHP_CONFIG=""

COPY --from=composer/composer:2-bin /composer /usr/bin/composer
COPY --link docker/php/conf.d/app.prod.ini /usr/local/etc/php/conf.d/app.ini
COPY --from=vendor --link /app/vendor ./vendor
COPY --link . .

RUN set -eux; \
    mkdir -p var/cache var/log var/share; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    composer dump-env prod; \
    composer run-script --no-dev post-install-cmd; \
    php bin/console asset-map:compile; \
    chmod +x bin/console; \
    sync

# --- Non privilégié, seconde moitié ----------------------------------------
# `var/` est le seul endroit où l'application écrit : le cache réchauffé au
# démarrage, les journaux, les exports. Le reste de /app lui reste en lecture
# seule, ce qui est exactement ce qu'on veut — un code qui ne peut pas se
# réécrire ne peut pas se rendre persistant.
RUN chown -R www-data:www-data var

USER www-data
