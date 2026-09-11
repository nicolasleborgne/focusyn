# ---------------------------------------------------------------------------
# Ce que la CI a le droit de faire
# ---------------------------------------------------------------------------

resource "github_actions_repository_permissions" "focusyn" {
  repository = github_repository.focusyn.name
  enabled    = true

  # Pas « toutes les actions ». Chaque action tierce s'exécute dans le même
  # runner que notre code, avec le même jeton et le même accès au registre :
  # `uses:` est un `curl | sh` avec les droits du dépôt. La liste dit de qui
  # l'on accepte quelque chose ; l'empreinte, dans le workflow, dit quoi
  # exactement.
  allowed_actions = "selected"

  allowed_actions_config {
    github_owned_allowed = true
    # « vérifié » veut dire que GitHub a vérifié l'identité de l'éditeur, pas
    # son code. Cela ne réduit pas la surface : on préfère nommer.
    verified_allowed = false
    patterns_allowed = local.action_patterns
  }
}

# Les droits par défaut du `GITHUB_TOKEN` ne sont pas exposés par le
# fournisseur Terraform (l'API existe, la ressource non). C'est sans
# conséquence ici : **chaque workflow part de `permissions: {}`** et redemande
# nommément, tâche par tâche, ce dont il a besoin — `contents: write` pour
# publier une release, `packages: write` pour pousser une image. Un droit
# déclaré au plus près l'emporte de toute façon sur le défaut du dépôt, et se
# relit là où il sert.

# Ce qu'un jeton OIDC raconte de lui-même, et donc ce qu'une politique tierce
# peut exiger. `job_workflow_ref` est la clé de voûte : il nomme le workflow
# *et son empreinte*, ce qui permet à un registre ou à un cloud de n'accepter
# que des jetons émis par la chaîne de publication attendue — pas par n'importe
# quel workflow du dépôt.
resource "github_actions_repository_oidc_subject_claim_customization_template" "focusyn" {
  repository  = github_repository.focusyn.name
  use_default = false

  include_claim_keys = [
    "repo",
    "context",
    "job_workflow_ref",
  ]
}

# ---------------------------------------------------------------------------
# Secrets : il n'y en a pas, et c'est le but
# ---------------------------------------------------------------------------
# La chaîne de publication ne porte aucun secret de longue durée. Elle
# s'authentifie auprès de GHCR avec le `GITHUB_TOKEN` de l'exécution, qui
# expire avec elle, et signe les attestations par OIDC auprès de Sigstore —
# une clé éphémère, dont la trace publique est le journal de transparence. Rien
# à faire fuiter, rien à faire tourner.
#
# Le jour où il en faudra un (une clé de déploiement, un DSN de production),
# le poser ici plutôt que dans une interface le rend relisible — mais sa valeur
# passera par l'état Terraform. Un magasin de secrets qui s'interroge à
# l'exécution évite cela ; `github_actions_secret` est le dernier recours,
# pas le premier réflexe.
