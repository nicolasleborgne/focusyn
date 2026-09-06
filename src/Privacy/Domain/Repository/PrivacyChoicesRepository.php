<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Repository;

use App\Privacy\Domain\Model\PrivacyChoices;
use App\Privacy\Domain\Model\SubjectId;

interface PrivacyChoicesRepository
{
    public function save(PrivacyChoices $choices): void;

    public function ofSubject(SubjectId $subjectId): ?PrivacyChoices;
}
