<?php

declare(strict_types=1);

namespace App\Shared\UI\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose `shell()` aux gabarits.
 *
 * Sans cela, chacun des contrôleurs d'écran devrait transporter les données de
 * la coquille dans son contexte de rendu — cinq répétitions aujourd'hui,
 * autant que d'écrans demain.
 */
final class ShellExtension extends AbstractExtension
{
    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('shell', [ShellRuntime::class, 'view']),
            new TwigFunction('shell_navigation', [ShellRuntime::class, 'navigation']),
            new TwigFunction('shell_mobile_navigation', [ShellRuntime::class, 'mobileNavigation']),
        ];
    }
}
