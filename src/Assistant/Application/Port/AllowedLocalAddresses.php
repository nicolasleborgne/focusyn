<?php

declare(strict_types=1);

namespace App\Assistant\Application\Port;

/**
 * Les adresses de fournisseur local que cette instance accepte d'appeler.
 *
 * Un fournisseur « local » tourne sur la machine de la personne, que le serveur
 * ne peut pas joindre. En hébergé, une adresse saisie là ne peut donc désigner
 * que l'intérieur du réseau du serveur : une base de données, un service
 * voisin, le point de métadonnées de l'hébergeur. Laisser le choix à qui
 * remplit le champ revient à prêter le serveur comme relais — c'est une
 * requête forgée côté serveur, écrite dans un écran de réglages.
 *
 * La liste appartient donc à l'exploitant, jamais au compte. **Vide par
 * défaut** : une instance hébergée ne propose pas de fournisseur local, et
 * celui qui héberge son propre modèle nomme l'adresse exacte qu'il fait
 * tourner.
 *
 * La comparaison est une égalité, pas une appartenance à un réseau : c'est la
 * seule règle qu'aucune résolution de nom, aucun `..` dans un chemin et aucune
 * redirection ne contourne.
 */
interface AllowedLocalAddresses
{
    /** @return list<string> */
    public function all(): array;

    public function permits(string $address): bool;
}
