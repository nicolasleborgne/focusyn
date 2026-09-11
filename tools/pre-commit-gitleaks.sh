#!/usr/bin/env bash
#
# gitleaks sur ce qui est **indexé**, avant que cela devienne un commit.
#
# La CI cherche déjà les secrets dans tout l'historique, mais elle les trouve
# *après* : à ce moment-là le secret est dans un commit, il faut le révoquer et
# réécrire l'historique. Ici il suffit de retirer la ligne.
#
# Le même binaire et la même configuration que la CI : deux verdicts différents
# sur la même règle apprendraient à ne plus croire ni l'un ni l'autre.

set -euo pipefail

VERSION=v8.30.1
RACINE="$(git rev-parse --show-toplevel)"

if ! command -v docker > /dev/null 2>&1; then
    echo "gitleaks a besoin de docker, qui est introuvable." >&2
    echo "Le commit passe : un garde-fou absent ne doit pas bloquer le travail," >&2
    echo "mais la CI, elle, refusera." >&2
    exit 0
fi

if ! docker run --rm \
    -v "${RACINE}:/repo:ro" \
    "zricethezav/gitleaks:${VERSION}" \
    git /repo --staged --config /repo/.gitleaks.toml --redact --no-banner; then
    cat >&2 <<'AIDE'

Un secret a été trouvé dans ce qui allait être commité.

  - Retirez-le de l'index, puis recommencez.
  - S'il a déjà été poussé ne serait-ce qu'une fois : révoquez-le d'abord,
    le retirer de l'historique ne suffit pas.
  - Si c'est un faux positif assumé — une fixture, une clé de test —, il se
    déclare dans .gitleaks.toml, avec le chemin exact et la raison.

AIDE
    exit 1
fi
