# Architecture de Focusyn

Ce document consigne les décisions structurantes et leurs raisons. Il se lit
avant d'ajouter un contexte ou de modifier une règle de dépendance.

## 1. Monolithe modulaire, pas microservices

Un seul déployable, découpé en contextes bornés étanches. Le découpage est
appliqué par `deptrac.contexts.yaml` : un contexte ne connaît que lui-même et
`Shared`. Toute communication passe par un événement de domaine ou par un port
déclaré dans la couche Application.

Conséquence pratique : `Notebook` ne peut pas appeler `App\Task\...`. S'il a
besoin des tâches liées à une obsession, il publie ou consomme un événement.

## 2. Quatre couches par contexte

```
Domain          ← ne dépend de rien (pas même de Symfony)
Application     ← Domain
Infrastructure  ← Domain + Application
UI              ← Domain + Application
```

`deptrac.yaml` refuse toute dépendance du domaine vers `Symfony\*`,
`Doctrine\*`, `Twig\*` ou `Zenstruck\*`. Deux exceptions assumées, sans état ni
dépendance transitive : `symfony/uid` (UUID v7) et `symfony/clock` (PSR-20).

Cette règle n'est pas décorative : elle est testée. Ajouter un
`use Symfony\Component\HttpFoundation\Request` dans un fichier de `Domain/` fait
échouer `qa`.

`config/services.yaml` double la barrière côté conteneur : `src/*/Domain/` est
exclu de l'enregistrement automatique des services. Un agrégat ne s'injecte pas.

## 3. Domaine pur, mapping Doctrine en XML

Les agrégats sont du PHP ordinaire, sans attribut de persistance. La
correspondance objet/table vit dans
`src/<Contexte>/Infrastructure/Persistence/Doctrine/Mapping/*.orm.xml`.

Trois options étaient sur la table :

| Option | Retenue | Raison |
| --- | --- | --- |
| Entités à attributs Doctrine | non | le domaine dépendrait de l'ORM |
| Domaine pur + entités de persistance + mappers | non | deux modèles et un mapper par agrégat, pour un bénéfice marginal à cette échelle |
| **Domaine pur + mapping XML** | **oui** | un seul modèle, domaine testable sans base, ORM cantonné à l'infrastructure |

`auto_mapping` est désactivé : chaque contexte déclare explicitement son
espace de noms et son dossier de mapping dans `config/packages/doctrine.yaml`.

## 4. Identifiants

`App\Shared\Domain\EntityId` est la base de tous les identifiants. Chaque
agrégat en dérive sa propre classe (`NoteId`, `TaskListId`…), ce qui rend
impossible de passer un identifiant de tâche là où une note est attendue.

Les valeurs sont des **UUID v7** : ordonnés dans le temps, donc sans la
fragmentation d'index d'un UUID v4 en clé primaire PostgreSQL, et générables
par le domaine — un agrégat est complet avant tout aller-retour avec la base.

## 5. Multi-tenant : base unique et discriminant

Chaque table métier porte une colonne `organization_id`. Un filtre Doctrine
l'applique automatiquement à toutes les requêtes, à partir de l'organisation
courante résolue depuis la session.

Retenu contre un schéma ou une base par tenant : migrations simples, une seule
sauvegarde, coût d'infrastructure minimal. Le prix à payer est que l'isolation
repose sur le code — elle doit donc être couverte par des tests fonctionnels
dédiés (« un membre de A ne voit jamais une note de B »), pas seulement par le
filtre lui-même.

## 6. Front : Live Components d'abord, CodeMirror pour l'éditeur

Le rendu est fait par le serveur. Les interactions (navigation, filtres,
tâches, réglages, rappels) passent par des Live Components : un seul modèle
mental, pas d'API à maintenir en parallèle.

**Exception : l'éditeur de note.** La maquette édite ligne à ligne, affiche les
marques markdown en direct et réagit à chaque frappe. Confier cela à un Live
Component imposerait un aller-retour réseau par caractère, avec sauts de
curseur. L'éditeur est donc un contrôleur Stimulus qui pilote **CodeMirror 6**
— le moteur du mode « live preview » d'Obsidian, conçu exactement pour afficher
une source markdown décorée. La sauvegarde est différée vers le serveur.

React n'a pas été retenu : il aurait introduit un second paradigme de rendu à
côté de Twig, pour un seul écran.

## 7. Design system

`assets/styles/` en cinq couches, chacune ne consommant que la précédente :
primitives → sémantique → base → gabarits → composants.

Nommage **BEM** strict avec préfixe `fx-` : `.fx-button`, `.fx-button__icon`,
`.fx-button--primary`. Les états transitoires posés par Stimulus utilisent
`.is-*` (`.is-open`, `.is-armed`).

Règle non négociable : **un composant ne référence jamais une primitive**,
uniquement un jeton sémantique. C'est ce qui permettra d'ajouter un thème sombre
en réécrivant un seul fichier.

Les réglages utilisateur (accent, densité, opacité des marques, prose serif)
sont des attributs `data-fx-*` sur `<html>` qui redéfinissent des variables CSS.
Aucun style de composant n'est recalculé côté serveur.

`/_design-system` (développement uniquement) rend tous les blocs sur une page.

## 8. Tests

Trois suites, du plus rapide au plus lent :

| Suite | Portée | Base de données |
| --- | --- | --- |
| `unit` | domaine pur | non |
| `integration` | dépôts, mapping, filtre multi-tenant | oui |
| `functional` | HTTP de bout en bout, Live Components, sécurité | oui |

`dama/doctrine-test-bundle` enveloppe chaque test dans une transaction annulée
en fin de test ; `zenstruck/foundry` fournit les fabriques d'objets.

Piège rencontré et à ne pas réintroduire : **ne jamais exporter `APP_ENV` comme
variable d'environnement réelle** (par exemple depuis `devenv.nix`). Elle prime
sur `$_ENV`, PHPUnit démarre alors le noyau en `dev` et le conteneur de test
n'existe pas. La configuration d'environnement appartient aux fichiers `.env`.

## 9. Production

FrankenPHP (serveur et PHP dans un seul binaire), image construite par le
`Dockerfile` en trois étapes : socle, dépendances, image finale. Le cache des
dépendances n'est invalidé que par `composer.lock`.

`compose.prod.yaml` démarre trois services : `app`, `worker`
(`messenger:consume`) et `database`. Les migrations sont appliquées au
démarrage du seul conteneur web (`RUN_MIGRATIONS=1`), jamais par le worker.

Le mode *worker* de FrankenPHP est préparé mais désactivé : il se branche dans
`docker/frankenphp/Caddyfile` une fois l'application stabilisée.

## Décisions prises avec le commanditaire

| Sujet | Décision |
| --- | --- |
| Périmètre | l'intégralité de la maquette |
| Modèle SaaS | organisations/équipes **et** abonnement |
| Isolation | base unique, discriminant `organization_id` |
| Domaine | PHP pur, mapping XML |
| Infrastructure | PostgreSQL + FrankenPHP |
| Assistant IA | clé fournie par l'utilisateur (multi-fournisseurs) |
| Versions | Symfony LTS + PHP stable |
| Facturation | Stripe |
| Connexion | courriel + mot de passe avec TOTP, et SSO Google/GitHub |
| Rappels | Web Push, courriel, export `.ics` et flux iCal |
| PWA | installable, cache de l'app shell |
| Langues | français **et** anglais dès le départ |
| Dépôt / CI | GitHub + GitHub Actions |
| Tests | unitaires, intégration, fonctionnels |
| Déploiement | VPS avec docker compose |
