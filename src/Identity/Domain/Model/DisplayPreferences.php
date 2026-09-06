<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use InvalidArgumentException;

/**
 * Réglages d'affichage d'un compte.
 *
 * Objet valeur immuable : chaque modification retourne une nouvelle instance.
 * Ces réglages ne pilotent rien côté serveur — ils sont rendus en attributs
 * `data-fx-*` sur `<html>`, et c'est le design system qui en tire les
 * conséquences. Aucun style n'est recalculé.
 */
final readonly class DisplayPreferences
{
    public function __construct(
        public Accent $accent,
        public ProseFont $proseFont,
        public Density $density,
        public MarkOpacity $markOpacity,
        public bool $previewPane,
        public Theme $theme,
    ) {
    }

    public static function default(): self
    {
        return new self(
            Accent::Slate,
            ProseFont::Serif,
            Density::Comfortable,
            MarkOpacity::fromFloat(0.45),
            previewPane: true,
            theme: Theme::System,
        );
    }

    /**
     * Reconstruit depuis la forme stockée.
     *
     * Toute valeur inconnue retombe sur le défaut : un réglage retiré d'une
     * version à l'autre ne doit pas empêcher un compte de se connecter.
     *
     * @param array<string, mixed> $stored
     */
    public static function fromArray(array $stored): self
    {
        $default = self::default();

        return new self(
            Accent::tryFrom((string) ($stored['accent'] ?? '')) ?? $default->accent,
            ProseFont::tryFrom((string) ($stored['proseFont'] ?? '')) ?? $default->proseFont,
            Density::tryFrom((string) ($stored['density'] ?? '')) ?? $default->density,
            self::opacityOrDefault($stored['markOpacity'] ?? null, $default->markOpacity),
            \is_bool($stored['previewPane'] ?? null) ? $stored['previewPane'] : $default->previewPane,
            Theme::tryFrom((string) ($stored['theme'] ?? '')) ?? $default->theme,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'accent' => $this->accent->value,
            'proseFont' => $this->proseFont->value,
            'density' => $this->density->value,
            'markOpacity' => $this->markOpacity->toFloat(),
            'previewPane' => $this->previewPane,
            'theme' => $this->theme->value,
        ];
    }

    public function withAccent(Accent $accent): self
    {
        return new self($accent, $this->proseFont, $this->density, $this->markOpacity, $this->previewPane, $this->theme);
    }

    public function withProseFont(ProseFont $proseFont): self
    {
        return new self($this->accent, $proseFont, $this->density, $this->markOpacity, $this->previewPane, $this->theme);
    }

    public function withDensity(Density $density): self
    {
        return new self($this->accent, $this->proseFont, $density, $this->markOpacity, $this->previewPane, $this->theme);
    }

    public function withMarkOpacity(MarkOpacity $markOpacity): self
    {
        return new self($this->accent, $this->proseFont, $this->density, $markOpacity, $this->previewPane, $this->theme);
    }

    public function withPreviewPane(bool $previewPane): self
    {
        return new self($this->accent, $this->proseFont, $this->density, $this->markOpacity, $previewPane, $this->theme);
    }

    public function withTheme(Theme $theme): self
    {
        return new self($this->accent, $this->proseFont, $this->density, $this->markOpacity, $this->previewPane, $theme);
    }

    public function equals(self $other): bool
    {
        return $this->accent === $other->accent
            && $this->proseFont === $other->proseFont
            && $this->density === $other->density
            && $this->markOpacity->equals($other->markOpacity)
            && $this->previewPane === $other->previewPane
            && $this->theme === $other->theme;
    }

    private static function opacityOrDefault(mixed $stored, MarkOpacity $default): MarkOpacity
    {
        if (!\is_float($stored) && !\is_int($stored)) {
            return $default;
        }

        try {
            return MarkOpacity::fromFloat((float) $stored);
        } catch (InvalidArgumentException) {
            return $default;
        }
    }
}
