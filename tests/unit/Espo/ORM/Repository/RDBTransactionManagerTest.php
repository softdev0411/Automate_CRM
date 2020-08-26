<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2020 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
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

namespace tests\unit\Espo\ORM\Repository;

require_once 'tests/unit/testData/DB/Entities.php';

use Espo\ORM\{
    Repository\RDBTransactionManager,
    TransactionManager,
};

use RuntimeException;

class RDBTransactionManagerTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp() : void
    {
        $this->wrappee = $this->getMockBuilder(TransactionManager::class)->disableOriginalConstructor()->getMock();

        $this->manager = new RDBTransactionManager($this->wrappee);
    }


    public function testStartOnce()
    {

        $this->wrappee
            ->expects($this->once())
            ->method('start');

        $this->manager->start();
    }

    public function testException()
    {
        $this->wrappee
            ->expects($this->once())
            ->method('start');

        $this->wrappee
            ->expects($this->once())
            ->method('getLevel')
            ->will($this->returnValue(1));

        $this->expectException(RuntimeException::class);

        $this->manager->start();

        $this->manager->start();
    }

    public function testCommit()
    {
        $this->wrappee
            ->expects($this->at(0))
            ->method('start');

        $this->wrappee
            ->expects($this->at(1))
            ->method('getLevel')
            ->will($this->returnValue(1));

        $this->wrappee
            ->expects($this->at(2))
            ->method('getLevel')
            ->will($this->returnValue(2));

        $this->wrappee
            ->expects($this->at(3))
            ->method('commit');

        $this->wrappee
            ->expects($this->at(4))
            ->method('getLevel')
            ->will($this->returnValue(1));

        $this->wrappee
            ->expects($this->at(5))
            ->method('commit');


        $this->manager->start();

        $this->manager->commit();
    }
}
