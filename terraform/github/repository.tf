# ---------------------------------------------------------------------------
# Le dépôt
# ---------------------------------------------------------------------------

resource "github_repository" "focusyn" {
  name        = var.repository
  description = "Focusyn — carnet numérique de synthèse personnelle (SaaS)"
  visibility  = var.visibility
  topics      = var.topics

  has_issues      = true
  has_projects    = false
  has_wiki        = false
  has_discussions = false

  # Une seule façon d'entrer dans `main`, et elle produit un seul commit dont le
  # message est celui de la demande. L'historique linéaire n'est pas une
  # coquetterie : c'est ce qui permet de dire « cette version contient
  # exactement ces changements » sans démêler un graphe.
  allow_merge_commit     = false
  allow_rebase_merge     = false
  allow_squash_merge     = true
  allow_auto_merge       = true
  allow_update_branch    = true
  delete_branch_on_merge = true

  squash_merge_commit_title   = "PR_TITLE"
  squash_merge_commit_message = "PR_BODY"

  # Un commit écrit depuis l'interface web est signé par GitHub, pas par son
  # auteur : le `Signed-off-by` est la seule trace qu'il reste de qui l'a voulu.
  web_commit_signoff_required = true

  dynamic "security_and_analysis" {
    # Sur un dépôt privé sans Advanced Security, demander ces réglages fait
    # échouer l'API. On ne les déclare donc que là où ils peuvent s'appliquer,
    # plutôt que de laisser un `apply` casser chez qui n'a pas l'offre.
    for_each = var.visibility == "public" || var.github_advanced_security ? [1] : []

    content {
      dynamic "advanced_security" {
        for_each = var.visibility == "private" && var.github_advanced_security ? [1] : []
        content {
          status = "enabled"
        }
      }

      secret_scanning {
        status = "enabled"
      }

      # La poussée est **refusée**, pas seulement signalée. Un secret repoussé
      # reste lisible dans le commit qui l'a introduit : le rattraper après coup
      # oblige à réécrire l'historique et à révoquer la clé. L'arrêter avant
      # coûte une seconde.
      secret_scanning_push_protection {
        status = "enabled"
      }
    }
  }

  # Un dépôt ne se supprime pas par inadvertance, et surtout pas parce qu'une
  # ressource a été renommée dans un fichier. Les deux gardes sont utiles :
  # `prevent_destroy` arrête le plan, `archive_on_destroy` fait que même un
  # contournement archive au lieu d'effacer.
  archive_on_destroy = true

  lifecycle {
    prevent_destroy = true
  }
}

resource "github_branch_default" "main" {
  count = var.manage_default_branch ? 1 : 0

  repository = github_repository.focusyn.name
  branch     = "main"
}

# ---------------------------------------------------------------------------
# Dependabot
# ---------------------------------------------------------------------------
# Deux moitiés, et la seconde ne sert à rien sans la première : les *alertes*
# disent qu'une dépendance est vulnérable, les *mises à jour de sécurité*
# ouvrent la demande qui la corrige. Sans la seconde, les alertes s'accumulent
# dans un onglet que personne n'ouvre.
#
# Ce que Dependabot surveille est décrit dans `.github/dependabot.yml`, fichier
# du dépôt — donc relu comme le reste, et non piloté depuis ici.
resource "github_repository_vulnerability_alerts" "focusyn" {
  repository = github_repository.focusyn.name
  enabled    = true
}

resource "github_repository_dependabot_security_updates" "focusyn" {
  repository = github_repository.focusyn.name
  enabled    = true

  # L'API refuse d'activer les mises à jour avant les alertes.
  depends_on = [github_repository_vulnerability_alerts.focusyn]
}
