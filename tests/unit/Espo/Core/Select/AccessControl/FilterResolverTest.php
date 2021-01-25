<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2021 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace tests\unit\Espo\Core\Select\AccessControl;

use Espo\Core\{
    Select\AccessControl\FilterResolver,
    Acl,
    Portal\Acl as AclPortal,
};

use Espo\{
    Entities\User,
};

class FilterResolverTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp() : void
    {
        $this->acl = $this->createMock(Acl::class);
        $this->aclPortal = $this->createMock(AclPortal::class);
        $this->user = $this->createMock(User::class);

        $this->entityType = 'Test';
    }

    public function testResolveRegularOnlyOwn()
    {
        $this->assertEquals(
            'onlyOwn',
            $this->initResolveTest(false, false, 'checkReadOnlyOwn')
        );
    }

    public function testResolveRegularOnlyTeam()
    {
        $this->assertEquals(
            'onlyTeam',
            $this->initResolveTest(false, false, 'checkReadOnlyTeam')
        );
    }

    public function testResolveRegularNo()
    {
        $this->assertEquals(
            'no',
            $this->initResolveTest(false, false, 'checkReadNo')
        );
    }

    public function testResolvePortalOnlyOwn()
    {
        $this->assertEquals(
            'portalOnlyOwn',
            $this->initResolveTest(true, false, 'checkReadOnlyOwn')
        );
    }

    public function testResolvePortalOnlyAccount()
    {
        $this->assertEquals(
            'portalOnlyAccount',
            $this->initResolveTest(true, false, 'checkReadOnlyAccount')
        );
    }

    public function testResolvePortalOnlyContact()
    {
        $this->assertEquals(
            'portalOnlyContact',
            $this->initResolveTest(true, false, 'checkReadOnlyContact')
        );
    }

    public function testResolvePortalNo()
    {
        $this->assertEquals(
            'no',
            $this->initResolveTest(true, false, 'checkReadNo')
        );
    }

    public function testResolveAdmin()
    {
        $this->assertEquals(
            null,
            $this->initResolveTest(false, true, null)
        );
    }

    protected function initResolveTest(bool $isPortal = false, bool $isAdmin = false, ?string $method) : ?string
    {
        $acl = $this->acl;

        if ($isPortal) {
            $acl = $this->aclPortal;
        }

        $this->resolver = new FilterResolver(
            $this->entityType,
            $this->user,
            $acl
        );

        $this->user
            ->expects($this->any())
            ->method('isAdmin')
            ->willReturn($isAdmin);

        $this->user
            ->expects($this->any())
            ->method('isPortal')
            ->willReturn($isPortal);

        if ($method) {
            $acl
                ->expects($this->any())
                ->method($method)
                ->with($this->entityType)
                ->willReturn(true);
        }

        return $this->resolver->resolve();
    }
}
