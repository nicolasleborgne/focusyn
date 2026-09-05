<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\PasswordResetLink;
use App\Identity\Domain\Model\User;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SignedPasswordResetLink implements PasswordResetLink
{
    private const string FINGERPRINT = 'h';
    private const string VALIDITY = '+30 minutes';

    public function __construct(
        private UrlGeneratorInterface $urls,
        private UriSigner $signer,
    ) {
    }

    public function urlFor(User $user): string
    {
        $url = $this->urls->generate(
            'password_reset',
            ['id' => $user->id()->toString(), self::FINGERPRINT => self::fingerprint($user)],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return $this->signer->sign($url, new DateTimeImmutable(self::VALIDITY));
    }

    public function isValidFor(User $user, string $uri): bool
    {
        if (!$this->signer->check($uri)) {
            return false;
        }

        parse_str((string) parse_url($uri, \PHP_URL_QUERY), $query);
        $presented = $query[self::FINGERPRINT] ?? null;

        // Comparaison à temps constant : ce paramètre est le seul rempart si la
        // clé de signature venait à fuir.
        return \is_string($presented) && hash_equals(self::fingerprint($user), $presented);
    }

    /**
     * Empreinte du mot de passe courant. Le mot de passe changé, l'empreinte
     * change, et tous les liens émis avant deviennent caducs.
     */
    private static function fingerprint(User $user): string
    {
        return substr(hash('sha256', $user->password()->toString()), 0, 16);
    }
}
