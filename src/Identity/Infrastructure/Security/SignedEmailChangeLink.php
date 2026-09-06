<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\EmailChangeLink;
use App\Identity\Domain\Model\User;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Le lien qui confirme une nouvelle adresse.
 *
 * Même dessin que celui de la réinitialisation de mot de passe : une adresse
 * signée, plus une empreinte qui la lie à la demande en cours. Se raviser
 * change l'empreinte, et le lien précédent cesse d'ouvrir quoi que ce soit.
 */
final readonly class SignedEmailChangeLink implements EmailChangeLink
{
    private const string FINGERPRINT = 'h';
    private const string VALIDITY = '+24 hours';

    public function __construct(
        private UrlGeneratorInterface $urls,
        private UriSigner $signer,
    ) {
    }

    public function urlFor(User $user): string
    {
        $url = $this->urls->generate(
            'email_change_confirm',
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
     * Empreinte de l'adresse en attente. Aucune demande, aucune empreinte : un
     * lien émis pour une demande annulée ne vaut plus rien.
     */
    private static function fingerprint(User $user): string
    {
        return substr(hash('sha256', $user->pendingEmail()?->toString() ?? 'aucune'), 0, 16);
    }
}
