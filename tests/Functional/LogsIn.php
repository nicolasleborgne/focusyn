<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Tests\Factory\Identity\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Authentifie un client de test sans passer par le formulaire.
 *
 * Le compte est réellement enregistré : à chaque requête, le pare-feu recharge
 * l'utilisateur depuis le fournisseur, et un compte fabriqué de toutes pièces
 * serait aussitôt déconnecté.
 *
 * La classe qui utilise ce trait doit également utiliser `Factories` et
 * `ResetDatabase`. Le parcours de connexion lui-même est couvert par
 * AuthenticationTest, qui remplit le vrai formulaire.
 */
trait LogsIn
{
    private function logIn(KernelBrowser $client, string $email = 'nicolas@focusyn.fr'): SecurityUser
    {
        $user = UserFactory::new()->withEmail($email)->create();
        $account = SecurityUser::fromDomain($user);

        $client->loginUser($account);

        return $account;
    }
}
