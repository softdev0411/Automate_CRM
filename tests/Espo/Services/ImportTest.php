<?php

namespace tests\Espo\Core;

use tests\ReflectionHelper;


class ImportTest extends \PHPUnit_Framework_TestCase
{
    protected $objects;
    
    protected $importService;


    protected function setUp()
    {
        $this->objects['serviceFactory'] = $this->getMockBuilder('\Espo\Core\ServiceFactory')->disableOriginalConstructor()->getMock();

        $this->objects['config'] = $this->getMockBuilder('\Espo\Core\Utils\Config')->disableOriginalConstructor()->getMock();
        
        $this->objects['fileManager'] = $this->getMockBuilder('\Espo\Core\Utils\File\Manager')->disableOriginalConstructor()->getMock();

        $this->objects['metadata'] = $this->getMockBuilder('\Espo\Core\Utils\Metadata')->disableOriginalConstructor()->getMock();
        
        $this->objects['acl'] = $this->getMockBuilder('\Espo\Core\Acl')->disableOriginalConstructor()->getMock();


        $this->importService = new \Espo\Services\Import();
        $this->importService->inject('serviceFactory', $this->objects['serviceFactory']);
        $this->importService->inject('config', $this->objects['config']);
        $this->importService->inject('fileManager', $this->objects['fileManager']);
        $this->importService->inject('metadata', $this->objects['metadata']);
        $this->importService->inject('acl', $this->objects['acl']);
        
    }

    protected function tearDown()
    {
        $this->importService = NULL;
    }
    
    
    function testImportRow()
    {
            
    }
}

