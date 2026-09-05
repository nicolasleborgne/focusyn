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

Cinq règles vérifiées mécaniquement — les enfreindre fait échouer `qa` :

1. **Le domaine ne dépend de rien**, pas même de Symfony ou Doctrine. Trois
   exceptions : `symfony/uid`, `symfony/clock`, `doctrine/collections`.
2. **Un contexte ne connaît que lui-même, `Shared`, et les événements publiés
   par les autres.** `src/<Contexte>/Domain/Event/` est le contrat public d'un
   contexte ; tout le reste lui est privé.
3. **Un événement publié ne transporte que des primitives.** Un consommateur qui
   devrait importer `UserId` pour lire `UserWasRegistered` dépendrait des types
   internes d'Identity.
4. `Infrastructure` et `UI` dépendent de `Application` et `Domain`, jamais
   l'inverse. En particulier **`UI` ne touche jamais `Infrastructure`** : passer
   par un port applicatif (voir `SessionStarter`).
5. `src/*/Domain/` est exclu du conteneur de services (`config/services.yaml`) :
   un agrégat ne s'injecte pas.

## Conventions

**Contrôleurs invocables.** Une classe, une action, une méthode `__invoke()`,
dans `src/<Contexte>/UI/Http/`. Nommées à l'impératif du cas d'usage
(`ShowLibraryController`, `SearchNotesController`). Elles étendent
`AbstractController`, conformément aux bonnes pratiques Symfony ; la logique
reste dans la couche Application, le contrôleur ne fait que traduire une requête
HTTP en appel de cas d'usage.

**Routes localisées.** Chaque écran a une adresse par langue, déclarée dans
l'attribut : `#[Route(path: ['fr' => '/bibliotheque', 'en' => '/library'])]`.
Pas de préfixe `/{_locale}` : c'est l'adresse empruntée qui fixe la langue.

**Persistance.** Les agrégats ne portent aucun attribut Doctrine. Le mapping
est du XML dans
`src/<Contexte>/Infrastructure/Persistence/Doctrine/Mapping/<Agrégat>.orm.xml`,
déclaré dans `config/packages/doctrine.yaml` (`auto_mapping` est désactivé).

**Identifiants.** Dériver `App\Shared\Domain\EntityId` par agrégat (UUID v7,
générés par le domaine). Ne jamais passer un `string` nu comme identifiant.

**Multi-tenant.** Un agrégat cloisonné implémente `Shared\Domain\TenantScoped`
et porte une colonne `organization_id`. Le filtre Doctrine `TenantFilter` ajoute
alors la clause à *toutes* les requêtes, sans que les dépôts aient à y penser :
un oubli dans un dépôt ne se verrait pas, la requête marcherait et retournerait
les données de quelqu'un d'autre.

Le filtre est désactivé par défaut (console, workers) et armé à chaque requête
HTTP par `EnableTenantFilterListener`. **Armé sans organisation, il ne laisse
rien passer** — échouer ouvert reviendrait à tout montrer.

Chaque nouvelle ressource cloisonnée s'ajoute à
`tests/Integration/Notebook/TenantIsolationTest.php`. Ce test doit échouer si le
filtre est neutralisé : le vérifier de temps en temps en le sabotant
volontairement, sinon il ne prouve rien.

**Cas d'usage.** Une commande immuable + un gestionnaire `#[AsMessageHandler]`,
dispatchés par le port `CommandBus` (jamais `MessageBusInterface` depuis un
contrôleur). Le bus déballe les `HandlerFailedException` : un appelant attrape
l'exception métier, pas une exception de transport.

**Bus.** `command.bus` (une intention, un gestionnaire, une transaction) et
`event.bus` (un fait acquis, zéro à N gestionnaires). Les dépôts publient les
événements d'un agrégat **après** le flush.

**Sécurité.** L'agrégat `User` n'implémente ni `UserInterface` ni les interfaces
du bundle de double authentification : l'adaptateur `SecurityUser` le fait à sa
place. Les habilitations fines dépendront de l'organisation courante et
passeront par des voteurs, pas par un rôle global.

Trois authentificateurs cohabitent sur le pare-feu `main` : mot de passe,
second facteur, fournisseur externe. Toute connexion programmée doit donc
**nommer** l'authentificateur (`$security->login($user, 'form_login')`), sinon
Symfony refuse de choisir.

**Secrets.** Le secret TOTP est stocké en clair — l'algorithme impose que le
serveur puisse recalculer le code. Les codes de secours, eux, sont **hachés** :
montrés une fois, jamais relisibles. La comparaison passe par
`HashedBackupCodeManager`, qui remplace le gestionnaire du bundle (lequel
suppose des codes en clair).

**Connexion externe.** `SignInWithOAuth` ne rattache un compte existant que si
le fournisseur atteste avoir vérifié l'adresse. Sans ce contrôle, un
fournisseur permissif permettrait de prendre la main sur un compte en déclarant
son adresse. Chaque fournisseur a son lecteur de profil : Google donne la
vérification dans le jeton, GitHub exige un appel à `/user/emails`.

**Interface.** Rendu serveur en Twig. Partage du travail entre les deux outils
front, à respecter strictement :

- **Live Component** dès qu'une interaction touche l'état du serveur (recherche,
  cochage d'une tâche, bascule d'un réglage, enregistrement d'un rappel).
  Ils se testent avec `InteractsWithLiveComponents` : `createLiveComponent()`
  puis `->call('action', [...])`, sans navigateur ;
- **Stimulus** pour l'état purement présentationnel, local à l'onglet (ouverture
  d'un menu, mode focus). Faire un aller-retour réseau pour ouvrir un menu
  serait un gaspillage visible à l'œil.

Seul l'éditeur de note échappe aux deux : CodeMirror 6 piloté par Stimulus
(`assets/controllers/note_editor_controller.js`), parce qu'un aller-retour par
frappe serait inutilisable. Il sauvegarde en différé vers un point d'entrée
JSON, dont le jeton CSRF voyage dans l'en-tête `X-CSRF-Token` — l'attribut
`#[IsCsrfTokenValid]` lit un paramètre de formulaire, pas un en-tête, d'où une
validation explicite dans `SaveNoteBodyController`.

Les couleurs et tailles de l'éditeur sont lues depuis les variables CSS du
design system : aucune valeur en dur dans le JavaScript.

**Responsive, pas de bascule d'appareil.** La maquette propose un interrupteur
DESKTOP/MOBILE : c'était un outil d'aperçu du logiciel de design, pas une
fonction du produit. La coquille utilise un seul balisage et une requête média
à 900 px. Ne pas réintroduire l'interrupteur.

**Données provisoires.** `PrototypeShellDataProvider` et
`PrototypeHomeDataProvider` (dans `Shared/Infrastructure/`) servent les données
de la maquette pour que la coquille soit visible avant que les contextes
n'existent. Elles implémentent des ports de la couche Application : les
remplacer ne doit toucher aucun gabarit. Les supprimer dès que Notebook et Task
exposent leurs requêtes.

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

## PWA

`public/manifest.webmanifest`, `public/sw.js` et `public/icons/` sont des
fichiers statiques, hors AssetMapper : le service worker doit être servi depuis
la racine, sans condensat dans son nom, pour contrôler toute l'origine et rester
détectable à la mise à jour. Le Caddyfile lui impose `Cache-Control: no-cache`.

Stratégies du service worker : réseau d'abord pour les navigations avec repli
sur `/offline`, cache d'abord pour `/assets/*` (versionnés par condensat, donc
immuables). Pas d'écriture hors ligne — écartée à la conception.

En touchant aux icônes, penser à `tests/Unit/Shared/Pwa/ManifestTest.php` :
c'est le seul lien entre le manifeste et les fichiers qu'il déclare.

## Pièges connus

- **Ne jamais exporter `APP_ENV` comme variable d'environnement réelle** (par
  exemple depuis `devenv.nix`) : elle prime sur `$_ENV`, PHPUnit démarre le
  noyau en `dev` et le conteneur de test disparaît. La configuration
  d'environnement appartient aux fichiers `.env`.
- Les commentaires XML n'acceptent pas `--` : `phpunit.dist.xml` refuse de se
  charger si un commentaire contient une option en double tiret.
- Le collecteur Deptrac s'appelle `classNameRegex` et attend une expression
  **avec délimiteurs** (`'#^App\\Kernel$#'`).
- Un `{% set %}` placé dans un gabarit inclus **ne remonte pas** dans la portée
  appelante. Ce qui est partagé entre plusieurs gabarits (la navigation, par
  exemple) est construit en PHP et exposé par une fonction Twig.
- En zsh, `path` est lié à `PATH` : ne jamais s'en servir comme variable dans un
  script shell, sous peine de vider le `PATH` en cours d'exécution.
- **`ResetDatabase` de Foundry est incompatible avec les tests fonctionnels
  ici** : il coupe les connexions ouvertes (« terminating connection due to
  administrator command »). Les suites `functional` s'appuient sur la seule
  transaction annulée par DAMA ; `ResetDatabase` reste dans `integration`.
- `loginUser()` d'un client de test exige un compte **réellement enregistré** :
  à chaque requête le pare-feu recharge l'utilisateur par le fournisseur, et un
  compte fabriqué de toutes pièces est aussitôt déconnecté. Voir le trait
  `App\Tests\Functional\LogsIn`.
- Pas de `set_locale_from_accept_language` : c'est l'adresse empruntée qui fixe
  la langue. Sinon la même URL rend deux langues selon le visiteur et les liens
  générés cessent d'être prévisibles.
- DBAL 4 : `Type::getName()` n'existe plus (les types sont nommés dans
  `doctrine.yaml`) et les erreurs de conversion passent par
  `Doctrine\DBAL\Types\Exception\InvalidType::new()`.
- `scheb_two_factor.security_tokens` doit lister **`UsernamePasswordToken` et
  `PostAuthenticationToken`**. En omettre un laisse passer la connexion sans
  jamais demander le second facteur — sans aucune erreur.
- Avec le second facteur, la connexion enchaîne deux redirections (cible par
  défaut, puis écran du code). Dans un test, suivre toute la chaîne
  (`$client->followRedirects()`), pas un seul saut.
- Un jeton CSRF ne se génère pas hors requête (pas de session) : dans un test
  fonctionnel, soumettre le formulaire via le `Crawler`, qui porte déjà le jeton.
- En YAML, une valeur non guillemetée ne peut pas contenir `: `. Les catalogues
  de traduction en sont truffés — `bin/console lint:yaml translations` avant de
  commiter.
- Les messages de contraintes de validation vivent dans le domaine
  **`validators`**, pas `messages` : `translations/validators+intl-icu.*.yaml`.
- Un test fonctionnel ne peut créer **qu'un seul client** (un noyau par test).
  Pour changer de compte, se déconnecter puis se reconnecter sur le même client.
- `LogsIn::logIn()` passe par le cas d'usage `RegisterUser`, ce qui crée
  l'organisation personnelle. Un compte fabriqué directement n'en a pas, et le
  cloisonnement rendrait alors tous les écrans vides.
- `EntityManager::find()` court-circuite les filtres quand il touche le cache
  d'identité : dans un dépôt cloisonné, passer par une requête DQL.
- Ne pas vider le gestionnaire d'entités juste avant un `persist()` : détacher
  une entité déjà enregistrée la fait ré-insérer, avec violation de clé primaire.
- La **limitation des tentatives de connexion** compte dans un cache qui survit
  d'une exécution de tests à l'autre. Elle est relevée à 1000 en environnement
  de test ; sans cela, les tests de connexion se mettent à échouer après
  quelques passages, sans que rien n'ait changé.
- Foundry reconstruit la base de test **par les migrations**
  (`ResetDatabaseMode::MIGRATE`). Le mode par défaut, SCHEMA, supprime
  `doctrine_migration_versions` et casse toute migration lancée ensuite.

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
