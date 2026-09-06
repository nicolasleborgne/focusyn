<?php

declare(strict_types=1);

namespace App\Assistant\Application\Port;

use App\Assistant\Domain\Model\SealedKey;

/**
 * Scelle et descelle une clé d'API.
 *
 * **Ce que cela protège, et ce que cela ne protège pas.** Un vol de la seule
 * base de données ne donne rien : les clés y sont illisibles. Mais le serveur
 * doit pouvoir les relire pour appeler le fournisseur, donc quiconque obtient
 * *à la fois* la base et le secret de l'application les obtient toutes. Un
 * chiffrement de bout en bout est impossible ici : personne n'est là pour
 * saisir un mot de passe au moment où le worker appelle le modèle.
 */
interface KeyVault
{
    public function seal(string $plainKey): SealedKey;

    public function unseal(SealedKey $key): string;
}
