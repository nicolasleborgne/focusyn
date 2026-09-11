# ---------------------------------------------------------------------------
# L'environnement « release »
# ---------------------------------------------------------------------------
# Un environnement n'est pas une machine : c'est un point de passage. Il permet
# de dire « seule cette référence peut publier », et — le jour où l'équipe
# compte plus d'une personne — « quelqu'un doit le vouloir ».
#
# Sa valeur pour la chaîne de provenance : la tâche qui pousse l'image et signe
# est la seule à s'exécuter dans cet environnement, donc la seule à pouvoir
# obtenir les droits qui y sont attachés. Une tâche ajoutée ailleurs dans le
# dépôt ne peut pas s'y glisser.

resource "github_repository_environment" "release" {
  repository  = github_repository.focusyn.name
  environment = "release"

  # Personne ne s'auto-approuve. Sans relecteur déclaré, la règle n'a rien à
  # empêcher et GitHub refuse qu'on la pose : elle n'apparaît donc que le jour
  # où quelqu'un peut réellement approuver.
  prevent_self_review = length(var.release_reviewers) > 0

  dynamic "reviewers" {
    for_each = length(var.release_reviewers) > 0 ? [1] : []
    content {
      users = var.release_reviewers
    }
  }

  deployment_branch_policy {
    # `main` et rien d'autre. Le raisonnement a changé avec le modèle : tant que
    # la version naissait d'un tag, c'était le tag qu'il fallait nommer ici.
    # Maintenant qu'une fusion publie, c'est la branche — et le tag, lui, est
    # créé *après*, par l'usine, une fois l'image attestée.
    #
    # Ce que cela garde : aucune branche de travail ne peut obtenir les droits
    # attachés à cet environnement, donc aucune ne peut faire signer le
    # constructeur.
    protected_branches     = false
    custom_branch_policies = true
  }
}

resource "github_repository_environment_deployment_policy" "release_branch" {
  repository  = github_repository.focusyn.name
  environment = github_repository_environment.release.environment

  branch_pattern = "main"
}
