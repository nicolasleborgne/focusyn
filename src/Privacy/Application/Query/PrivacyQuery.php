<?php

declare(strict_types=1);

namespace App\Privacy\Application\Query;

use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Model\PrivacyChoices;
use App\Privacy\Domain\Model\Retention;
use App\Privacy\Domain\Model\SubjectId;
use App\Privacy\Domain\Repository\PrivacyChoicesRepository;

final readonly class PrivacyQuery
{
    public function __construct(
        private PrivacyChoicesRepository $choices,
        private PersonalDataExport $export,
    ) {
    }

    public function forSubject(SubjectId $subjectId): PrivacyView
    {
        $choices = $this->choices->ofSubject($subjectId);

        return new PrivacyView(
            consents: self::consentsOf($choices),
            retention: $choices?->retention() ?? Retention::TwelveMonths,
            summary: $this->export->summary(),
        );
    }

    /** @return array<string, bool> */
    private static function consentsOf(?PrivacyChoices $choices): array
    {
        $consents = [];

        foreach (Consent::cases() as $consent) {
            $consents[$consent->value] = $choices?->allows($consent) ?? false;
        }

        return $consents;
    }
}
