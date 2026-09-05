<?php

declare(strict_types=1);

namespace App\Shared\UI\Twig;

use App\Shared\Application\Display\CurrentDisplay;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class DisplayExtension extends AbstractExtension
{
    public function __construct(
        private readonly CurrentDisplay $display,
    ) {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('display', fn (): CurrentDisplay => $this->display),
        ];
    }
}
