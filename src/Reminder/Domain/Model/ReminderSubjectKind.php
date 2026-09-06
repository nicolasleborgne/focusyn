<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Model;

/**
 * Ce à quoi un rappel peut se rattacher.
 *
 * Les valeurs sont des chaînes et non des types d'un autre contexte : Reminder
 * ne connaît ni `NoteId` ni `TaskItemId`, et n'a pas à les connaître pour tenir
 * une échéance.
 */
enum ReminderSubjectKind: string
{
    case Note = 'note';
    case Task = 'task';
}
