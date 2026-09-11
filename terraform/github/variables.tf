variable "owner" {
  description = "Compte ou organisation GitHub qui héberge le dépôt."
  type        = string
}

variable "repository" {
  description = "Nom du dépôt."
  type        = string
  default     = "focusyn"
}

variable "visibility" {
  description = <<-TXT
    « private » ou « public ». Le choix commande plus que l'affichage :
    l'analyse de secrets, l'analyse de code et la revue de dépendances sont
    gratuites sur un dépôt public, et demandent GitHub Advanced Security sur un
    dépôt privé (voir `github_advanced_security`).
  TXT
  type        = string
  default     = "private"

  validation {
    condition     = contains(["private", "public"], var.visibility)
    error_message = "La visibilité vaut « private » ou « public »."
  }
}

variable "github_advanced_security" {
  description = <<-TXT
    Le dépôt privé dispose-t-il de GitHub Advanced Security (offres « Code
    Security » / « Secret Protection ») ? Sans lui, l'analyse de secrets et
    l'analyse de code ne s'activent pas sur un dépôt privé, et Terraform
    échouerait à les demander. Sans effet sur un dépôt public, où tout est déjà
    ouvert.
  TXT
  type        = bool
  default     = false
}

variable "required_status_checks" {
  description = <<-TXT
    Les contrôles qui doivent passer avant qu'une modification entre dans
    `main`. Ce sont les **noms de tâches** du workflow CI, tels qu'ils
    s'affichent — pas les identifiants YAML. Renommer une tâche sans toucher à
    cette liste désarme silencieusement la règle : le contrôle attendu ne
    s'exécute jamais, donc il n'échoue jamais.
  TXT
  type        = list(string)
  default = [
    "Qualité (style, analyse statique, architecture)",
    "Tests (unit)",
    "Tests (integration)",
    "Tests (functional)",
    "Secrets (gitleaks)",
    "Dépendances (syft, grype)",
    "Dépôt (terraform)",
    "Image Docker",
  ]
}

variable "required_approving_review_count" {
  description = <<-TXT
    Nombre d'approbations exigées sur une pull request.

    **Zéro par défaut, et ce n'est pas un oubli** : sur un dépôt à un seul
    auteur, exiger une approbation rend toute fusion impossible — on n'approuve
    pas sa propre demande. La règle passe à 1 le jour où quelqu'un d'autre peut
    approuver, et pas avant, sinon elle sera contournée dès la première urgence.
    Les contrôles automatiques, eux, s'appliquent dès maintenant.
  TXT
  type        = number
  default     = 0
}

variable "bypass_actors" {
  description = <<-TXT
    Qui peut contourner les règles de `main`, et dans quelles conditions.

    Vide par défaut : un contournement qui existe finit par servir. Si le dépôt
    appartient à une organisation, la forme habituelle est
    `{ actor_type = "OrganizationAdmin", actor_id = 1, bypass_mode = "pull_request" }`
    — « pull_request » ne dispense que de la revue, jamais des contrôles.
  TXT
  type = list(object({
    actor_type  = string
    actor_id    = number
    bypass_mode = string
  }))
  default = []
}

variable "release_reviewers" {
  description = <<-TXT
    Identifiants numériques des comptes qui doivent approuver un déploiement
    dans l'environnement « release ». Vide sur un projet à un seul auteur : une
    approbation que l'on se donne à soi-même n'est qu'un clic de plus.
  TXT
  type        = list(number)
  default     = []
}

variable "allowed_action_patterns" {
  description = <<-TXT
    Les actions tierces que la CI a le droit d'exécuter, en plus de celles
    publiées par GitHub. Chacune tourne dans le même runner que notre code, avec
    le même jeton : la liste est donc une liste de personnes à qui l'on prête
    le dépôt.

    Les motifs acceptent une étiquette, pas une empreinte. C'est le workflow
    qui épingle par empreinte ; cette liste dit seulement de qui l'on accepte
    quelque chose.
  TXT
  type        = list(string)
  default = [
    "anchore/sbom-action@*",
    "docker/build-push-action@*",
    "docker/login-action@*",
    "docker/metadata-action@*",
    "docker/setup-buildx-action@*",
    "ossf/scorecard-action@*",
    "ramsey/composer-install@*",
    "shivammathur/setup-php@*",
  ]
}

# Le constructeur n'est pas une action tierce : c'est le nôtre. Il rejoint la
# liste blanche sans qu'on ait à y penser, sinon l'appel de release serait
# refusé par la politique que ce même fichier installe.
locals {
  action_patterns = concat(
    var.allowed_action_patterns,
    var.manage_builder ? ["${var.owner}/${var.builder_repository}@*"] : [],
  )
}

variable "manage_default_branch" {
  description = <<-TXT
    Laisser Terraform déclarer la branche par défaut. À mettre à `false` tant
    que le dépôt est vide : la branche doit exister avant qu'on puisse la
    désigner. Voir la marche à suivre dans le README.
  TXT
  type        = bool
  default     = true
}

variable "manage_builder" {
  description = <<-TXT
    Créer et protéger le dépôt constructeur. C'est lui qui porte la
    construction et la signature, dans un workflow réutilisable que le dépôt du
    produit ne peut pas modifier — sans ce second dépôt, la chaîne reste au
    niveau 2 de SLSA quelle que soit la qualité du reste.
  TXT
  type        = bool
  default     = true
}

variable "builder_repository" {
  description = "Nom du dépôt constructeur."
  type        = string
  default     = "focusyn-builder"
}

variable "owner_is_organization" {
  description = <<-TXT
    Le propriétaire est-il une organisation ? Commande la portée d'accès du
    dépôt constructeur : un dépôt privé doit s'ouvrir explicitement aux autres
    dépôts, sinon l'appel échoue en disant qu'il est introuvable — la pire
    façon d'apprendre un réglage de visibilité.
  TXT
  type        = bool
  default     = false
}

variable "topics" {
  description = "Sujets du dépôt."
  type        = list(string)
  default     = ["symfony", "php", "saas", "pwa", "notebook"]
}
