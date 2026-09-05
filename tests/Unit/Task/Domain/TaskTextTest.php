<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\Domain;

use App\Task\Domain\Model\TaskText;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaskText::class)]
final class TaskTextTest extends TestCase
{
    public function testItCollapsesStrayWhitespace(): void
    {
        self::assertSame('Écrire la synthèse', TaskText::fromString("  Écrire   la\nsynthèse  ")->toString());
    }

    #[DataProvider('emptyTexts')]
    public function testATaskWithoutTextIsRefused(string $candidate): void
    {
        $this->expectException(InvalidArgumentException::class);

        TaskText::fromString($candidate);
    }

    /** @return iterable<string, array{string}> */
    public static function emptyTexts(): iterable
    {
        yield 'vide' => [''];
        yield 'espaces' => ['    '];
        yield 'saut de ligne seul' => ["\n"];
    }

    public function testItRefusesWhatIsNoLongerATask(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TaskText::fromString(str_repeat('a', 501));
    }
}
