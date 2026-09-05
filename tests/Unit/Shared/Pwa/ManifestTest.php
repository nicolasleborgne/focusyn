<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Pwa;

use PHPUnit\Framework\TestCase;

/**
 * Le manifeste et ses icônes sont des fichiers statiques : rien ne les relie au
 * code, donc rien n'avertit si une icône est renommée. Ce test tient lieu de
 * lien.
 */
final class ManifestTest extends TestCase
{
    private const string PUBLIC_DIR = __DIR__.'/../../../../public';

    public function testTheManifestIsValidJson(): void
    {
        self::assertIsArray($this->manifest());
    }

    public function testItDeclaresWhatMakesAnApplicationInstallable(): void
    {
        $manifest = $this->manifest();

        foreach (['name', 'short_name', 'start_url', 'display', 'icons'] as $key) {
            self::assertArrayHasKey($key, $manifest);
        }

        self::assertSame('standalone', $manifest['display']);
    }

    public function testEveryDeclaredIconExists(): void
    {
        $manifest = $this->manifest();
        self::assertIsArray($manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            self::assertIsArray($icon);
            self::assertIsString($icon['src']);

            self::assertFileExists(
                self::PUBLIC_DIR.$icon['src'],
                \sprintf('Le manifeste déclare %s, qui n\'existe pas.', $icon['src']),
            );
        }
    }

    public function testItShipsAMaskableIconForAndroid(): void
    {
        $manifest = $this->manifest();
        self::assertIsArray($manifest['icons']);

        $purposes = array_column($manifest['icons'], 'purpose');

        self::assertContains(
            'maskable',
            $purposes,
            'Sans icône masquable, Android rogne l\'icône dans un cercle et coupe la marque.',
        );
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        $raw = file_get_contents(self::PUBLIC_DIR.'/manifest.webmanifest');
        self::assertIsString($raw);

        $decoded = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
