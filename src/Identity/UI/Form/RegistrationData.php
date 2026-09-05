<?php

declare(strict_types=1);

namespace App\Identity\UI\Form;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Données saisies au formulaire d'inscription.
 *
 * Objet mutable propre à la couche UI : la commande applicative, elle, est
 * immuable. Les contraintes de saisie vivent ici, les règles métier dans le
 * domaine — une adresse mal écrite est une erreur de formulaire, une adresse
 * déjà prise est une décision du domaine.
 */
final class RegistrationData
{
    #[Assert\NotBlank(message: 'registration.email.required')]
    #[Assert\Email(message: 'registration.email.invalid')]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'registration.password.required')]
    #[Assert\Length(
        min: 12,
        max: 4096,
        minMessage: 'registration.password.too_short',
    )]
    #[Assert\NotCompromisedPassword(message: 'registration.password.compromised', skipOnError: true)]
    public ?string $plainPassword = null;
}
