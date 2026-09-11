# Signaler une faille

Écrivez à **securite@focusyn.fr**, ou ouvrez un signalement privé depuis
l'onglet *Security* du dépôt (« Report a vulnerability »), qui reste invisible
tant que le correctif n'est pas publié.

**N'ouvrez pas d'issue publique** pour une faille non corrigée : une issue est
lisible par tout le monde, y compris par ceux qui n'attendaient que l'adresse.

Ce qui aide, dans l'ordre : ce que vous avez obtenu, comment le reproduire, et
ce que cela permettrait à quelqu'un de mal intentionné. Une capture d'écran
sans les étapes fait perdre une journée.

Nous répondons sous 72 heures et disons franchement si nous ne savons pas
encore. Vous serez crédité dans les notes de version si vous le souhaitez.

## Versions suivies

Seule la dernière version publiée reçoit des correctifs. Le service hébergé est
mis à jour en premier.

## Ce que nous publions avec chaque version

Chaque image porte sa **provenance** et son **inventaire**, signés. Vous pouvez
vérifier d'où elle vient sans nous faire confiance :

```bash
gh attestation verify oci://ghcr.io/nicolasleborgne/focusyn:<version> \
  --repo nicolasleborgne/focusyn \
  --predicate-type https://slsa.dev/provenance/v1
```

La provenance nomme le commit et le workflow qui ont produit l'image ; le SBOM
joint à la release dit ce qu'elle contient. Les deux sont signés par une clé
éphémère obtenue par OIDC et inscrits dans un journal de transparence public —
il n'existe aucune clé de signature à long terme à nous dérober.

## Ce que nous ne promettons pas

Notre modèle de menace est écrit dans `CLAUDE.md`, et il dit aussi ce qu'il ne
couvre pas. Deux points qui méritent d'être lus avant de nous confier quoi que
ce soit :

- **Les clés d'API de l'assistant** sont chiffrées en base, et le serveur doit
  pouvoir les relire pour appeler le fournisseur en votre nom. Cela protège du
  vol de la seule base ; cela ne protège pas d'un vol de la base *et* du secret.
  Un chiffrement de bout en bout est impossible ici — personne n'est là pour
  saisir un mot de passe quand le serveur appelle le modèle.
- **Le partage entrant** (`/partage`) n'a pas de jeton CSRF et ne peut pas en
  avoir : c'est le système d'exploitation qui poste, il n'a jamais vu notre
  page. Ce que cela ouvre est une ligne de plus dans une boîte de réception.
