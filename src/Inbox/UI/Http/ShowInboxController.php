<?php

declare(strict_types=1);

namespace App\Inbox\UI\Http;

use App\Inbox\Application\Query\InboxQuery;
use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowInboxController extends AbstractController
{
    public function __construct(
        private readonly InboxQuery $inbox,
    ) {
    }

    #[Route(
        path: ['fr' => '/boite', 'en' => '/inbox'],
        name: 'inbox',
        methods: ['GET'],
    )]
    public function __invoke(): Response
    {
        return $this->render('inbox/inbox.html.twig', [
            'section' => ShellSection::Inbox,
            'captures' => $this->inbox->pending(),
        ]);
    }
}
