# ---------------------------------------------------------------------------
# Ce qui protège `main`
# ---------------------------------------------------------------------------
# Des *rulesets* plutôt que l'ancienne « branch protection » : ils se cumulent,
# ils s'appliquent aussi aux administrateurs sauf contournement nommé, et ils
# savent exiger une signature — ce que la protection de branche ne fait pas.

resource "github_repository_ruleset" "main" {
  count = var.manage_rulesets ? 1 : 0

  name        = "main"
  repository  = github_repository.focusyn.name
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
    # `main` ne se supprime pas et ne se réécrit pas. C'est ce qui donne son
    # sens à tout le reste : une provenance attestée ne vaut rien si l'on peut
    # récrire l'historique sur lequel elle porte.
    deletion         = true
    non_fast_forward = true
    update           = false

    # Une signature par commit. Elle ne dit pas que le code est bon ; elle dit
    # qu'il vient de quelqu'un, et c'est la seule information qu'un
    # `git log --show-signature` puisse encore donner dans six mois. GitHub
    # accepte GPG, SSH et S/MIME ; une clé SSH déjà utilisée pour pousser suffit
    # (`git config gpg.format ssh`).
    required_signatures = true

    # L'historique reste plat : chaque entrée dans `main` est un commit unique,
    # attribuable, révocable.
    required_linear_history = true

    pull_request {
      required_approving_review_count = var.required_approving_review_count
      # Une approbation porte sur ce qui a été lu. Repousser après coup la
      # périme, sinon « approuvé » finit par désigner autre chose que ce qui
      # entre.
      dismiss_stale_reviews_on_push = true
      # Sans approbation exigée, une règle de propriété n'a rien à exiger : elle
      # ne s'arme donc qu'avec la revue, le jour où `.github/CODEOWNERS` nomme
      # quelqu'un.
      require_code_owner_review         = var.required_approving_review_count > 0
      require_last_push_approval        = true
      required_review_thread_resolution = true
    }

    required_status_checks {
      # « strict » : la branche doit être à jour avec `main` avant de fusionner.
      # Sans cela, deux changements verts séparément entrent ensemble sans que
      # personne n'ait jamais exécuté les tests sur leur somme.
      strict_required_status_checks_policy = true

      dynamic "required_check" {
        for_each = var.required_status_checks
        content {
          context = required_check.value
        }
      }
    }
  }
}

# ---------------------------------------------------------------------------
# Ce qui protège les tags de version
# ---------------------------------------------------------------------------
# Une provenance atteste que tel artefact a été construit depuis tel commit, à
# tel tag. Si le tag peut être déplacé, l'attestation continue de dire vrai tout
# en désignant autre chose : `v1.2.0` ne serait plus une version mais un nom de
# variable. D'où l'immuabilité, qui est une condition de la chaîne entière et
# non un raffinement.
resource "github_repository_ruleset" "tags" {
  count = var.manage_rulesets ? 1 : 0

  name        = "versions"
  repository  = github_repository.focusyn.name
  target      = "tag"
  enforcement = "active"

  conditions {
    ref_name {
      include = ["refs/tags/v*"]
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

    # Le nom est vérifié à la création : une version se lit `v1.4.2`, et rien
    # d'autre ne déclenchera la chaîne de publication.
    tag_name_pattern {
      operator = "regex"
      pattern  = "^v[0-9]+\\.[0-9]+\\.[0-9]+(-[0-9A-Za-z.-]+)?$"
      name     = "Versionnage sémantique, préfixé « v »"
    }
  }
}
