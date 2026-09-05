<?php

declare(strict_types=1);

namespace App\Notebook\UI\Http;

use App\Notebook\Application\Command\DescribeObsession\DescribeObsession;
use App\Notebook\Application\Exception\ObsessionNotFound;
use App\Shared\Application\Command\CommandBus;
use DomainException;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class DescribeObsessionController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: '/obsessions/{slug}/description',
        name: 'obsession_describe',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('obsession-describe')]
    public function __invoke(string $slug, Request $request): Response
    {
        /** @var list<string> $points */
        $points = array_values(array_filter(
            $request->request->all('points'),
            static fn (mixed $point): bool => \is_string($point),
        ));

        try {
            $this->commands->dispatch(new DescribeObsession(
                $slug,
                $request->request->getString('blurb'),
                $points,
            ));
            $this->addFlash('success', 'obsession.saved');
        } catch (ObsessionNotFound) {
            throw $this->createNotFoundException();
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('obsession_show', ['slug' => $slug]);
    }
}
