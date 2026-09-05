<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Domain;

use App\Organization\Domain\Model\OrganizationRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationRole::class)]
final class OrganizationRoleTest extends TestCase
{
    public function testOnlyOwnersAndAdministratorsManageMembers(): void
    {
        self::assertTrue(OrganizationRole::Owner->canManageMembers());
        self::assertTrue(OrganizationRole::Admin->canManageMembers());
        self::assertFalse(OrganizationRole::Member->canManageMembers());
    }

    public function testOnlyOwnersTouchBillingAndDeletion(): void
    {
        self::assertTrue(OrganizationRole::Owner->canAdministerOrganization());
        self::assertFalse(OrganizationRole::Admin->canAdministerOrganization());
        self::assertFalse(OrganizationRole::Member->canAdministerOrganization());
    }
}
