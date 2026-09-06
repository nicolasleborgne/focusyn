<?php

declare(strict_types=1);

namespace App\Reminder\UI\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * D'où venait la demande.
 *
 * La puce de rappel est posée sur plusieurs écrans et le dialogue ne sait pas
 * lequel l'a ouvert : il faut donc revenir sur ses pas. Seul un chemin de ce
 * site est accepté — une adresse absolue venue d'ailleurs ferait de ces
 * formulaires un tremplin vers n'importe où.
 */
trait BackToWhereWeCameFrom
{
    private function backTo(Request $request, string $fallbackRoute = 'home'): string
    {
        $path = parse_url((string) $request->headers->get('referer'), \PHP_URL_PATH);

        return \is_string($path) && 1 === preg_match('#^/[^/\\\\]#', $path)
            ? $path
            : $this->generateUrl($fallbackRoute);
    }
}
