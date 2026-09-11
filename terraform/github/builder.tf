# ---------------------------------------------------------------------------
# Le dépôt constructeur
# ---------------------------------------------------------------------------
# Il n'existe que pour une raison : SLSA niveau 3 demande que le matériel qui
# signe la provenance soit hors de portée des étapes de construction définies
# par le projet. Cela ne se décrète pas, cela se sépare — la construction doit
# venir d'un workflow réutilisable d'un autre dépôt, dont l'appelant ne définit
# aucune étape.
#
# Son contenu est dans `builder/`, à pousser une fois. Il se protège comme le
# produit : c'est lui qui signe pour les autres.

resource "github_repository" "builder" {
  count = var.manage_builder ? 1 : 0

  name        = var.builder_repository
  description = "Constructeur de confiance : image, SBOM et provenance SLSA pour ${var.repository}"
  visibility  = var.visibility

  has_issues      = true
  has_projects    = false
  has_wiki        = false
  has_discussions = false

  allow_merge_commit     = false
  allow_rebase_merge     = false
  allow_squash_merge     = true
  delete_branch_on_merge = true

  squash_merge_commit_title   = "PR_TITLE"
  squash_merge_commit_message = "PR_BODY"

  web_commit_signoff_required = true

  archive_on_destroy = true

  lifecycle {
    prevent_destroy = true
  }
}

resource "github_repository_vulnerability_alerts" "builder" {
  count = var.manage_builder ? 1 : 0

  repository = github_repository.builder[0].name
  enabled    = true
}

resource "github_repository_dependabot_security_updates" "builder" {
  count = var.manage_builder ? 1 : 0

  repository = github_repository.builder[0].name
  enabled    = true

  depends_on = [github_repository_vulnerability_alerts.builder]
}

# Sans cela, un dépôt privé ne peut pas être appelé par un autre : le workflow
# de release échouerait en disant que le constructeur est introuvable, ce qui
# est la pire façon d'apprendre un réglage de visibilité.
resource "github_actions_repository_access_level" "builder" {
  count = var.manage_builder && var.visibility == "private" ? 1 : 0

  repository   = github_repository.builder[0].name
  access_level = var.owner_is_organization ? "organization" : "user"
}

resource "github_actions_repository_permissions" "builder" {
  count = var.manage_builder ? 1 : 0

  repository      = github_repository.builder[0].name
  enabled         = true
  allowed_actions = "selected"

  allowed_actions_config {
    github_owned_allowed = true
    verified_allowed     = false
    patterns_allowed     = local.action_patterns
  }
}

# Les mêmes protections que le produit. Un constructeur qu'on peut réécrire
# sans laisser de trace ne vaut pas mieux que pas de constructeur : ce qu'il
# signe n'engagerait plus rien.
resource "github_repository_ruleset" "builder_main" {
  count = var.manage_builder && var.manage_rulesets ? 1 : 0

  name        = "main"
  repository  = github_repository.builder[0].name
  target      = "branch"
  enforcement = "active"

  conditions {
    ref_name {
      include = ["~DEFAULT_BRANCH"]
      exclude = []
    }
  }

  dynamic "bypass_actors" {
    for_each = var.bypass_actors
    content {
      actor_type  = bypass_actors.value.actor_type
      actor_id    = bypass_actors.value.actor_id
      bypass_mode = bypass_actors.value.bypass_mode
    }
  }

  rules {
    deletion                = true
    non_fast_forward        = true
    required_signatures     = true
    required_linear_history = true

    pull_request {
      required_approving_review_count   = var.required_approving_review_count
      dismiss_stale_reviews_on_push     = true
      require_code_owner_review         = var.required_approving_review_count > 0
      require_last_push_approval        = var.required_approving_review_count > 0
      required_review_thread_resolution = true
    }
  }
}

# Les étiquettes du constructeur. L'empreinte dit *quoi*, l'étiquette dit
# *quand* — et une étiquette qui se déplace ferait mentir la seconde moitié.
resource "github_repository_ruleset" "builder_tags" {
  count = var.manage_builder && var.manage_rulesets ? 1 : 0

  name        = "versions du constructeur"
  repository  = github_repository.builder[0].name
  target      = "tag"
  enforcement = "active"

  conditions {
    ref_name {
      include = ["refs/tags/builder-v*"]
      exclude = []
    }
  }

  dynamic "bypass_actors" {
    for_each = var.bypass_actors
    content {
      actor_type  = bypass_actors.value.actor_type
      actor_id    = bypass_actors.value.actor_id
      bypass_mode = bypass_actors.value.bypass_mode
    }
  }

  rules {
    deletion            = true
    update              = true
    non_fast_forward    = true
    required_signatures = true
  }
}
