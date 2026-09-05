<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Port\TotpProvisioner;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\UI\QrCode\QrCodeImage;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Prépare l'enrôlement : un secret est généré et gardé en session, sans être
 * écrit en base. Tant qu'un premier code valide n'a pas été fourni, le compte
 * n'a pas de second facteur — un QR affiché mais jamais scanné ne verrouille
 * donc personne.
 */
final class ShowTwoFactorEnrolmentController extends AbstractController
{
    public const string PENDING_SECRET_KEY = 'identity.two_factor.pending_secret';

    public function __construct(
        private readonly TotpProvisioner $totp,
        private readonly QrCodeImage $qrCode,
        private readonly CurrentAccount $account,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages/double-authentification', 'en' => '/settings/two-factor'],
        name: 'two_factor_enrolment',
        methods: ['GET'],
    )]
    public function __invoke(Request $request): Response
    {
        $email = EmailAddress::fromString(
            $this->account->emailOrNull() ?? throw $this->createAccessDeniedException(),
        );

        $secret = $this->totp->generateSecret();
        $request->getSession()->set(self::PENDING_SECRET_KEY, $secret->toString());

        return $this->render('identity/two_factor_enrolment.html.twig', [
            'section' => ShellSection::Settings,
            'secret' => $secret->grouped(),
            'qrCode' => $this->qrCode->dataUri($this->totp->provisioningUri($email, $secret)),
        ]);
    }
}
