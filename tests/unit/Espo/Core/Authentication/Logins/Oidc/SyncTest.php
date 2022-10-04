<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2022 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
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

namespace tests\unit\Espo\Core\Authentication\Logins\Oidc;

use Espo\Core\Acl\Cache\Clearer;
use Espo\Core\Authentication\Oidc\Sync;
use Espo\Core\FieldProcessing\EmailAddress\Saver as EmailAddressSaver;
use Espo\Core\FieldProcessing\PhoneNumber\Saver as PhoneNumberSaver;
use Espo\Core\FieldProcessing\Relation\LinkMultipleSaver;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\PasswordHash;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class SyncTest extends TestCase
{
    private ?Sync $sync = null;
    private ?Config $config = null;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);

        $this->sync = new Sync(
            $this->createMock(EntityManager::class),
            $this->config,
            $this->createMock(LinkMultipleSaver::class),
            $this->createMock(EmailAddressSaver::class),
            $this->createMock(PhoneNumberSaver::class),
            $this->createMock(PasswordHash::class),
            $this->createMock(Clearer::class)
        );
    }

    public function testNormalizeUsername(): void
    {
        $this->config
            ->expects($this->any())
            ->method('get')
            ->with('userNameRegularExpression')
            ->willReturn('[^a-z0-9\-@_\.\s]');

        $this->assertEquals(
            'test_name',
            $this->sync->normalizeUsername('test_name')
        );

        $this->assertEquals(
            'test_name',
            $this->sync->normalizeUsername('test|name')
        );

        $this->assertEquals(
            'test@name',
            $this->sync->normalizeUsername('test@name')
        );

        $this->assertEquals(
            'test_name',
            $this->sync->normalizeUsername('test name')
        );
    }
}
