<?php

declare(strict_types=1);

namespace App\Privacy\UI\Http;

use App\Privacy\Application\Export\MarkdownExport;
use App\Privacy\Application\Query\PersonalDataExport;
use App\Shared\Application\Account\CurrentAccount;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Téléchargement de toutes les données de l'organisation courante.
 *
 * Deux formats parce qu'ils servent deux choses : le JSON pour réimporter
 * ailleurs, le Markdown pour relire dans dix ans sans Focusyn.
 */
final class ExportPersonalDataController extends AbstractController
{
    public function __construct(
        private readonly PersonalDataExport $export,
        private readonly MarkdownExport $markdown,
        private readonly CurrentAccount $account,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages/donnees/export.{format}', 'en' => '/settings/data/export.{format}'],
        name: 'privacy_export',
        requirements: ['format' => 'json|md'],
        methods: ['GET'],
    )]
    public function __invoke(string $format): Response
    {
        $email = $this->account->emailOrNull() ?? throw $this->createAccessDeniedException();
        $data = $this->export->gather($email);

        [$body, $type] = 'json' === $format
            ? [json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR), 'application/json']
            : [$this->markdown->render($data), 'text/markdown'];

        $response = new Response($body, Response::HTTP_OK, ['Content-Type' => $type.'; charset=utf-8']);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition('attachment', 'focusyn-export.'.$format),
        );

        return $response;
    }
}
