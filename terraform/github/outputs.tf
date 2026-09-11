output "repository_url" {
  description = "Adresse du dépôt."
  value       = github_repository.focusyn.html_url
}

output "clone_url_ssh" {
  description = "À poser en remote : git remote add origin <valeur>."
  value       = github_repository.focusyn.ssh_clone_url
}

output "container_image" {
  description = "L'image que publie la chaîne de release."
  value       = "ghcr.io/${var.owner}/${var.repository}"
}

output "verification" {
  description = <<-TXT
    Comment vérifier une version sans faire confiance à qui l'a publiée.

    `--signer-repo` est l'assertion qui compte : elle exige que la signature
    vienne du constructeur, un dépôt séparé dont le dépôt du produit ne définit
    aucune étape. Sans elle, on vérifie qu'une signature existe, pas qu'elle
    vient d'où l'on croit.
  TXT
  value = {
    provenance = var.manage_builder ? join(" ", [
      "gh attestation verify oci://ghcr.io/${var.owner}/${var.repository}:VERSION",
      "--repo ${var.owner}/${var.repository}",
      "--signer-repo ${var.owner}/${var.builder_repository}",
      "--predicate-type https://slsa.dev/provenance/v1",
      ]) : join(" ", [
      "gh attestation verify oci://ghcr.io/${var.owner}/${var.repository}:VERSION",
      "--repo ${var.owner}/${var.repository}",
    ])
    sbom       = "gh attestation verify … --predicate-type https://spdx.dev/Document"
    signatures = "git verify-commit HEAD && git verify-tag VERSION"
  }
}

output "builder_repository_url" {
  description = "Le dépôt constructeur, à peupler avec le contenu de builder/."
  value       = var.manage_builder ? github_repository.builder[0].html_url : null
}

output "builder_clone_url_ssh" {
  value       = var.manage_builder ? github_repository.builder[0].ssh_clone_url : null
  description = "cd builder && git init -b main && git remote add origin <valeur>"
}

output "release_uses_line" {
  description = <<-TXT
    La ligne à reporter dans `.github/workflows/release.yaml`, une fois le
    constructeur poussé — en remplaçant `main` par l'empreinte du commit
    (`git rev-parse HEAD` dans builder/). Une référence de branche se déplace
    sous les pieds de ce qu'elle construit.
  TXT
  value       = var.manage_builder ? "uses: ${var.owner}/${var.builder_repository}/.github/workflows/build-image.yaml@<empreinte>" : null
}
