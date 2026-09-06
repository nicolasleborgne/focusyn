<?php

declare(strict_types=1);

namespace App\Inbox\UI\Http;

use App\Inbox\Application\Command\CaptureText\CaptureText;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Partager vers Focusyn », depuis n'importe quelle application.
 *
 * C'est le système qui poste ici, à partir du `share_target` déclaré dans le
 * manifeste. Il envoie jusqu'à trois champs, tous facultatifs : un titre, un
 * texte, une adresse — ce qu'il en remplit dépend de l'application d'origine
 * et de ce qu'on y avait sélectionné.
 *
 * **Aucun jeton CSRF**, et il ne peut pas y en avoir : la requête est formée
 * par le système d'exploitation, qui n'a pas vu notre page. Ce que cela ouvre
 * est une ligne de plus dans la boîte de réception — chez quelqu'un de déjà
 * connecté, et à l'endroit même prévu pour trier ce qui vient d'ailleurs.
 * Rien n'est écrit dans le carnet, rien n'est modifié, rien n'est supprimé ;
 * le tri, lui, reste protégé.
 *
 * Une seule adresse, non localisée : elle est inscrite dans le manifeste
 * installé sur l'appareil, et changerait de sens si la langue en changeait.
 */
final class ReceiveSharedController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(path: '/partage', name: 'inbox_share', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        try {
            $this->commands->dispatch(new CaptureText(self::compose($request), source: 'shared'));
        } catch (InvalidArgumentException) {
            // Un partage sans rien dedans : on ouvre la boîte, il n'y a
            // simplement rien de nouveau à y voir.
        }

        // 303 et non 302 : le navigateur doit repartir en GET, sinon revenir
        // en arrière rejouerait le partage.
        return $this->redirectToRoute('inbox', status: Response::HTTP_SEE_OTHER);
    }

    /**
     * Un seul texte à partir des trois champs.
     *
     * Le titre d'abord — c'est lui qui deviendra le titre de la capture, et
     * c'est ce qu'on reconnaît dans une liste. L'adresse ensuite, seule sur sa
     * ligne, ce qui suffit à faire reconnaître un lien. Les doublons sont
     * écartés : partager depuis un navigateur envoie souvent l'adresse à la
     * fois comme `text` et comme `url`.
     */
    private static function compose(Request $request): string
    {
        $parts = [];

        foreach (['title', 'text', 'url'] as $field) {
            $value = trim((string) $request->request->get($field, ''));

            if ('' !== $value && !\in_array($value, $parts, true)) {
                $parts[] = $value;
            }
        }

        return implode("\n\n", $parts);
    }
}
