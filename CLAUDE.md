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

`qa` couvre ce que la CI vérifie **du code**. Elle vérifie en plus trois choses
qu'aucune suite de tests ne voit, par des outils conteneurisés — aucune
installation locale n'est nécessaire :

```bash
# Secrets, dans tout l'historique (la configuration est dans .gitleaks.toml)
docker run --rm -v "$PWD:/repo:ro" zricethezav/gitleaks:v8.30.1 \
    git /repo --config /repo/.gitleaks.toml --redact --no-banner --verbose

# Vulnérabilités des dépendances (composer.lock, package-lock.json, actions)
docker run --rm -v "$PWD:/src:ro" anchore/grype:v0.118.0 dir:/src \
    --exclude './vendor/**/.github/**' --exclude './node_modules/**' --fail-on medium

# Vulnérabilités de l'image, une fois construite
docker build --target prod -t focusyn:local .
docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
    anchore/grype:v0.118.0 focusyn:local --only-fixed --fail-on critical
```

Les versions sont figées dans le workflow, et **les actions GitHub sont
épinglées par empreinte de commit**, étiquette en commentaire : une étiquette se
déplace, `@v4` désigne ce que son auteur y a poussé ce matin, et un dépôt
compromis livrerait son code dans nos exécutions avec le jeton qui va avec. Un
outil de sécurité qui change de verdict tout seul rend par ailleurs la CI
ininterprétable. `syft` produit en plus
l'inventaire (SBOM CycloneDX) joint à chaque exécution — c'est le seul endroit
où l'on voit ce que contient réellement l'image : paquets Alpine, extensions
PHP, modules Go compilés dans FrankenPHP.

**Un secret ne doit pas devenir un commit.** Le hook de pré-commit, posé par
GrumPHP (`vendor/bin/grumphp git:init`, fait à l'installation), lance gitleaks
sur l'index puis php-cs-fixer sur ce qui change. C'est ce que la CI ne peut pas
faire : elle trouve un secret *après*, quand il faut déjà le révoquer et
réécrire l'historique.

gitleaks n'est **pas** une tâche GrumPHP mais une ligne du gabarit
`.githooks/pre-commit`, et c'est délibéré : le `triggered_by` d'une tâche est
une expression `/\.(ext)$/` sur le nom du fichier, qu'un `Dockerfile` ou un
`Caddyfile` ne peut jamais déclencher — précisément là où un secret passe
inaperçu. Le hook n'embarque que deux vérifications rapides : au-delà, on le
contourne au `--no-verify`, et un garde-fou contourné ne garde rien.

La politique du dépôt se vérifie de même, sans aucune identité — `validate`
regarde le schéma du fournisseur, pas l'état distant :

```bash
docker run --rm -v "$PWD/terraform/github:/tf" -w /tf \
    ghcr.io/opentofu/opentofu:1.12.6 validate
```

Le seuil de l'image est « critique » et non « élevé » : FrankenPHP 1.12 embarque
`google.golang.org/grpc` 1.81, sur lequel pèsent trois avis élevés qu'aucune
reconstruction ne corrige. Bloquer dessus rendrait la CI rouge en permanence,
c'est-à-dire illisible. Le resserrer suppose que l'amont bouge.

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
`Routine`, `Inbox`, `Reminder`, `Assistant`, `Billing`, `Privacy`.

**L'accueil montre les routines du jour, et on les y coche.** C'est ce que fait
la maquette, et c'est ce qui donne son sens à la section : une routine du matin
se coche le matin, en ouvrant l'application — devoir ouvrir chaque routine pour
cela reviendrait à ne pas les montrer. D'où un Live Component (`RoutineToday`)
et non une simple liste. Une routine terminée disparaît de l'accueil : la garder
pour montrer qu'elle est faite occuperait la place de ce qui reste.

**Le quatrième compteur de l'accueil est « routines à cocher »**, et non plus
« mots ce mois » : la maquette a troqué l'un pour l'autre, et un décompte qu'on
peut faire descendre vaut mieux qu'un total qui monte. `wordsWrittenSince()` a
été retiré avec son port — du code mort se serait sinon accumulé derrière un
écran qui ne l'affiche plus.

**Sur une routine quotidienne, aucune étape n'affiche son calendrier** : il n'y
a rien à dire, c'est tous les jours, et le répéter sur chaque ligne serait du
bruit.

**La revue du soir est le seul écran qui lit trois contextes à la fois** —
tâches, routines, rappels. Elle vit donc dans `Shared` et n'en connaît aucun :
chacun lui parle par son port, et elle ne manipule que des primitives. Reporter
pose **un rappel par tâche**, sur son propre sujet : c'est ce qui permet d'en
déplacer une ensuite sans défaire le report entier. Cinq tâches au plus — au-delà,
« reporter à demain » cesse d'être une décision et devient un déménagement.

**Une synthèse d'obsession se propose, elle ne s'enregistre pas.** « Ce qui se
dégage » peut avoir été écrit à la main, et l'écraser sans demander ferait
perdre le fruit d'une lecture qu'aucune machine ne refera. Le Live Component
tient la proposition ; « garder » seul appelle `DescribeObsession`. Les puces
et numéros que le modèle remet malgré la consigne sont retirés — l'écran
numérote déjà.

**La recherche traverse les contextes par des ports.** `TaskFinder` et
`RoutineFinder` sont déclarés dans `Shared\Application\Search` et implémentés
par Task et Routine : l'écran appartient à Notebook, qui n'a le droit de
connaître ni l'un ni l'autre. Une routine se cherche **par son nom et par ses
étapes** — « Matin » ne dit rien de ce qu'elle contient, et c'est « rafraîchir
le levain » qu'on aura en tête. Ce qui est annoncé sous le champ doit
correspondre à ce qui est réellement fouillé : un test le vérifie.

**Ce qui rapproche deux notes est grossier, et doit le rester.**
`NoteKeywords` retient les mots d'au moins cinq lettres hors liste de mots
vides ; un mot partagé vaut un point, une **obsession commune en vaut quatre**
— c'est le seul signal posé à la main, donc le plus sûr. Au-dessous de quatre
points, on se tait : deux mots communs sont du bruit, et un rapprochement de
trop ferait douter de tous les autres. Trois résultats au plus, sinon la
remarque devient un écran de recherche, qui existe déjà et fait cela mieux. Le
rapprochement se fait en mémoire sur les trois cents notes les plus récentes :
la borne dit jusqu'où l'on va, plutôt que de laisser l'écran ralentir sans
prévenir.

**Une obsession est « dormante » après six semaines sans une note**, et la
mesure est `updatedAt`. La maquette appelait dormante une obsession de *moins de
deux notes* — un raccourci de prototype qui aurait signalé les obsessions
neuves au lieu de celles qu'on délaisse, soit exactement l'inverse.

**Une routine se repose, une liste s'épuise.** Tout tient dans la **période** :
`Cadence::periodOf()` rend une étiquette (`2026-09-07`, `2026-W37`, `2026-09`),
et deux cochages comptent pour la même période si et seulement si leurs
étiquettes sont égales. Cocher n'efface donc rien — cela vaut jusqu'à la période
suivante, où tout se repose. C'est la seule différence de fond avec une liste de
tâches, où ce qui est coché le reste.

**Chaque étape porte son propre calendrier**, pas la routine entière :
« rafraîchir le levain » tous les jours et « relever le pH » le samedi
cohabitent. Sans jour nommé, une étape est due n'importe quel jour de sa période
— « une fois cette semaine, quand on veut » —, la période ne bougeant pas pour
autant. Les jours sont en **ISO-8601** (1 lundi, 7 dimanche), comme `format('N')`.

**Changer de cadence oublie les calendriers et les cochages.** Des jours réglés
pour une routine hebdomadaire ne veulent plus rien dire une fois quotidienne ;
les garder les ferait réapparaître au retour, sans que personne ne les ait
redemandés. Les cochages, eux, étaient rangés par période, et les périodes
viennent de changer de nature.

**La série se calcule sur les étapes d'aujourd'hui**, en remontant les périodes
entièrement faites. Ce qui était dû il y a trois mois est irrécupérable — les
étapes ont pu changer depuis. Elle répond donc à « depuis quand tiens-tu la
routine *telle qu'elle est* ? », seule question à laquelle on puisse répondre
honnêtement. La période en cours ne compte que si elle est déjà finie, sinon la
série tomberait à zéro chaque matin. Une période où rien n'était dû rompt la
série plutôt que de l'allonger : une série qui traverse des périodes vides ne
dit plus rien de ce qu'on tient.

**Une routine retient le nom de l'obsession qu'elle sert**, pas son identifiant
— comme un rappel retient un sujet. Le slug, lui, est une règle de Notebook :
c'est le port partagé `ObsessionDirectory` qui le rend, et **`null` quand
l'obsession n'est mentionnée par aucune note**. Le lien n'apparaît alors pas :
une obsession n'est pas créée, elle est mentionnée, et conduire à un écran vide
promettrait ce qui n'existe pas.

**Une capture ignore ce qu'elle deviendra**, comme un rappel ignore ce qu'il
porte. La boîte de réception est un sas : ce qui y tombe n'est ni une note ni
une tâche, et trier ne transforme rien — cela écrit ailleurs, puis **la capture
disparaît**. Écarter, classer en note, classer en tâche : trois issues, aucune
survivance. Une boîte qu'on ne peut pas vider cesse d'être une boîte, et ce qui
mérite d'être gardé se classe plutôt qu'il ne s'archive.

Elle est immuable : on la trie ou on l'écarte, on ne la corrige pas — corriger,
c'est déjà l'avoir sortie de la boîte. Son titre est la première ligne coupée à
soixante-dix caractères, une étiquette de liste ; le texte, lui, est conservé
entier.

**Inbox n'écrit ni note ni tâche lui-même** : il passe par les ports partagés
`NoteWriter` et `TaskWriter`, que Notebook et Task implémentent en appelant
**directement leur gestionnaire**, sans repasser par le bus. On est déjà dans la
transaction du tri, et il faut que ce qui est écrit et la capture retirée
tiennent ou tombent ensemble ; un second envoi ouvrirait une transaction dans la
transaction. L'ordre compte : l'écriture d'abord, de sorte qu'un plafond de
notes atteint fasse tomber la transaction et **laisse la capture dans la
boîte** — la perdre pour une note qui n'a pas pu s'écrire serait la pire issue.

**Le partage entrant n'a pas de jeton CSRF, et ne peut pas en avoir.** Le
`share_target` du manifeste fait poster le système d'exploitation sur
`/partage` ; il n'a jamais vu notre page. Ce que cela ouvre est une ligne de
plus dans la boîte, chez quelqu'un de déjà connecté, à l'endroit même prévu pour
trier ce qui vient d'ailleurs : rien n'est écrit dans le carnet, rien n'est
modifié, rien n'est supprimé, et le tri reste protégé. La route répond **303**,
pour que revenir en arrière ne rejoue pas le partage, et son adresse n'est pas
localisée — elle est inscrite dans le manifeste installé sur l'appareil.
*Limite connue* : partager hors session mène à l'écran de connexion et le
contenu partagé est perdu.

**Un rappel ne connaît ni la note ni la tâche qu'il porte.** Son sujet est une
chaîne (`note:<uuid>`, `task:<uuid>`), son destinataire un identifiant résolu à
l'envoi par le port `AccountDirectory`. Un sujet ne porte qu'un rappel : reposer
une échéance déplace celle qui existe, plutôt que d'en empiler une seconde.

**Une clé d'API suit la personne, pas l'organisation** : c'est sa clé, c'est sa
facture. `AssistantSettings` n'a pas d'`organization_id`, et son identifiant de
propriétaire *est* sa clé primaire — une personne, un réglage.

**La clé n'existe en clair nulle part dans le domaine.** L'agrégat manipule un
`SealedKey` et ignore comment on le descelle ; le `KeyVault` chiffre en
XSalsa20-Poly1305 avec `ASSISTANT_SECRET`. Ce que cela protège : le vol de la
seule base. Ce que cela ne protège pas : base + secret ensemble. Un chiffrement
de bout en bout est impossible — personne n'est là pour saisir un mot de passe
quand le serveur appelle le modèle. La déclaration de l'écran des réglages le
dit ainsi, et ne doit pas être adoucie.

**Le consentement se vérifie avant tout le reste**, et l'ordre n'est écrit
qu'à un seul endroit : `Assistant\Application\Completion`. Consentement,
puis palier, puis réglages, puis clé — sans consentement rien n'est lu, rien
n'est déchiffré, et l'on ne révèle même pas qu'une clé existe. Chaque appelant
qui referait cette séquence pour son compte finirait par en intervertir deux
lignes, un jour, sans que rien ne le signale.

**La matière est passée close** (`\Closure(): string`), jamais déjà lue :
`Completion` ne l'ouvre qu'une fois les vérifications passées. C'est ce qui
fait tenir « sans consentement, rien n'est lu » même quand l'appelant, lui,
ignore l'ordre des vérifications. Le port partagé `WritingAssistant` a la même
signature, pour la même raison — et traduit `AssistantRefused` en
`AssistantUnavailable`, un contexte appelant n'ayant pas à dépendre des
exceptions d'Assistant.

**Changer de fournisseur efface la clé et le modèle.** Une clé Anthropic n'ouvre
rien chez OpenAI et « claude-haiku » n'y existe pas : les garder ne ferait
qu'échouer plus tard, à un endroit où l'on ne comprendrait plus pourquoi. De
même, seul un fournisseur local prend une adresse — la laisser changer pour un
fournisseur hébergé permettrait de détourner la clé vers un serveur tiers.

**L'adresse d'un fournisseur local n'appartient pas au compte.** Un fournisseur
« local » tourne sur la machine de la personne, que le serveur ne peut pas
joindre : une adresse saisie là ne peut désigner que l'intérieur de *notre*
réseau — la base de données, un service voisin, le point de métadonnées de
l'hébergeur. Le champ libre faisait donc du serveur un relais, c'est-à-dire une
requête forgée côté serveur écrite dans un écran de réglages.

La liste est celle de l'exploitant (`ASSISTANT_LOCAL_URLS`, **vide par
défaut**), et la règle est une **égalité** de chaîne : ni appartenance à un
réseau, ni préfixe d'hôte, ni chemin — aucune résolution de nom, aucun `..` et
aucune redirection ne contourne une égalité. Elle est vérifiée **deux fois**,
comme le plafond de places : à l'enregistrement, et à l'appel — retirer une
adresse de la liste doit la faire cesser d'être appelée sans qu'il faille aller
nettoyer les réglages de chaque compte. Sans aucune adresse autorisée, le
fournisseur local **n'est pas proposé du tout**, comme la ligne « notifications
système » sans clés VAPID.

**Un abonnement poussé suit la personne, pas l'organisation** : `PushSubscription`
n'a pas d'`organization_id`, comme `PrivacyChoices`. Un navigateur ne se
dédouble pas selon l'organisation dans laquelle on travaille. Le point de
réception fait l'identité de l'appareil — un navigateur qui renouvelle ses clés
garde la même adresse, et doit être mis à jour plutôt que dupliqué, sinon chaque
rappel partirait en double.

**Un canal de notification muet n'est pas une erreur.** `NotifyEveryChannel`
appelle tous les canaux tagués ; sans clés VAPID ou sans appareil abonné, le
canal poussé renonce et retourne `false`. Une exception à cet endroit
empêcherait le courriel de partir, alors que c'est justement lui le filet.

**`CalendarFeed` est le seul agrégat délibérément non cloisonné.** Un agenda ne
se connecte pas : il récupère une adresse. Le jeton, long et renouvelable, tient
donc lieu d'authentification, et le contrôleur lit ensuite les rappels *dans*
l'organisation du flux, par `TenantScope::runAs()`.

**Une obsession n'est pas créée, elle est mentionnée.** Elle existe dès qu'une
note la porte ; l'agrégat `Obsession` n'est que sa *fiche éditoriale*, et son
absence est un état normal. C'est ce qui évite toute synchronisation :
étiqueter une note ne crée rien, retirer la dernière note ne casse rien. Une
fiche vidée de son contenu est supprimée plutôt que conservée vide.

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

**Abonnement.** Un par organisation, y compris l'espace personnel : c'est
l'organisation qui délimite ce qu'on voit, donc ce qui se facture. Trois
paliers — gratuit (cinquante notes, ni assistant ni équipe), personnel à prix
fixe, équipe au membre. Toute organisation naît avec quatorze jours d'essai sur
le palier personnel, sans carte.

**Les places se comptent, comme les notes.** `Subscription::memberAllowance($now)`
donne le nombre de personnes qui tiennent dans l'organisation : les places
payées sur le palier équipe, une seule partout ailleurs — le personnel est à
prix fixe, et le tenir pour extensible reviendrait à servir une équipe au prix
d'une personne. **L'essai en ouvre cinq** : avec une seule, une organisation
créée pour essayer l'équipe ne pourrait inviter personne.

Le plafond est vérifié **deux fois**, et il le faut. À l'invitation, il compte
les invitations en attente avec les membres, sans quoi vingt invitations parties
pour trois places entreraient toutes, une à une. À l'acceptation, il compte de
nouveau : entre l'envoi et le clic, l'abonnement a pu retomber. Là, la question
porte sur une organisation **nommée** (`Entitlements::memberAllowanceOf()`) et
non sur l'organisation courante — celui qui accepte est encore dans la sienne,
et l'interroger donnerait le plafond du mauvais abonnement.

Un refus à l'acceptation ne consomme pas l'invitation : elle vaudra encore le
jour où une place se paie. Et personne n'est jamais renvoyé — une équipe
redescendue sous le nombre de ses membres cesse d'inviter, elle ne se vide pas.
Après la connexion, `AcceptPendingInvitationListener` traite l'équipe complète
comme un refus ordinaire : faire échouer la connexion elle-même serait hors de
proportion.

**Tout tient dans `Subscription::entitledPlan($now)`** : essai fini, paiement
échoué, période dépassée donnent la même réponse — le gratuit. **Jamais rien de
moins.** Un plafond arrête l'écriture ; il ne rend jamais un carnet illisible ni
inexportable. Les autres contextes interrogent le port partagé `Entitlements`,
et `PlanLimitReached` est traduit en message par `PlanLimitListener` plutôt que
rattrapé dans chaque contrôleur.

**« En cours » vaut pour un palier payé, pas pour celui que l'essai prête.**
Pendant l'essai on est sur le personnel sans l'avoir payé : désactiver son
bouton interdirait de convertir l'essai en abonnement, et il faudrait attendre
que l'essai expire pour pouvoir payer.

**Rien n'est activé au retour du paiement.** `?paye=1` dit seulement que le
prestataire a pris le paiement et que le palier s'ouvre d'un instant à l'autre ;
c'est le webhook signé qui fait foi. Sans ce mot, l'écran afficherait l'ancien
palier sans rien dire, et l'on croirait le paiement perdu.

**Le prestataire fait foi sur l'état commercial** : prorata, relances, cartes
expirées. On ne recalcule rien, on enregistre ce que le webhook signé raconte —
et l'on répond 200 même à un événement qu'on ignore, sans quoi Stripe le
réessaierait indéfiniment. Le paiement non configuré n'empêche rien : l'essai
puis le gratuit fonctionnent, et l'écran cache ses boutons.

**Équipes.** Une organisation d'équipe s'ouvre par n'importe qui, qui en devient
propriétaire ; l'espace personnel créé à l'inscription ne s'invite ni ne se
partage. Les rôles passent par `OrganizationVoter` (`MANAGE_MEMBERS`,
`ADMINISTER`) et non par un rôle global : ce qu'on a le droit de faire dépend de
l'organisation courante, et la même personne peut y être propriétaire ici et
simple membre ailleurs. Le voteur se teste directement — forcer la route dans un
test fonctionnel se heurterait d'abord au jeton CSRF, ce qui ne prouverait rien
de l'autorisation.

**Une invitation n'est pas cloisonnée**, comme `CalendarFeed` : celui qui
accepte n'est pas encore membre, et le filtre — armé sur *son* organisation
courante — masquerait l'invitation qu'il vient de recevoir. Ce qui protège est
double : le jeton, et **l'adresse**. `Invitation::acceptedBy()` refuse un compte
dont l'adresse n'est pas celle qui a été invitée, de sorte qu'un lien transféré
ne fait entrer personne. On n'invite jamais comme propriétaire — celui-ci se
transmet, sans quoi la règle « au moins un propriétaire » n'aurait plus d'effet.

**Sécurité.** L'agrégat `User` n'implémente ni `UserInterface` ni les interfaces
du bundle de double authentification : l'adaptateur `SecurityUser` le fait à sa
place. Les habilitations fines dépendront de l'organisation courante et
passeront par des voteurs, pas par un rôle global.

Trois authentificateurs cohabitent sur le pare-feu `main` : mot de passe,
second facteur, fournisseur externe. Toute connexion programmée doit donc
**nommer** l'authentificateur (`$security->login($user, 'form_login')`), sinon
Symfony refuse de choisir.

**L'adresse de connexion se change en deux temps** : la nouvelle attend d'être
confirmée depuis sa propre boîte aux lettres (`User::pendingEmail()`), sans quoi
une faute de frappe fermerait le compte à son propriétaire. Deux courriels
partent — le lien vers la nouvelle adresse, **un avertissement vers l'ancienne**,
qui est la seule alerte du propriétaire légitime si sa session a été dérobée.
Le lien est signé et porte l'empreinte de la demande en cours : se raviser ou
annuler le rend caduc. Et comme **l'identifiant de connexion *est* l'adresse**,
le contrôleur rouvre la session après le changement — sinon confirmer son
adresse déconnecterait à la requête suivante.

**Une invitation se découvre avant d'avoir un compte.** `/invitations/{token}`
est publique : sans session, l'écran dit de quoi il retourne et met le jeton de
côté (`PendingInvitation`), qu'un écouteur reprend à la connexion — y compris
celle qui suit une inscription, `Security::login()` émettant bien
`InteractiveLoginEvent`. Le jeton est **retiré** de la session en le lisant :
sans cela il rejouerait à chaque connexion suivante. Le lien « créer le compte »
emporte l'adresse invitée en paramètre, pour pré-remplir seulement — elle
n'ouvre rien par elle-même, l'agrégat la vérifie.

**Sessions.** Elles vivent en base (`session.handler.pdo`, table `sessions`),
pas dans des fichiers : c'est ce qui rend la révocation réelle — fermer une
session depuis un autre appareil doit la fermer, pas seulement l'ôter d'une
liste. `LoginSession` en est l'index lisible ; **sa clé primaire est
l'identifiant de la session PHP**, sinon il n'y aurait rien à détruire. La date
de dernière vue n'est rafraîchie qu'au quart d'heure : l'écrire à chaque requête
coûterait une écriture par clic pour une précision dont personne n'a l'usage.
L'appareil est déduit de l'en-tête du navigateur, jamais d'une adresse IP — une
ville devinée qui se trompe est pire qu'une ville absente quand il s'agit de
décider d'une révocation.

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

**En-têtes de sécurité.** `config/packages/nelmio_security.yaml` les porte tous,
et le Caddyfile répète les valeurs constantes pour les fichiers que PHP ne voit
jamais. **Le CSP ne peut pas vivre ailleurs que dans l'application** : son nonce
change à chaque requête. `script-src` n'accepte donc que `'self'` et le nonce de
la requête — ni `'unsafe-inline'`, ni `'unsafe-eval'`, ni `data:`, et c'est
`tests/Functional/Shared/SecurityHeadersTest.php` qui le tient, y compris en
sabotant le réglage pour vérifier que le test tombe.

`style-src` garde `'unsafe-inline'`, et c'est assumé : la maquette calcule des
valeurs par élément — largeur d'une jauge, opacité des marques —, un nonce ne
s'applique pas à un attribut `style`, et NelmioSecurityBundle ne sait pas écrire
`style-src-attr`. Ce qu'on perd suppose déjà une injection de balise ; ce qu'on
garde, la défense contre l'exécution de script, n'en suppose aucune.

**Rien ne doit passer par une adresse `data:`.** AssetMapper traduit un
`import './x.css'` depuis du JavaScript en une entrée
`data:application/javascript` de l'importmap — qu'il faudrait autoriser dans
`script-src`, où `data:` annule la protection du nonce (un `<script
src="data:…">` injecté serait alors accepté sans en porter aucun). Les deux
feuilles de style sont donc chargées par des balises `<link>` dans
`base.html.twig`, et `assets/controllers.json` refuse l'`autoimport` de
`live.min.css`. Un test le vérifie ; sans lui, un `import` de CSS ajouté un jour
ferait échouer *tous* les contrôleurs Stimulus, et seulement en production.

**Le polyfill d'importmap est désactivé** (`importmap_polyfill: false`) : Symfony
le charge sinon depuis ga.jspm.io, c'est-à-dire l'adresse IP de chaque visiteur
envoyée à un tiers — exactement ce qui a fait auto-héberger les polices.

**Un gestionnaire d'événement en attribut est du JavaScript en ligne.** Il n'y a
plus de `onchange="…"` dans les gabarits : `autogrow#save` et le contrôleur
`submit-on-change` font le travail. Un attribut ne peut pas porter de nonce, donc
le CSP le bloque, sans autre symptôme qu'un champ qui n'enregistre plus.

**`trusted_hosts` est armé en production.** L'en-tête `Host` est recopié dans les
adresses absolues, dont les liens de réinitialisation envoyés par courriel : un
`Host` falsifié ferait partir vers la boîte de la victime un lien pointant chez
l'attaquant. `TRUSTED_HOSTS` doit suivre `SERVER_NAME` ; la boucle locale est
ajoutée par la configuration elle-même, faute de quoi la sonde de disponibilité
recevrait 400 à chaque battement.

**La demande de réinitialisation est gardée deux fois, et pas contre la même
chose.** C'est le seul écran qui fait partir un courriel vers une adresse nommée
dans la requête, sans session ni compte. Le **jeton CSRF** empêche un autre site
de déclencher l'envoi depuis le navigateur d'un passant ; la **limitation**
empêche d'en faire une boucle — par adresse IP on arrête l'auteur et on le lui
dit (429), par adresse de courriel on protège une boîte et l'on ne dit rien.
L'écran répond la même chose dans tous les cas, sinon il redeviendrait
l'annuaire que le message unique évite justement d'être.

**Le conteneur de production ne tourne pas sous root.** `www-data`, avec
`CAP_NET_BIND_SERVICE` posée par `setcap` sur le seul binaire FrankenPHP pour
qu'il ouvre encore les ports 80 et 443. `var/` est le seul répertoire que
l'application peut écrire : un code qui ne peut pas se réécrire ne peut pas se
rendre persistant. Le `USER` est posé à la **fin** de l'étape `prod` — les
étapes précédentes installent et compilent encore. L'API d'administration de
Caddy est coupée (`admin off`) : rien ne s'en sert, et depuis que PHP n'est
plus root elle serait le chemin le plus court entre une exécution de code et la
maîtrise du serveur.

**La sonde de disponibilité a son propre point d'écoute**, sur la boucle locale
(`127.0.0.1:2020`), qui ne sert que `/healthz`. Le site public ne répond qu'à
son propre nom d'hôte : en production `SERVER_NAME` vaut `focusyn.fr`, et la
sonde — qui interroge forcément « localhost » depuis l'intérieur du conteneur —
n'y trouvait aucun site. Le conteneur était déclaré mort en permanence, et rien
ne le signalait : la CI ne lance pas la sonde.

**`preload` est déclaré dans HSTS, mais ne fait rien par lui-même** : il annonce
seulement que le domaine remplit les conditions de la liste embarquée dans les
navigateurs. C'est la soumission sur hstspreload.org qui engage, elle se fait à
la main, et en sortir prend des mois. Ce qui engage déjà, en revanche, c'est
`includeSubDomains` : tout sous-domaine devra parler HTTPS.

**Permissions-Policy ne liste que ce que les navigateurs reconnaissent.** Une
fonction non implémentée n'est pas refusée « en avance » : la directive est
ignorée, et chaque page écrit un avertissement dans la console. Trois
avertissements permanents apprennent à ne plus lire la console.

**Interface.** Rendu serveur en Twig. Partage du travail entre les deux outils
front, à respecter strictement :

- **Live Component** dès qu'une interaction touche l'état du serveur (recherche,
  cochage d'une tâche, bascule d'un réglage, enregistrement d'un rappel).
  Ils se testent avec `InteractsWithLiveComponents` : `createLiveComponent()`
  puis `->call('action', [...])`, sans navigateur ;
- **Stimulus** pour l'état purement présentationnel, local à l'onglet (ouverture
  d'un menu, mode focus). Faire un aller-retour réseau pour ouvrir un menu
  serait un gaspillage visible à l'œil.

**Une section d'écran appartenant à un autre contexte est un composant Twig,
pas un bloc de gabarit.** `ShowSettingsController` vit dans Identity et n'a pas
le droit de connaître Privacy ni Reminder : ces contextes exposent
`PrivacySection`, `CalendarFeedSection`, `ReminderChip`, que les gabarits
appellent. Les gabarits ne sont pas analysés par deptrac — c'est le contrôleur
qui doit rester propre.

**Un contrôleur Stimulus n'écoute que son propre sous-arbre.** La pastille de
rappel vit dans la liste des tâches, le dialogue à la fin de la page : un
`data-action` sur la pastille ne l'aurait jamais atteint. L'écoute est déléguée
au document, ce qui a un second mérite — les lignes de tâches sont remplacées à
chaque action du Live Component, et une écoute posée sur elles disparaîtrait
avec elles.

Seul l'éditeur de note échappe aux deux : CodeMirror 6 piloté par Stimulus
(`assets/controllers/note_editor_controller.js`), parce qu'un aller-retour par
frappe serait inutilisable. Il sauvegarde en différé vers un point d'entrée
JSON, dont le jeton CSRF voyage dans l'en-tête `X-CSRF-Token` — l'attribut
`#[IsCsrfTokenValid]` lit un paramètre de formulaire, pas un en-tête, d'où une
validation explicite dans `SaveNoteBodyController`.

Les couleurs et tailles de l'éditeur sont lues depuis les variables CSS du
design system : aucune valeur en dur dans le JavaScript.

**L'éditeur et l'aperçu sont le même rendu.** C'est le parti pris de la
maquette : chaque ligne de CodeMirror reçoit la classe `fx-prose__line--*` que
porte l'aperçu, et chaque fragment la classe `fx-prose__mark|strong|emphasis…`.
La feuille de style décide seule — **rien n'est recopié en JavaScript**. Les
marques markdown sont en monospace des deux côtés ; les avoir laissées en serif
dans l'éditeur était le défaut le plus visible du projet. Conséquence heureuse :
l'opacité des marques et le choix serif/sans s'appliquent à l'éditeur sans qu'il
les lise, alors qu'une valeur lue à l'ouverture restait figée.

La nature d'une ligne vient de l'arbre syntaxique de CodeMirror, jamais d'une
expression régulière — et la décoration se recalcule aussi quand *l'analyse
avance*, pas seulement quand le texte change : sans cela une ligne devenue titre
attendait la frappe suivante pour le devenir à l'écran.

**Le panneau d'aperçu est rendu par le serveur**, jamais reconstruit côté
client : `MarkdownOutline` découpe le corps en lignes, et le point d'entrée de
sauvegarde renvoie l'aperçu déjà rendu, que le contrôleur Stimulus substitue.
Écrire un second analyseur markdown en JavaScript créerait deux vérités qui
finiraient par diverger. Le même gabarit `notebook/_prose.html.twig` sert à
l'aperçu (`marks: false`) et servira à tout rendu figé.

**Responsive, pas de bascule d'appareil.** La maquette propose un interrupteur
DESKTOP/MOBILE : c'était un outil d'aperçu du logiciel de design, pas une
fonction du produit. La coquille utilise un seul balisage et une requête média
à 900 px. Ne pas réintroduire l'interrupteur.

**Coquille et accueil sont agrégés, pas devinés.** `AggregatedShellDataProvider`
et `AggregatedHomeDataProvider` (dans `Shared/Infrastructure/`) rassemblent ce
que chaque contexte expose pour la navigation et l'accueil, derrière des ports
de la couche Application : un gabarit n'interroge jamais un contexte
directement, et ajouter une source ne touche aucun gabarit. Ils ont remplacé
les fournisseurs de données de la maquette, qui n'existent plus.

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

Cette règle n'était pas tenue : trente et une déclarations lisaient une
primitive, et **cela ne s'est vu qu'en allumant le thème sombre** — un titre de
note en `--fx-ink-750` reste noir sur fond noir. C'est le genre de fuite
qu'aucun test ne rattrape et qu'aucune relecture ne remarque tant qu'il n'y a
qu'un thème. En ajouter un est le seul moyen de la rendre visible.

**Le thème sombre tient dans `light-dark()`.** Chaque jeton sémantique porte
ses deux valeurs côte à côte, et `color-scheme` décide laquelle s'applique :
`light dark` s'en remet à l'appareil, une valeur unique le force. Pas de bloc
sombre dupliqué — une fois pour le choix explicite, une fois sous
`prefers-color-scheme` —, où l'oubli d'un jeton dans l'une des deux copies ne
se verrait que sur un écran, un jour, chez quelqu'un.

Deux rampes de primitives cohabitent donc : `--fx-neutral-*` / `--fx-ink-*` le
jour, `--fx-night-*` / `--fx-chalk-*` la nuit. Les paliers se répondent un à un
(`chalk-900` tient le rôle de `ink-900`), et les nombres croissent dans les deux
cas **en s'éloignant du fond**. Les cinq accents ont chacun leur jumelle de nuit
(`--fx-accent-slate-night`…), dessinée par la maquette et non calculée : un
`color-mix` uniforme donnerait des contrastes inégaux d'une teinte à l'autre.

**Le réglage a trois valeurs, pas deux** : `system`, `light`, `dark`.
`system` n'est pas l'absence de choix mais un choix à part entière, et le seul
qui suive l'appareil au fil de la journée ; le ramener à « clair » dès qu'on a
basculé une fois interdirait d'y revenir. L'écran des réglages ne propose donc
« suivre l'appareil » que lorsqu'on lui a pris la main.

**Le serveur ignore la préférence de l'appareil**, et c'est heureux : elle
changerait sans qu'aucune requête n'en avertisse. Une seule conséquence à
assumer — réglé sur `system`, l'interrupteur rendu serait éteint sur un écran
sombre. `theme_switch_controller.js` corrige alors sa position *et* la valeur
qu'il postera. Sans JavaScript la bascule reste juste, elle passe seulement par
« sombre » d'abord.

La couleur du chrome du navigateur (`<meta name="theme-color">`) est la seule
teinte que le serveur choisit, faute de pouvoir la déléguer au CSS. Sans thème
choisi, les deux sont déclarées avec leur `media`.

**L'accent par défaut est l'ardoise** (`--fx-accent-slate`, #41586e), comme la
maquette. « Encre » reste un choix possible dans les réglages, mais ce n'est pas
le réglage d'origine.

`devenv shell -- shots` capture une troisième passe, `*-sombre.png`, en
`prefers-color-scheme: dark`. Le thème par défaut suivant l'appareil, cela suffit
à voir le sombre sans toucher au réglage du compte.

**Les écrans d'authentification partagent `identity/_auth_frame.html.twig`** et
suivent une composition fixe : marque, phrase en serif 24 px, champs **sans
étiquette visible** (placeholder + `aria-label`), bouton pleine largeur, recours,
puis une ligne de message dont la hauteur est réservée en permanence — sinon
l'apparition d'une erreur fait sauter le formulaire au moment du clic.

**La graisse par défaut est 400, partout.** La maquette n'emploie 600 qu'à trois
endroits, tous le mot « Focusyn » (`--fx-weight-brand`), et 600/650 pour les
titres **à l'intérieur** d'une note (`--fx-weight-heading`, `--fx-weight-strong`).
Un titre d'écran en 600 se lit comme une autre famille typographique : c'est
l'erreur la plus visible qu'on puisse commettre ici.

Deux échelles de titres, à ne pas confondre :

| Rôle | Jeton | Graisse |
| --- | --- | --- |
| Accroche d'accueil, titre d'obsession | `--fx-title-hero` (40 px) | 400 |
| Titre d'écran | `--fx-title-screen` (32 px) | 400 |
| Titre de dialogue et d'authentification | `--fx-title-dialog` (24 px) | 400 |
| Titre de ligne de note | `--fx-title-row` (20 px) | 400 |
| Titres `#`/`##`/`###` dans une note | `--fx-prose-h1/h2/h3` | 600/600/650 |

**Le serif est réservé à ce qui a été écrit** — titres d'écran, titres de note,
prose, compteurs mis en avant. Un extrait de note est en Archivo : deux serifs
superposés se disputeraient l'attention. La monospace ne sert qu'aux
métadonnées (dates, décomptes, étiquettes capitales).

**Les réglages d'affichage ne recalculent aucun style.** Accent, prose serif,
densité, opacité des marques et panneau d'aperçu sont portés par le compte
(`DisplayPreferences`, dans Identity) et rendus en attributs `data-fx-*` sur
`<html>` par le gabarit de base, via le port `CurrentDisplay`. Le design system
en tire seul les conséquences. Hors session, les valeurs d'origine du design
s'appliquent.

**Les polices sont auto-hébergées** dans `assets/fonts/`, déclarées par
`assets/styles/base/fonts.css`. Ne pas réintroduire le CDN Google : il reçoit
l'adresse IP de chaque visiteur, ce qu'un produit affichant une section RGPD ne
peut pas se permettre. Les trois familles sont variables — une déclaration par
plage de graisses, jamais par graisse isolée, sinon le navigateur simule les
autres et cela se voit.

`/_design-system` (dev uniquement) rend tous les blocs avec leurs variantes.

**Vérifier le rendu, ne pas le déduire.** `devenv shell -- shots` sème un compte
de démonstration (`app:demo`) puis capture chaque écran en 1280 et 390 px dans
`var/screenshots/`. C'est ainsi qu'ont été trouvés une barre d'onglets mobile
visible sur bureau, un fil d'Ariane jamais rendu et une erreur 500 sur l'accueil
qu'aucun test ne voyait. Comparer les captures à `project/Focusyn.dc.html`.

**Chaque écran a sa propre mesure** (`fx-app__content--home`, `--library`,
`--editor`, `--list`, `--settings`…) : la largeur de lecture suit la nature du
contenu, elle n'est pas uniforme.

**Les bascules d'affichage vivent dans `layout/responsive.css`, importée en
dernier.** Une requête média n'ajoute aucune spécificité : placée plus haut,
elle se fait écraser par n'importe quel `display` déclaré après.

## PWA

`public/manifest.webmanifest`, `public/sw.js` et `public/icons/` sont des
fichiers statiques, hors AssetMapper : le service worker doit être servi depuis
la racine, sans condensat dans son nom, pour contrôler toute l'origine et rester
détectable à la mise à jour. Le Caddyfile lui impose `Cache-Control: no-cache`.

Stratégies du service worker : réseau d'abord pour les navigations avec repli
sur `/offline`, cache d'abord pour `/assets/*` (versionnés par condensat, donc
immuables). Pas d'écriture hors ligne — écartée à la conception.

Il porte aussi les notifications poussées (`push`, `notificationclick`). La
paire de clés VAPID se tire par `console app:vapid`, **une seule par
déploiement** : en changer invalide tous les abonnements déjà pris. Sans clés,
la ligne « notifications système » ne se rend pas du tout — proposer un
interrupteur qui ne peut rien faire serait pire que de ne rien proposer.

En touchant aux icônes, penser à `tests/Unit/Shared/Pwa/ManifestTest.php` :
c'est le seul lien entre le manifeste et les fichiers qu'il déclare.

## Le dépôt et la chaîne de publication

`terraform/github/` décrit le dépôt GitHub et sa sécurité. Ce qui se règle dans
une interface se dérègle sans laisser de trace : personne ne sait quand la
protection de `main` a été désactivée « cinq minutes ». Ici, `tofu plan` le dit.

Partage du travail, à ne pas confondre : **Terraform tient la politique** — ce
que le dépôt autorise —, **`.github/workflows/` tient la chaîne de
construction**, et **le contenu des fichiers reste dans le dépôt**. Faire poser
`dependabot.yml` ou `CODEOWNERS` par Terraform les ferait entrer par l'API,
c'est-à-dire en contournant la protection que ce même Terraform installe.

**Un tag de version est immuable, et c'est une condition, pas un raffinement.**
Une attestation de provenance dit « cet artefact vient de ce commit, à ce tag ».
Si le tag peut être déplacé, l'attestation continue de dire vrai tout en
désignant autre chose : `v1.2.0` cesse d'être une version pour devenir un nom de
variable.

**La chaîne de publication ne porte aucun secret de longue durée.** Elle
s'authentifie auprès de GHCR avec le jeton de l'exécution, qui expire avec elle,
et signe par OIDC auprès de Sigstore — une clé éphémère dont la trace est un
journal de transparence public. Il n'y a rien à faire fuiter et rien à faire
tourner. Chaque workflow part de `permissions: {}` et redemande nommément ce
dont il a besoin, tâche par tâche.

**Une fusion sur `main` peut publier une version**, et c'est ce qui décide de
tout le reste. Pas toutes : la nature des commits tranche — `feat` monte le
mineur, `fix` le correctif, `chore` et `docs` ne publient rien. Personne ne pose
de tag à la main ; l'usine les pose. Les messages de commit sont donc devenus
une **entrée de la chaîne de publication** et non une affaire de style : c'est
pourquoi GrumPHP les valide au moment où on les écrit, plutôt que trois jours
plus tard en se demandant pourquoi rien n'est sorti.

**Tout tient dans une seule exécution, et il le faut.** Un tag créé avec le
`GITHUB_TOKEN` ne déclenche aucun workflow — GitHub s'en protège pour éviter les
boucles. Enchaîner « semantic-release pose le tag → un second workflow
construit » s'arrêterait en silence, à moins d'un jeton d'application à demeure,
c'est-à-dire le secret de longue durée que cette chaîne évite depuis le début.

**On reste en 0.x tant qu'on ne décide pas d'en sortir.** Une rupture monte le
mineur au lieu du majeur (`releaseRules` dans `.releaserc.json`) : le produit
annonce « v0.1 » dans sa coquille, et publier un 1.0.0 promettrait une stabilité
d'interface et de schéma qu'il n'a pas. En sortir sera une décision, pas un
effet de bord — il suffira de retirer cette règle.

**Le constructeur reçoit la version en entrée**, il ne la déduit plus de
`github.ref` : on construit sur `main`, où le tag n'existe pas encore. Il ne
naîtra qu'après que l'image aura été poussée, éprouvée et attestée — une version
annoncée sans artefact vérifiable serait pire qu'une version en retard.

**Un tag ne se supprime pas, même par son propriétaire.** La règle
d'immuabilité refuse le `DELETE` de l'API en 422, et aucun contournement n'est
déclaré. Corollaire à connaître avant de s'amuser : un tag d'essai reste pour
toujours, et le retirer demande de désarmer la règle le temps d'un `apply`.

**Un tag créé par l'API passe `required_signatures`**, parce qu'il est *léger* :
il n'y a pas d'objet à signer, seulement une référence vers un commit qui, lui,
est signé. C'est ce qui permet à l'usine de poser les tags, et c'est aussi la
limite de cette règle — elle contraint les poussées humaines, pas l'API.

**Une version publie trois choses, et la troisième vérifie les deux autres** :
l'image, sa provenance SLSA, son inventaire — puis la chaîne relit sa propre
attestation avec `gh attestation verify`, l'outil qu'emploierait n'importe qui.
Une signature qu'on ne sait pas relire ne protège personne.

**La construction n'a pas lieu dans ce dépôt, et c'est toute la différence entre
le niveau 2 et le niveau 3 de SLSA.** Le niveau 3 demande que le matériel qui
signe la provenance soit hors de portée des étapes définies par le projet. Un
workflow réutilisable *local* (`uses: ./…`) ne l'obtient pas : il vient du même
commit que ce qu'il construit, celui qui écrit la release écrit le constructeur,
il n'y a pas deux parties mais une seule. `release.yaml` appelle donc un
workflow réutilisable d'un **dépôt séparé** (`builder/`, poussé vers
`<compte>/focusyn-builder`), épinglé par empreinte, qui définit toutes les
étapes. Ce dépôt-ci ne fournit que des paramètres : il ne peut pas insérer
d'étape dans la tâche qui signe, donc il ne peut pas atteindre le jeton OIDC.

Ce que cela ne donne pas, et qu'il faut savoir : tant que la même personne
possède les deux dépôts, **l'isolation est technique, pas organisationnelle**.
Elle protège d'une dépendance compromise, d'une action tierce compromise, d'un
Dockerfile hostile, d'une étape ajoutée par erreur — pas de son propriétaire.

**`--signer-repo` est l'assertion qui compte** à la vérification : elle exige que
la signature vienne du constructeur. Sans elle, on vérifie qu'une signature
existe, pas qu'elle vient d'où l'on croit. Elle est dans les notes de chaque
version.

**Ce que publie une fusion est, par construction, du code passé par `main`** —
donc par les contrôles de CI qu'exige le ruleset, **et par eux seuls**. Aucune
approbation n'est requise : un dépôt à un seul auteur ne peut pas s'en donner.
Une provenance de niveau 3 sur du code non relu reste une provenance de niveau
3 ; c'est la phrase qui doit être exacte, pas la garantie qui doit être
gonflée.

**L'image est éprouvée avant d'être poussée, pas après.** L'ordre inverse
publiait l'artefact *et son attestation* avant que grype ne parle : une version
vulnérable existait alors sur le registre, signée et vérifiable, et seule la
release GitHub manquait. Le constructeur construit donc localement, scanne, puis
pousse — deux passes qui partagent le cache du builder dans la même exécution.

**Le constructeur ne lit aucun cache partagé.** `cache-from: type=gha` lui
donnerait des couches écrites par la CI de la branche par défaut, pour un autre
commit — exactement ce que la provenance prétend écarter en disant « cet
artefact vient de ce commit ».

**Rien de ce qui cherche les failles ne dépend d'une fonctionnalité propre à
GitHub.** gitleaks, syft et grype tournent depuis des images Docker épinglées :
elles donneraient le même verdict sur GitLab ou sur un portable. C'est la raison
pour laquelle CodeQL, Scorecard et la revue de dépendances restent absents —
**et non parce qu'ils seraient payants** : les dépôts sont publics, ils y sont
gratuits. Ce qui reste réellement découvert est le JavaScript des contrôleurs
Stimulus ; les workflows, eux, sont tenus par actionlint et par l'étape
« Droits déclarés et jeton non persisté ».

**Ce que le passage en public a apporté sans qu'on le demande** : l'analyse de
secrets native est active, avec **blocage à la poussée**. Elle arrête un secret
avant qu'il n'entre, là où gitleaks le trouve après coup ; le hook de
pré-commit, lui, l'arrête encore avant, sur le poste. Les trois se recouvrent
et c'est très bien — aucun n'est le filet de l'autre.

**Les règles de branche et de tag sont gratuites sur un dépôt public, payantes
sur un dépôt privé.** Rulesets *et* protection classique répondent le même 403 :
« Upgrade to GitHub Pro **or make this repository public** ». C'est ce qui a
décidé de la visibilité. `manage_rulesets` reste parce que la condition peut
changer : repasser en privé sans le basculer ferait échouer chaque `tofu plan`.

**L'épinglage par empreinte est désormais une règle du dépôt**
(`sha_pinning_required`), et non plus seulement une convention : GitHub refuse
lui-même un `uses:` référencé par étiquette. Une convention qu'on peut oublier
est devenue une condition qu'on ne peut pas contourner.

**Les workflows sont analysés comme le reste** : actionlint dans `qa`, et son
passage par shellcheck sur les blocs `run:`. Une variable non protégée dans un
script de CI est une injection comme une autre, avec les droits du dépôt.

## Pièges connus

- **`#[IsCsrfTokenValid]` vérifie aussi les requêtes GET.** Sur un contrôleur
  qui répond à `GET` et `POST`, l'écran cesse purement et simplement de
  s'afficher. Nommer les méthodes : `#[IsCsrfTokenValid('x', methods: ['POST'])]`.
- **Un jeton CSRF sans session a besoin de JavaScript.** La valeur rendue par
  `csrf_token()` pour un identifiant listé dans `stateless_token_ids` est le
  *nom* du jeton, que le contrôleur Stimulus `csrf-protection` remplace par un
  aléa qu'il pose aussi en cookie. Le champ doit donc porter
  `data-controller="csrf-protection"` — les formulaires Symfony l'ajoutent
  seuls, un `<input>` écrit à la main, non, et chaque envoi est alors refusé.
- **Le client de test réinitialise les services entre deux requêtes même avec
  `disableReboot()`.** Un adaptateur de cache étiqueté `kernel.reset` est donc
  vidé à chaque requête : un test qui croit vérifier une limitation de débit
  passe à vide. D'où un `ArrayAdapter` déclaré à la main, sans l'étiquette, pour
  `cache.rate_limiter` en test.
- **Symfony analyse *tous* les fichiers de `config/packages/` avant de décider
  lesquels s'appliquent.** Un `when@dev:` ne protège donc pas son contenu de
  l'analyse : un `!php/enum` désignant une classe absente en production — parce
  que la dépendance est de développement — fait échouer la construction de
  l'image, et seulement là. Écrire la valeur en chaîne quand le bundle sait la
  normaliser (`mode: migrate` pour Foundry).
- **Un bloc `when@prod:` n'est jamais exécuté en développement ni en test** :
  une option qui n'existe plus s'y conserve indéfiniment sans que rien ne le
  signale. C'était le cas d'`auto_generate_proxy_classes` et `proxy_dir`,
  retirés par DoctrineBundle 3, qui faisaient échouer l'étape « Image Docker »
  de la CI seule. Toucher à `when@prod:` demande de construire l'image.
- **L'image de base est figée par son étiquette, pas son contenu.** Sans
  `apk upgrade` avant `apk add`, `curl` et ses bibliothèques restent à la
  version livrée par l'amont — grype y trouvait des failles critiques toutes
  corrigées en amont.
- **`new X()->m()` (PHP 8.4) est interdit dans `src/`** : le parseur embarqué
  dans deptrac ne le comprend pas et *écarte le fichier* au lieu d'échouer — la
  règle d'architecture cesse silencieusement de s'y appliquer. `qa` refuse la
  syntaxe (étape `syntaxe`) et php-cs-fixer impose les parenthèses via
  `new_expression_parentheses`, qui neutralise la règle inverse de
  `@PHP84Migration`.
- **`Crawler::form()` sur un `<form>` n'envoie aucune valeur de bouton** : pour
  qu'un `<button name="cancel">` compte, il faut sélectionner le bouton
  (`filterXPath('//button[@name="cancel"]')->form()`), pas le formulaire.
- **`assertEmailCount()` prend un nom de transport en second argument**, pas un
  message d'explication — et lit le profil de la *dernière* requête : mesurer
  après un `followRedirect()` compte les courriels de la redirection, c'est-à-dire
  aucun.
- **La table `sessions` doit exister avant la première requête** : le
  gestionnaire PDO ne la crée pas tout seul en production. Elle est posée par
  la migration `Version20260906092152`, pas par `createTable()`, pour qu'elle
  se lise dans l'historique du schéma comme le reste.
- **`APP_SECRET` doit être non vide, y compris en dev** (`.env.dev`) : la
  protection CSRF le dérive pour signer ses jetons, et sans lui *tout* écran
  portant un formulaire tombe en 500. Le symptôme est
  `InvalidArgumentException: A non-empty secret is required.`
- **Le client de test redémarre le noyau à chaque requête** : un double de
  service que l'on règle avant la requête n'est pas celui qui répondra.
  `$client->disableReboot()` avant de le muter.
- **Le rendu Twig échappe les apostrophes** (`n&#039;est`) : une assertion de
  test ne doit pas s'ancrer sur un fragment qui en contient.
- **Deux champs, deux verbes** : la bibliothèque *filtre* une liste déjà
  affichée (`NoteLibrary`), la recherche *cherche* — et déroule elle aussi tout
  le carnet au départ. Ni l'un ni l'autre n'a de titre d'écran : le champ en
  tient lieu, comme dans la maquette. Un test qui identifie ces écrans doit donc
  s'ancrer sur l'invite du champ, pas sur un `<h1>`.
- **`{{ attributes }}` d'un Live Component écrase `data-controller`.** Un
  dialogue qui porterait son propre contrôleur Stimulus sur l'élément racine du
  composant le perdrait au profit de `live`. L'enveloppe du dialogue appartient
  donc à l'écran, le composant n'en rend que le panneau.
- **Un dialogue fermé doit être masqué par une règle explicite.**
  `.fx-dialog { display: flex }` l'emporte sur `[hidden]`, et un dialogue
  « fermé » reste alors un voile plein écran qui avale tous les clics de la page
  sans rien montrer. `.fx-dialog[hidden] { display: none }` est déclaré une fois
  dans `overlay.css` ; chaque dialogue n'a plus à y penser.
- **`.fx-board` est une grille**, pas un conteneur flex : un titre placé
  dedans occupe une case et se range *à côté* de la première carte au lieu de
  la surmonter. Le titre d'une section de cartes va au-dessus de la grille.
- **La barre d'onglets ne rend pas la même liste que la barre latérale.**
  Six entrées tiennent dans une colonne, pas en bas d'un écran de téléphone :
  `ShellRuntime::mobileNavigation()` en retire la recherche et suit l'ordre de
  la maquette. Ajouter une destination demande de penser aux deux.
- **Une couleur en dur, ou une primitive lue par un composant, ne se voit
  qu'en thème sombre** — et seulement sur l'écran concerné. Après avoir touché
  au CSS : `grep -rn '#[0-9a-f]\{3,8\}' assets/styles/{base,layout,components}`
  et `grep -rn 'var(--fx-\(neutral\|ink\|night\|chalk\)-' assets/styles/{base,layout,components}`
  doivent tous deux ne rien retourner.
- **Un `<a class="fx-button">` doit rester non souligné** : `base/typography.css`
  souligne tous les liens, ce qui est juste pour la prose et faux pour une
  commande. `.fx-button` neutralise la règle ; un nouveau composant-lien devra
  faire de même.
- **Ne jamais lire un jeton CSRF par position dans un test**
  (`filter('input[name="_token"]')->last()`) : ajouter une section à l'écran des
  réglages casse alors des tests qui n'ont rien à voir. Toujours ancrer sur le
  formulaire : `filter('form[action="…"] input[name="_token"]')->first()`.
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
- `ResetDatabase` est utilisé dans **toutes** les suites touchant la base. Il
  était incompatible tant que Foundry reconstruisait depuis le mapping (mode
  SCHEMA), qui coupe les connexions ouvertes ; en mode MIGRATE il fonctionne, et
  garantit que la base de test porte bien les dernières migrations. Sans lui,
  lancer la seule suite `functional` après une migration échoue sur une colonne
  inexistante.
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
- En développement, un `asset-map:compile` laisse des fichiers dans
  `public/assets/`, que le serveur sert **à la place** des versions à jour.
  Après avoir touché au CSS : `rm -rf public/assets`. Symfony le signale, mais
  le message passe facilement inaperçu.
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
