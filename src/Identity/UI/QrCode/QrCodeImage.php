<?php

declare(strict_types=1);

namespace App\Identity\UI\QrCode;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Rend l'URI d'enrôlement en QR code.
 *
 * SVG et non PNG : le code reste net à toute densité d'écran, et le résultat
 * s'intègre dans la page sans requête supplémentaire — ce qui compte pour une
 * donnée qui ne doit surtout pas être mise en cache par un intermédiaire.
 */
final readonly class QrCodeImage
{
    public function dataUri(string $provisioningUri): string
    {
        return new SvgWriter()->write(
            new QrCode(
                data: $provisioningUri,
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: 220,
                margin: 8,
                foregroundColor: new Color(31, 31, 31),
                backgroundColor: new Color(255, 255, 255),
            ),
        )->getDataUri();
    }
}
