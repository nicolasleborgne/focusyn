<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Provider;

use App\Assistant\Application\Port\AllowedLocalAddresses;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * La liste blanche, telle que l'exploitant l'a écrite dans
 * `ASSISTANT_LOCAL_URLS` : des adresses séparées par des virgules.
 *
 * La normalisation est la même qu'à l'enregistrement — espaces retirés, barre
 * oblique finale retirée —, sans quoi `http://ollama:11434/` ne vaudrait pas
 * `http://ollama:11434` et la liste refuserait ce qu'elle autorise.
 */
final readonly class ConfiguredLocalAddresses implements AllowedLocalAddresses
{
    public function __construct(
        #[Autowire('%env(ASSISTANT_LOCAL_URLS)%')]
        private string $addresses,
    ) {
    }

    public function all(): array
    {
        return array_values(array_filter(array_map(
            self::normalize(...),
            explode(',', $this->addresses),
        ), static fn (string $address): bool => '' !== $address));
    }

    public function permits(string $address): bool
    {
        $wanted = self::normalize($address);

        return '' !== $wanted && \in_array($wanted, $this->all(), true);
    }

    private static function normalize(string $address): string
    {
        return rtrim(trim($address), '/');
    }
}
