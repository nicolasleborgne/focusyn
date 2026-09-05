{ pkgs, lib, config, ... }:

{
  # ---------------------------------------------------------------------------
  # Focusyn — environnement de développement
  # ---------------------------------------------------------------------------
  # `devenv up` démarre PostgreSQL, Mailpit et le worker Messenger.
  # `devenv shell` (ou direnv) met PHP, Composer, Node et le CLI Symfony au PATH.

  languages.php = {
    enable = true;
    version = "8.4";
    extensions = [ "pdo_pgsql" "pgsql" "intl" "zip" "sodium" "opcache" "apcu" ];
    ini = ''
      memory_limit = 512M
      date.timezone = "Europe/Paris"
      display_errors = On
      error_reporting = E_ALL
      realpath_cache_ttl = 3600
      opcache.enable_cli = 1
      xdebug.mode = off
      xdebug.client_host = 127.0.0.1
      xdebug.start_with_request = trigger
    '';
  };

  languages.javascript = {
    enable = true;
    package = pkgs.nodejs_22;
  };

  packages = with pkgs; [
    git
    symfony-cli
    postgresql_17     # pour psql / pg_dump en ligne de commande
    gnumake
  ];

  # ---------------------------------------------------------------------------
  # Services
  # ---------------------------------------------------------------------------
  services.postgres = {
    enable = true;
    package = pkgs.postgresql_17;
    listen_addresses = "127.0.0.1";
    port = 5432;
    initialDatabases = [
      { name = "focusyn"; }
      { name = "focusyn_test"; }
    ];
    initialScript = ''
      CREATE ROLE focusyn WITH LOGIN SUPERUSER PASSWORD 'focusyn';
    '';
  };

  services.mailpit = {
    enable = true;
    smtpListenAddress = "127.0.0.1:1025";
    uiListenAddress = "127.0.0.1:8025";
  };

  # ---------------------------------------------------------------------------
  # Pas de variable applicative ici : la configuration vient des fichiers .env.
  #
  # Exporter APP_ENV depuis devenv casserait la suite de tests — une vraie
  # variable d'environnement prime sur $_ENV et PHPUnit démarrerait le noyau en
  # « dev », sans le conteneur de test.
  # ---------------------------------------------------------------------------

  # ---------------------------------------------------------------------------
  # Raccourcis
  # ---------------------------------------------------------------------------
  scripts = {
    console.exec = "php bin/console \"$@\"";
    # Serveur de développement via le CLI Symfony : routage des variables
    # d'environnement, journal unifié PHP + serveur, et détection automatique
    # des services démarrés par devenv.
    serve.exec = "symfony server:start --no-tls --port=8000";
    serve-d.exec = "symfony server:start --no-tls --port=8000 --daemon";
    unserve.exec = "symfony server:stop";
    logs.exec = "symfony server:log";

    # Vérification visuelle : sème un compte de démonstration puis capture
    # chaque écran. Chromium vient de nix — les binaires téléchargés par
    # Playwright ne démarrent pas sur NixOS.
    shots.exec = ''
      set -e
      php bin/console app:demo
      nix --extra-experimental-features "nix-command flakes" shell nixpkgs#chromium \
        --command node tools/screenshots.mjs
      echo "→ var/screenshots/"
    '';
    tests.exec = "vendor/bin/phpunit \"$@\"";
    stan.exec = "vendor/bin/phpstan analyse --memory-limit=1G \"$@\"";
    cs.exec = "vendor/bin/php-cs-fixer fix \"$@\"";
    qa.exec = ''
      set -e
      echo "→ style"      && vendor/bin/php-cs-fixer fix --dry-run --diff -q
      echo "→ statique"   && vendor/bin/phpstan analyse --memory-limit=1G --no-progress -q
      echo "→ couches"    && vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress -q
      echo "→ contextes"  && vendor/bin/deptrac analyse --config-file=deptrac.contexts.yaml --no-progress -q
      echo "→ tests"      && vendor/bin/phpunit
    '';
  };

  enterShell = ''
    echo "Focusyn — PHP $(php -r 'echo PHP_VERSION;') · $(node --version) · PostgreSQL 17"
    echo "  devenv up      démarre PostgreSQL, Mailpit et le worker"
    echo "  serve          lance le serveur web sur http://127.0.0.1:8000"
    echo "  qa             style + analyse statique + tests"
  '';
}
