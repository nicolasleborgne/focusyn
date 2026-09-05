# Focusyn

Carnet numérique de synthèse personnelle : des notes en markdown, des obsessions
(sujets suivis dans la durée), des listes de tâches, des rappels et un assistant
d'écriture. Application web installable (PWA), multi-organisations.

L'interface est la mise en œuvre d'une maquette produite avec Claude Design ;
la maquette d'origine est conservée telle quelle dans `project/` et sert de
référence visuelle (voir `docs/design-handoff.md`).

## Démarrer

Le seul prérequis est [devenv](https://devenv.sh) (Nix). Aucune installation de
PHP, PostgreSQL ou Node n'est nécessaire sur la machine hôte.

```bash
devenv shell        # PHP 8.4, Composer, Node 22, CLI Symfony
composer install
devenv up -d        # PostgreSQL 17 + Mailpit
serve               # http://127.0.0.1:8000
```

Avec [direnv](https://direnv.net), `direnv allow` charge l'environnement
automatiquement à l'entrée dans le dossier.

### Commandes

| Commande | Effet |
| --- | --- |
| `serve` | Serveur de développement sur le port 8000 |
| `console <cmd>` | Raccourci vers `bin/console` |
| `tests` | Suite complète PHPUnit |
| `tests --testsuite=unit` | Une seule suite : `unit`, `integration` ou `functional` |
| `stan` | Analyse statique PHPStan |
| `cs` | Correction du style de code |
| `qa` | Style + statique + architecture + tests (ce que vérifie la CI) |
| `devenv up -d` | Démarre PostgreSQL et Mailpit en arrière-plan |

Mailpit expose les courriels de développement sur <http://127.0.0.1:8025>.

En développement, `/_design-system` affiche la référence vivante du design
system : chaque bloc BEM avec ses variantes.

## Pile technique

| Domaine | Choix |
| --- | --- |
| Langage | PHP 8.4 |
| Cadriciel | Symfony 7.4 LTS |
| Base de données | PostgreSQL 17 |
| Front | Twig, Symfony UX Live Components, Stimulus, AssetMapper |
| Éditeur de notes | CodeMirror 6 piloté par Stimulus |
| Tests | PHPUnit 13, `dama/doctrine-test-bundle`, `zenstruck/foundry` |
| Qualité | PHPStan (niveau 8), PHP-CS-Fixer, Deptrac |
| Environnement | devenv (Nix) |
| Production | FrankenPHP, Docker, `compose.prod.yaml` |

## Architecture

Monolithe modulaire : un contexte borné par dossier sous `src/`, chacun découpé
en quatre couches. Les règles de dépendance sont vérifiées mécaniquement par
Deptrac à chaque exécution de `qa`.

```
src/<Contexte>/
  Domain/          PHP pur — agrégats, objets valeur, interfaces de dépôt
  Application/     cas d'usage, commandes et requêtes, ports
  Infrastructure/  Doctrine (mapping XML), adaptateurs, services techniques
  UI/              contrôleurs invocables, Live Components, Twig
```

Le détail des décisions et de leurs raisons est dans
[`docs/architecture.md`](docs/architecture.md).

## Déploiement

```bash
docker compose -f compose.prod.yaml --env-file .env.prod.local up -d --build
```

L'image est construite par le `Dockerfile` (cible `prod`) sur FrankenPHP.
`compose.prod.yaml` démarre le serveur web, un worker Messenger et PostgreSQL.
