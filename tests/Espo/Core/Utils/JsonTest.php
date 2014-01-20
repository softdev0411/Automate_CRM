<?php

namespace tests\Espo\Core\Utils;

use Espo\Core\Utils\Json;

class JsonTest extends \PHPUnit_Framework_TestCase
{
	function testEncode()
	{
		$testVal= array('testOption'=>'Test');
		$this->assertEquals(json_encode($testVal), Json::encode($testVal));
	}

	function testDecode()
	{
		$testVal= array('testOption'=>'Test');
		$this->assertEquals($testVal, Json::decode(json_encode($testVal), true));        

		$test= '{"folder":"data\/logs"}';
        $this->assertEquals('data/logs', Json::decode($test)->folder);  	     
	}

	function testIsJSON()
	{
		$this->assertTrue(Json::isJSON('{"database":{"driver":"pdo_mysql","host":"localhost"},"devMode":true}'));  		
		
	    $this->assertTrue(Json::isJSON('[]'));
		
	    $this->assertTrue(Json::isJSON('{}'));
		
	    $this->assertTrue(Json::isJSON('true'));
		
		$this->assertFalse(Json::isJSON('some string'));
		
		$this->assertTrue(Json::isJSON(true));
		$this->assertEquals('true', json_encode(true));
		
		$this->assertFalse(Json::isJSON(false));
		$this->assertEquals('false', json_encode(false)); 	   
	}



}

?>