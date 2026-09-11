# Le dépôt GitHub, décrit

Ce qui se règle dans une interface se dérègle sans laisser de trace. Ici,
chaque garde est une ligne relue comme le reste du code, et `tofu plan` dit ce
qui a bougé depuis la dernière fois.

## Ce que ce dossier tient, et ce qu'il ne tient pas

| | Où | Pourquoi |
| --- | --- | --- |
| La **politique** — ce que le dépôt autorise | ici | Se relit, se diffe, se restaure |
| La **chaîne de construction** — ce que la CI fait et atteste | `.github/workflows/` | C'est du code, il passe par une revue |
| Le **contenu** des fichiers (`dependabot.yml`, `CODEOWNERS`…) | le dépôt | Les faire poser par Terraform les ferait entrer par l'API, c'est-à-dire en contournant la protection que ce même Terraform installe |

## Démarrer

```bash
export GITHUB_TOKEN="$(gh auth token)"      # ou un jeton d'application GitHub
cp terraform.tfvars.example terraform.tfvars
$EDITOR terraform.tfvars

tofu init
tofu plan
tofu apply
```

`terraform` et `tofu` fonctionnent tous deux ; les exemples disent `tofu`.

### Dépôt vide : deux temps

Une branche doit exister avant qu'on puisse la désigner par défaut, et avant
qu'un contrôle de CI puisse être « exigé ». Sur un dépôt neuf :

```bash
# 1. Le dépôt seul, sans les règles qui parlent de main.
tofu apply -var manage_default_branch=false \
  -target github_repository.focusyn

# 2. La première poussée.
git remote add origin "$(tofu output -raw clone_url_ssh)"
git push -u origin main

# 3. Tout le reste.
tofu apply
```

### Dépôt déjà en ligne

Ne pas laisser Terraform le recréer : l'adopter.

```bash
tofu import github_repository.focusyn focusyn
tofu plan   # doit montrer des modifications, jamais une suppression
```

`github_repository` porte `prevent_destroy` et `archive_on_destroy` : même un
plan de suppression s'arrête, et un contournement archiverait au lieu
d'effacer.

## Ce qui est posé

**Sur `main`** — signature exigée sur chaque commit, historique linéaire,
suppression et réécriture interdites, contrôles de CI obligatoires et branche à
jour avant fusion. Aucun contournement n'est déclaré : `bypass_actors` est vide.

**Sur les tags `v*`** — mêmes protections, plus un motif de nom. Un tag qui
peut se déplacer rend toute attestation de provenance décorative : elle
continuerait de dire vrai en désignant autre chose.

**Sur les actions** — liste blanche d'éditeurs (`allowed_action_patterns`).
Chaque `uses:` s'exécute dans le même runner que notre code, avec le même
jeton : c'est un `curl | sh` avec les droits du dépôt. La liste dit de qui l'on
accepte quelque chose, l'empreinte dans le workflow dit quoi exactement.

**Sur les jetons OIDC** — `job_workflow_ref` est inclus dans le sujet, de sorte
qu'un registre ou un cloud puisse n'accepter que des jetons émis par la chaîne
de publication attendue, et non par n'importe quel workflow du dépôt.

**Sur l'environnement `release`** — seuls les tags `v*` y déploient.

**Dependabot** — alertes puis mises à jour de sécurité. La première sans la
seconde ne fait que remplir un onglet.

## Le constructeur

`builder/`, à la racine du dépôt, est le contenu d'un **second dépôt** que
Terraform crée et protège. Il porte le workflow réutilisable qui construit,
signe et atteste. Sans lui, la chaîne reste au niveau 2 de SLSA quelle que soit
la qualité du reste : un constructeur qui vient du même commit que ce qu'il
construit n'est pas un tiers.

`builder/` est suivi par le dépôt Focusyn : on ne fait **pas** `git init`
dedans, sous peine d'en faire un sous-module. On clone ailleurs et on copie.

```bash
tofu apply                       # crée les deux dépôts
git clone "$(tofu output -raw builder_clone_url_ssh)" /tmp/focusyn-builder
cp -a ../../builder/. /tmp/focusyn-builder/
cd /tmp/focusyn-builder && git add . && git commit -S -m "Le constructeur" && git push -u origin main
git rev-parse HEAD               # l'empreinte à reporter dans release.yaml
```

Puis, dans `.github/workflows/release.yaml`, remplacer `nicolasleborgne` et `@main`
par le compte et cette empreinte. Une référence de branche se déplacerait sous
les pieds de ce qu'elle construit.

## Ce qui reste hors de portée de Terraform

- **Le mode vigilant** du compte (afficher « Unverified » sur les commits non
  signés) est un réglage personnel, pas un réglage de dépôt :
  <https://github.com/settings/keys>. Sans lui, un commit non signé se lit comme
  les autres dans l'interface — la règle de branche le refuse quand même.
- **La rétention des artefacts et journaux** est un réglage d'organisation ou
  d'entreprise.
- **La visibilité d'un paquet GHCR** n'est pas exposée par le fournisseur ; elle
  se règle une fois, à la première publication.
- **Les droits par défaut du `GITHUB_TOKEN`** n'ont pas de ressource au niveau
  du dépôt. Sans conséquence : chaque workflow part de `permissions: {}` et
  redemande nommément, ce qui l'emporte de toute façon.

## L'état

Il décrit qui peut contourner quoi. Ce n'est pas un secret, mais c'est une
carte. Le garder sur un poste de travail, c'est le perdre le jour où le poste
change — voir le bloc `backend` commenté dans `versions.tf`.

Aucun secret n'est géré ici, et c'est délibéré : la chaîne de publication n'en
utilise aucun. Elle s'authentifie avec le jeton de l'exécution, qui expire avec
elle, et signe par OIDC auprès de Sigstore — une clé éphémère. Rien à faire
fuiter, rien à faire tourner.
