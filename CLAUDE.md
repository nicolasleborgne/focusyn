# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Ce qu'est ce dépôt

Focusyn, un SaaS de carnet numérique (notes markdown, obsessions, tâches,
rappels, assistant d'écriture), en cours de construction à partir d'une maquette
Claude Design conservée dans `project/`.

Deux choses cohabitent, à ne pas confondre :

- `project/Focusyn.dc.html` — la **maquette** (prototype HTML/JS, non exécuté en
  production). C'est la spécification visuelle : dimensions, couleurs, copie
  française, comportements. On la lit, on ne la modifie pas.
- tout le reste — l'**application** Symfony qui la réalise.

Le format `.dc.html` et le fonctionnement de la maquette sont décrits en fin de
fichier ; les décisions d'architecture le sont dans `docs/architecture.md`.

## Commandes

Tout passe par devenv (Nix) : PHP, PostgreSQL et Node ne sont pas installés sur
l'hôte. Préfixer par `devenv shell --` hors du shell, ou utiliser direnv.

```bash
devenv up -d                          # PostgreSQL 17 + Mailpit
serve                                 # serveur de dev, port 8000
console <commande>                    # bin/console
qa                                    # style + statique + architecture + tests
tests                                 # PHPUnit, les trois suites
vendor/bin/phpunit --testsuite=unit   # une suite : unit | integration | functional
vendor/bin/phpunit --filter=NomDuTest # un seul test
stan                                  # PHPStan
cs                                    # PHP-CS-Fixer (correction)
vendor/bin/deptrac analyse --config-file=deptrac.yaml           # couches
vendor/bin/deptrac analyse --config-file=deptrac.contexts.yaml  # contextes
```

`qa` est exactement ce que vérifie la CI GitHub Actions. Le lancer avant de
proposer un commit.

## Architecture

Monolithe modulaire. Un contexte borné par dossier de `src/`, quatre couches
dans chacun :

```
src/<Contexte>/
  Domain/          PHP pur : agrégats, objets valeur, interfaces de dépôt, événements
  Application/     cas d'usage, commandes/requêtes, ports vers l'extérieur
  Infrastructure/  Doctrine + mapping XML, adaptateurs, services techniques
  UI/              contrôleurs invocables, Live Components, Twig
```

Contextes : `Shared`, `Identity`, `Organization`, `Notebook`, `Task`,
`Reminder`, `Assistant`, `Billing`, `Privacy`.

Quatre règles vérifiées mécaniquement — les enfreindre fait échouer `qa` :

1. **Le domaine ne dépend de rien**, pas même de Symfony ou Doctrine. Seules
   exceptions : `symfony/uid` et `symfony/clock`.
2. **Un contexte ne connaît que lui-même et `Shared`.** Entre contextes : un
   événement de domaine ou un port applicatif, jamais un appel direct.
3. `Infrastructure` et `UI` dépendent de `Application` et `Domain`, jamais
   l'inverse.
4. `src/*/Domain/` est exclu du conteneur de services (`config/services.yaml`) :
   un agrégat ne s'injecte pas.

## Conventions

**Contrôleurs invocables.** Une classe, une action, une méthode `__invoke()`,
dans `src/<Contexte>/UI/Http/`. Nommées à l'impératif du cas d'usage
(`ShowDesignSystemController`, `HealthCheckController`). Ne pas étendre
`AbstractController` : injecter ce dont on a besoin (`Twig\Environment`,
`Connection`…), ce qui rend le contrôleur testable sans conteneur.

**Persistance.** Les agrégats ne portent aucun attribut Doctrine. Le mapping
est du XML dans
`src/<Contexte>/Infrastructure/Persistence/Doctrine/Mapping/<Agrégat>.orm.xml`,
déclaré dans `config/packages/doctrine.yaml` (`auto_mapping` est désactivé).

**Identifiants.** Dériver `App\Shared\Domain\EntityId` par agrégat (UUID v7,
générés par le domaine). Ne jamais passer un `string` nu comme identifiant.

**Multi-tenant.** Toute table métier porte `organization_id`. Un test
fonctionnel doit prouver l'étanchéité pour chaque nouvelle ressource : un membre
de l'organisation A ne doit jamais atteindre une donnée de B.

**Interface.** Rendu serveur en Twig, interactions en Live Components. Seul
l'éditeur de note échappe à la règle : CodeMirror 6 piloté par Stimulus, parce
qu'un aller-retour réseau par frappe serait inutilisable.

**Traductions.** Aucun texte en dur dans les templates : catalogues `fr` et `en`
dès l'écriture. La copie française de référence est celle de la maquette.

**TDD.** Test d'abord, y compris pour les règles métier. Trois suites :
`tests/Unit` (domaine, sans base), `tests/Integration` (Doctrine et PostgreSQL
réels, transactions annulées par DAMA), `tests/Functional` (HTTP complet).
Fabriques d'objets avec `zenstruck/foundry`.

## Design system

`assets/styles/`, cinq couches strictement ordonnées :
`tokens/primitives` → `tokens/semantic` → `base` → `layout` → `components`,
assemblées par `@import` dans `app.css` (AssetMapper réécrit les chemins, aucun
build Node n'est nécessaire).

Nommage **BEM** préfixé `fx-` : `.fx-button`, `.fx-button__icon`,
`.fx-button--primary`. États transitoires posés par Stimulus : `.is-open`,
`.is-armed`.

**Un composant ne référence jamais une primitive**, uniquement un jeton
sémantique (`--fx-surface-app`, `--fx-text-muted`, `--fx-accent`). Les réglages
utilisateur sont des attributs `data-fx-*` sur `<html>` qui redéfinissent des
variables ; aucun style n'est calculé côté serveur.

`/_design-system` (dev uniquement) rend tous les blocs avec leurs variantes :
s'en servir comme vérification visuelle après toute modification de jeton.

## Pièges connus

- **Ne jamais exporter `APP_ENV` comme variable d'environnement réelle** (par
  exemple depuis `devenv.nix`) : elle prime sur `$_ENV`, PHPUnit démarre le
  noyau en `dev` et le conteneur de test disparaît. La configuration
  d'environnement appartient aux fichiers `.env`.
- Les commentaires XML n'acceptent pas `--` : `phpunit.dist.xml` refuse de se
  charger si un commentaire contient une option en double tiret.
- Le collecteur Deptrac s'appelle `classNameRegex` et attend une expression
  **avec délimiteurs** (`'#^App\\Kernel$#'`).

## La maquette (`project/`)

À lire avant d'implémenter un écran : tout y est — dimensions, teintes, copie.

`Focusyn.dc.html` est un composant unique au format `.dc.html` :

- `<x-dc>` contient le gabarit ; `<helmet>` les styles globaux.
- `<script type="text/x-dc" data-dc-script>` contient la logique, une classe
  `Component extends DCLogic`. `data-props` est le schéma JSON des réglages
  éditables (`accent`, `serifProse`, `markOpacity`, `density`, `showPreview`).
- `{{ expr }}` est résolu par un résolveur de chemins minimal, pas par `eval` :
  aucune expression JavaScript dans le gabarit, tout est précalculé.
- `<sc-if>` et `<sc-for>` sont les seules structures de contrôle. Les attributs
  `hint-placeholder-*` sont des indices d'aperçu pour l'outil de design, sans
  signification à l'exécution : les ignorer.
- Les styles sont souvent des chaînes entières construites dans la logique, d'où
  le fort couplage style/état du prototype — que le design system défait.

Le pont entre les deux moitiés est **`renderVals()`** : il retourne un unique
objet plat, seule chose que le gabarit voit. Pour porter un écran, lire son bloc
`<sc-if>` en parallèle des clés correspondantes en fin de `renderVals()`.

`support.js` est le moteur généré (`// GENERATED from dc-runtime/src/*.ts`) :
lecture seule, et seulement pour comprendre la sémantique du gabarit.

Raccourcis du prototype à ne pas reproduire : `today` et « 2 140 mots ce mois »
codés en dur, horodatages stockés en chaînes françaises (« il y a 2 h »), corps
de notes qui recouvrent des constantes immuables, `window.claude.complete` qui
n'existe que dans l'outil de design.
