<?php

declare(strict_types=1);

namespace App\Organization\Application\Query;

final readonly class TeamView
{
    /**
     * @param list<MemberEntry>     $members
     * @param list<InvitationEntry> $invitations
     * @param list<SpaceEntry>      $spaces
     */
    public function __construct(
        public string $id,
        public string $name,
        public bool $personal,
        public array $members,
        public array $invitations,
        public array $spaces,
    ) {
    }
}
