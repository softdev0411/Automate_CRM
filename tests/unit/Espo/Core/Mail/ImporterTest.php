<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2015 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
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

namespace tests\unit\Espo\Core\Mail;

use \Espo\Entities\Attachment;
use \Espo\Entities\Email;

class ImporterTest extends \PHPUnit_Framework_TestCase
{
    function testImport1()
    {
        $entityManager = $this->getMockBuilder('\\Espo\\Core\\ORM\\EntityManager')->disableOriginalConstructor()->getMock();
        $config = $this->getMockBuilder('\\Espo\\Core\\Utils\\Config')->disableOriginalConstructor()->getMock();

        $emailRepository = $this->getMockBuilder('\\Espo\\Core\\ORM\\Repositories\\RDB')->disableOriginalConstructor()->getMock();
        $emptyRepository = $this->getMockBuilder('\\Espo\\Core\\ORM\\Repositories\\RDB')->disableOriginalConstructor()->getMock();

        $emailRepository
            ->expects($this->any())
            ->method('where')
            ->will($this->returnSelf());

        $emptyRepository
            ->expects($this->any())
            ->method('where')
            ->will($this->returnSelf());

        $repositoryMap = array(
             array('Email', $emailRepository),
             array('Account', $emptyRepository),
             array('Contact', $emptyRepository),
             array('Lead', $emptyRepository)
        );

        $email = new \Espo\Entities\Email();
        $emailDefs = require('tests/unit/testData/Core/Mail/email_defs.php');
        $email->fields = $emailDefs['fields'];
        $email->relations = $emailDefs['relations'];

        $attachment = new \Espo\Entities\Attachment();
        $attachmentDefs = require('tests/unit/testData/Core/Mail/attachment_defs.php');
        $attachment->fields = $attachmentDefs['fields'];
        $attachment->relations = $attachmentDefs['relations'];

        $entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->will($this->returnValueMap($repositoryMap));

        $entityManager
            ->expects($this->any())
            ->method('saveEntity')
            ->with($this->isInstanceOf('\\Espo\\Entities\\Attachment'));

        $entityManager
            ->expects($this->once())
            ->method('saveEntity')
            ->with($this->isInstanceOf('\\Espo\\Entities\\Email'));

        $entityManager
            ->expects($this->any())
            ->method('getEntity')
            ->with($this->equalTo('Email'))
            ->will($this->returnValue($email));

        $config
            ->expects($this->any())
            ->method('get')
            ->will($this->returnValueMap(array(
                array('b2cMode', false)
            )));

        $contents = file_get_contents('tests/unit/testData/Core/Mail/test_email_1.eml');
        $importer = new \Espo\Core\Mail\Importer($entityManager, $config);

        $message = new \Zend\Mail\Storage\Message(array('raw' => $contents));

        $importer->importMessage($message, null, ['teamTestId'], ['userTestId']);

        $this->assertEquals('test 3', $email->get('name'));

        $teamIdList = $email->getLinkMultipleIdList('teams');
        $this->assertContains('teamTestId', $teamIdList);

        $userIdList = $email->getLinkMultipleIdList('users');
        $this->assertContains('userTestId', $userIdList);

        $this->assertContains('<br>Admin Test', $email->get('body'));
        $this->assertContains('Admin Test', $email->get('bodyPlain'));

        $this->assertEquals('<e558c4dfc2a0f0d60f5ebff474c97ffc/1466410740/1950@espo>', $email->get('messageId'));

    }
}