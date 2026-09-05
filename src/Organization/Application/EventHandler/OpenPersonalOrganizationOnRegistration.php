<?php

declare(strict_types=1);

namespace App\Organization\Application\EventHandler;

use App\Identity\Domain\Event\UserWasRegistered;
use App\Organization\Application\Port\SlugGenerator;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\MembershipId;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Repository\OrganizationRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Ouvre l'espace personnel d'un nouveau compte.
 *
 * Identity ne sait pas que les organisations existent : il annonce une
 * inscription, Organization en tire les conséquences. C'est ce sens unique qui
 * permettra un jour de déplacer la facturation ou le partage sans toucher à
 * l'authentification.
 *
 * Le traitement est synchrone : sans organisation, un compte tout juste créé ne
 * pourrait rien écrire. La cohérence différée n'est pas acceptable ici.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class OpenPersonalOrganizationOnRegistration
{
    public function __construct(
        private OrganizationRepository $organizations,
        private SlugGenerator $slugs,
    ) {
    }

    public function __invoke(UserWasRegistered $event): void
    {
        $owner = MemberId::fromString($event->userId);
        $name = $this->personalName($event->email);

        $this->organizations->save(Organization::createPersonal(
            OrganizationId::generate(),
            $name,
            $this->availableSlug($name),
            $owner,
            MembershipId::generate(),
            $event->occurredAt(),
        ));
    }

    /**
     * « nicolas@focusyn.fr » donne « Nicolas ». Faute de nom saisi à
     * l'inscription, la partie locale de l'adresse est ce qu'on a de plus
     * proche d'un intitulé humain.
     */
    private function personalName(string $email): string
    {
        $local = substr($email, 0, (int) strpos($email, '@'));
        $readable = trim(str_replace(['.', '_', '-', '+'], ' ', $local));

        return '' === $readable ? 'Carnet personnel' : ucwords($readable);
    }

    private function availableSlug(string $name): string
    {
        $base = $this->slugs->slugify($name);
        $base = '' === $base ? 'carnet' : $base;

        if (!$this->organizations->slugIsTaken($base)) {
            return $base;
        }

        // Deux comptes « nicolas@… » sur des domaines différents produisent le
        // même intitulé : on suffixe jusqu'à trouver libre.
        for ($suffix = 2; $suffix < 1000; ++$suffix) {
            $candidate = $base.'-'.$suffix;

            if (!$this->organizations->slugIsTaken($candidate)) {
                return $candidate;
            }
        }

        return $base.'-'.bin2hex(random_bytes(4));
    }
}
