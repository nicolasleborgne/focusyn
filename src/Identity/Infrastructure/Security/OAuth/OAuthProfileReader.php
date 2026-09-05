<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security\OAuth;

use App\Identity\Domain\Model\OAuthProvider;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Chaque fournisseur expose l'adresse et sa vérification à sa façon : Google
 * la donne dans le jeton, GitHub exige un appel séparé. D'où une lecture par
 * fournisseur plutôt qu'un traitement uniforme approximatif.
 */
#[AutoconfigureTag('app.oauth_profile_reader')]
interface OAuthProfileReader
{
    public function supports(OAuthProvider $provider): bool;

    public function read(OAuth2ClientInterface $client, AccessToken $token): OAuthProfile;
}
