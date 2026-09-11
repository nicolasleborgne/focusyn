# ---------------------------------------------------------------------------
# Focusyn — le dépôt GitHub et sa sécurité, décrits plutôt que cliqués
# ---------------------------------------------------------------------------
# Ce qui se règle dans une interface se dérègle sans laisser de trace : personne
# ne sait quand la protection de `main` a été désactivée « cinq minutes », ni
# par qui. Ici, chaque garde est une ligne relue comme le reste du code, et un
# `tofu plan` dit ce qui a bougé.
#
# Partage du travail, à ne pas confondre :
#   - Terraform tient la **politique** — ce que le dépôt autorise ;
#   - les fichiers de `.github/` tiennent la **chaîne de construction** — ce que
#     la CI fait, et ce qu'elle atteste.
# Terraform ne gère volontairement pas le contenu des fichiers du dépôt : ils
# passeraient par l'API plutôt que par une revue, c'est-à-dire en contournant la
# protection que ce même fichier installe.

terraform {
  required_version = ">= 1.6"

  required_providers {
    github = {
      source  = "integrations/github"
      version = "~> 6.13"
    }
  }

  # L'état contient la description complète des protections : qui peut les
  # contourner, quels contrôles sont exigés. Ce n'est pas un secret, mais c'est
  # une carte. Le garder sur un poste de travail, c'est le perdre le jour où le
  # poste change.
  #
  # backend "s3" {
  #   bucket       = "focusyn-tfstate"
  #   key          = "github/terraform.tfstate"
  #   region       = "eu-west-3"
  #   encrypt      = true
  #   use_lockfile = true
  # }
}

# L'authentification passe par la variable d'environnement `GITHUB_TOKEN`, et
# jamais par un jeton écrit ici : un jeton dans un fichier finit dans un
# historique. Un jeton à durée de vie courte (`gh auth token`) suffit, avec les
# droits `repo` et `admin:repo_hook` — ou, sur une organisation, un jeton
# d'application GitHub, qui se révoque sans toucher à un compte humain.
provider "github" {
  owner = var.owner
}
