<?php

declare(strict_types=1);

namespace App\Shared\UI\Twig;

use App\Shared\Application\Shell\ShellDataProvider;
use App\Shared\Application\Shell\ShellView;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Charge la vue de la coquille à la première demande, une seule fois par
 * requête : un gabarit peut appeler `shell()` dans la barre latérale, l'en-tête
 * et la barre d'onglets sans multiplier les accès au fournisseur.
 */
final class ShellRuntime implements RuntimeExtensionInterface
{
    private ?ShellView $view = null;

    public function __construct(
        private readonly ShellDataProvider $provider,
    ) {
    }

    public function view(): ShellView
    {
        return $this->view ??= $this->provider->forCurrentUser();
    }

    /**
     * Entrées de navigation, dans l'ordre d'affichage.
     *
     * Définies ici plutôt que dans un gabarit : la barre latérale et la barre
     * d'onglets rendent la même liste, et une variable posée dans un `include`
     * ne remonte pas dans la portée appelante. Le libellé reste au gabarit,
     * via la clé de traduction `shell.nav.<clé>`.
     *
     * @return list<array{key: string, route: string, count: int|null, shortcut: string|null}>
     */
    public function navigation(): array
    {
        $view = $this->view();

        return [
            ['key' => 'home', 'route' => 'home', 'count' => null, 'shortcut' => null],
            ['key' => 'library', 'route' => 'library', 'count' => $view->noteCount, 'shortcut' => null],
            ['key' => 'tasks', 'route' => 'tasks', 'count' => $view->openTaskCount(), 'shortcut' => null],
            ['key' => 'inbox', 'route' => 'inbox', 'count' => $view->inboxCount ?: null, 'shortcut' => null],
            ['key' => 'search', 'route' => 'search', 'count' => null, 'shortcut' => '⌘K'],
            ['key' => 'settings', 'route' => 'settings', 'count' => null, 'shortcut' => null],
        ];
    }

    /**
     * La même chose sur un téléphone, mais pas la même liste.
     *
     * Cinq onglets tiennent en bas d'un écran, six s'y serrent. La recherche
     * en sort — c'est la seule destination qui a son propre geste au clavier
     * et qui se retrouve depuis n'importe quel écran —, et l'ordre suit la
     * maquette : ce qu'on ouvre le plus souvent d'abord.
     *
     * @return list<array{key: string, route: string, count: int|null, shortcut: string|null}>
     */
    public function mobileNavigation(): array
    {
        $keep = ['home', 'tasks', 'library', 'inbox', 'settings'];
        $byKey = array_column($this->navigation(), null, 'key');

        return array_values(array_map(
            static fn (string $key): array => $byKey[$key],
            $keep,
        ));
    }
}
