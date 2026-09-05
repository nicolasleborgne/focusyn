<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Domain;

use App\Organization\Domain\Event\OrganizationWasCreated;
use App\Organization\Domain\Exception\AlreadyAMember;
use App\Organization\Domain\Exception\NotAMember;
use App\Organization\Domain\Exception\OrganizationWouldLoseItsLastOwner;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\MembershipId;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Model\OrganizationRole;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Organization::class)]
final class OrganizationTest extends TestCase
{
    private const string CREATED_AT = '2026-09-05 10:00:00';

    public function testCreatingAnOrganizationMakesItsAuthorTheOwner(): void
    {
        $owner = MemberId::generate();

        $organization = self::create($owner);

        self::assertTrue($organization->hasMember($owner));
        self::assertSame(OrganizationRole::Owner, $organization->roleOf($owner));
    }

    public function testCreatingAnOrganizationAnnouncesIt(): void
    {
        $id = OrganizationId::generate();
        $owner = MemberId::generate();

        $organization = Organization::create($id, 'Atelier', 'atelier', $owner, MembershipId::generate(), new DateTimeImmutable(self::CREATED_AT));

        $events = $organization->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrganizationWasCreated::class, $events[0]);
        self::assertSame($id->toString(), $events[0]->organizationId);
    }

    public function testAPersonalOrganizationIsMarkedAsSuch(): void
    {
        $personal = Organization::createPersonal(
            OrganizationId::generate(),
            'Mon carnet',
            'mon-carnet',
            MemberId::generate(),
            MembershipId::generate(),
            new DateTimeImmutable(self::CREATED_AT),
        );

        self::assertTrue($personal->isPersonal());
        self::assertFalse(self::create()->isPersonal());
    }

    public function testAMemberCanBeAdded(): void
    {
        $organization = self::create();
        $newcomer = MemberId::generate();

        $organization->addMember(MembershipId::generate(), $newcomer, OrganizationRole::Member, new DateTimeImmutable(self::CREATED_AT));

        self::assertTrue($organization->hasMember($newcomer));
        self::assertSame(OrganizationRole::Member, $organization->roleOf($newcomer));
        self::assertCount(2, $organization->memberships());
    }

    public function testTheSamePersonCannotJoinTwice(): void
    {
        $organization = self::create();
        $newcomer = MemberId::generate();
        $organization->addMember(MembershipId::generate(), $newcomer, OrganizationRole::Member, new DateTimeImmutable(self::CREATED_AT));

        $this->expectException(AlreadyAMember::class);

        $organization->addMember(MembershipId::generate(), $newcomer, OrganizationRole::Admin, new DateTimeImmutable(self::CREATED_AT));
    }

    public function testAMemberCanLeave(): void
    {
        $owner = MemberId::generate();
        $organization = self::create($owner);
        $newcomer = MemberId::generate();
        $organization->addMember(MembershipId::generate(), $newcomer, OrganizationRole::Member, new DateTimeImmutable(self::CREATED_AT));

        $organization->removeMember($newcomer);

        self::assertFalse($organization->hasMember($newcomer));
        self::assertTrue($organization->hasMember($owner));
    }

    public function testRemovingSomebodyWhoIsNotAMemberIsRefused(): void
    {
        $this->expectException(NotAMember::class);

        self::create()->removeMember(MemberId::generate());
    }

    public function testTheLastOwnerCannotLeave(): void
    {
        $owner = MemberId::generate();
        $organization = self::create($owner);
        $organization->addMember(MembershipId::generate(), MemberId::generate(), OrganizationRole::Admin, new DateTimeImmutable(self::CREATED_AT));

        $this->expectException(OrganizationWouldLoseItsLastOwner::class);

        $organization->removeMember($owner);
    }

    public function testAnOwnerCanLeaveOnceAnotherOwnerExists(): void
    {
        $first = MemberId::generate();
        $second = MemberId::generate();
        $organization = self::create($first);
        $organization->addMember(MembershipId::generate(), $second, OrganizationRole::Owner, new DateTimeImmutable(self::CREATED_AT));

        $organization->removeMember($first);

        self::assertSame(OrganizationRole::Owner, $organization->roleOf($second));
    }

    public function testTheLastOwnerCannotBeDemoted(): void
    {
        $owner = MemberId::generate();
        $organization = self::create($owner);

        $this->expectException(OrganizationWouldLoseItsLastOwner::class);

        $organization->changeRole($owner, OrganizationRole::Admin);
    }

    public function testARoleCanBeChanged(): void
    {
        $organization = self::create();
        $member = MemberId::generate();
        $organization->addMember(MembershipId::generate(), $member, OrganizationRole::Member, new DateTimeImmutable(self::CREATED_AT));

        $organization->changeRole($member, OrganizationRole::Admin);

        self::assertSame(OrganizationRole::Admin, $organization->roleOf($member));
    }

    public function testSomebodyWhoNeverJoinedHasNoRole(): void
    {
        self::assertNull(self::create()->roleOf(MemberId::generate()));
    }

    public function testItCanBeRenamed(): void
    {
        $organization = self::create();

        $organization->rename('Atelier du soir', 'atelier-du-soir');

        self::assertSame('Atelier du soir', $organization->name());
        self::assertSame('atelier-du-soir', $organization->slug());
    }

    public function testItRefusesAnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::create()->rename('   ', 'vide');
    }

    private static function create(?MemberId $owner = null): Organization
    {
        return Organization::create(
            OrganizationId::generate(),
            'Atelier',
            'atelier',
            $owner ?? MemberId::generate(),
            MembershipId::generate(),
            new DateTimeImmutable(self::CREATED_AT),
        );
    }
}
