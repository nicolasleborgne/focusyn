<?php

declare(strict_types=1);

namespace App\Tests\Factory\Organization;

use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\MembershipId;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<Organization> */
final class OrganizationFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Organization::class;
    }

    public function ownedBy(MemberId $owner): static
    {
        return $this->with(['owner' => $owner]);
    }

    public function personal(): static
    {
        return $this->with(['personal' => true]);
    }

    public function named(string $name, string $slug): static
    {
        return $this->with(['name' => $name, 'slug' => $slug]);
    }

    protected function defaults(): array
    {
        $slug = self::faker()->unique()->slug(2);

        return [
            'id' => OrganizationId::generate(),
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'owner' => MemberId::generate(),
            'ownerMembershipId' => MembershipId::generate(),
            'createdAt' => DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'personal' => false,
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(
            /** @param array{id: OrganizationId, name: string, slug: string, owner: MemberId, ownerMembershipId: MembershipId, createdAt: DateTimeImmutable, personal: bool} $parameters */
            static function (array $parameters): Organization {
                $factory = $parameters['personal']
                    ? Organization::createPersonal(...)
                    : Organization::create(...);

                return $factory(
                    $parameters['id'],
                    $parameters['name'],
                    $parameters['slug'],
                    $parameters['owner'],
                    $parameters['ownerMembershipId'],
                    $parameters['createdAt'],
                );
            },
        );
    }
}
