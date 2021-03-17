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

namespace tests\unit\Espo\Core\Fields\PhoneNumber;

use Espo\Core\{
    Fields\PhoneNumber,
};

use RuntimeException;

class PhoneNumberTest extends \PHPUnit\Framework\TestCase
{
    public function testInvalidEmpty()
    {
        $this->expectException(RuntimeException::class);

        PhoneNumber::fromNumber('');
    }

    public function testCloneInvalid()
    {
        $number = PhoneNumber::fromNumber('+100')->invalid();

        $this->assertEquals('+100', $number->getNumber());

        $this->assertTrue($number->isInvalid());
        $this->assertFalse($number->isOptedOut());

        $this->assertNull($number->getType());
    }

    public function testCloneOptedOut()
    {
        $number = PhoneNumber::fromNumber('+100')->optedOut();

        $this->assertFalse($number->isInvalid());
        $this->assertTrue($number->isOptedOut());
    }

    public function testCloneWithType()
    {
        $number = PhoneNumber::fromNumberAndType('+100', 'Office');

        $this->assertEquals('+100', $number->getNumber());
        $this->assertEquals('Office', $number->getType());
    }

    public function testCloneNotOptedOut()
    {
        $number = PhoneNumber::fromNumber('+100')
            ->optedOut()
            ->notOptedOut()
            ->invalid()
            ->notInvalid();

        $this->assertFalse($number->isInvalid());
        $this->assertFalse($number->isOptedOut());
    }
}
