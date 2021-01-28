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

namespace tests\unit\Espo\Core\Utils;

use tests\unit\ReflectionHelper;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigFileManager;

class ConfigTest extends \PHPUnit\Framework\TestCase
{
    protected $object;

    protected $objects;

    protected $defaultTestConfig = 'tests/unit/testData/Utils/Config/config.php';

    protected $configPath = 'tests/unit/testData/cache/config.php';

    protected $systemConfigPath = 'tests/unit/testData/Utils/Config/systemConfig.php';

    protected function setUp() : void
    {
        $this->fileManager = new ConfigFileManager;

        /*copy defaultTestConfig file to cache*/
        if (!file_exists($this->configPath)) {
            copy($this->defaultTestConfig, $this->configPath);
        }

        $this->config = new Config($this->fileManager);

        $this->reflection = new ReflectionHelper($this->config);

        $this->reflection->setProperty('configPath', $this->configPath);
        $this->reflection->setProperty('systemConfigPath', $this->systemConfigPath);
    }

    protected function tearDown() : void
    {
        $this->config = NULL;
    }

    public function testLoadConfig()
    {
        $this->assertArrayHasKey('database', $this->reflection->invokeMethod('loadConfig', array()));

        $this->assertArrayHasKey('dateFormat', $this->reflection->invokeMethod('loadConfig', array()));
    }

    public function testGet()
    {
        $result = array(
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'dbname' => 'espocrm',
            'user' => 'root',
            'password' => '',
        );
        $this->assertEquals($result, $this->config->get('database'));

        $result = 'pdo_mysql';
        $this->assertEquals($result, $this->config->get('database.driver'));


        $result = 'YYYY-MM-DD';
        $this->assertEquals($result, $this->config->get('dateFormat'));

        $this->assertTrue($this->config->get('isInstalled'));
    }

    public function testSystemConfigMerge()
    {
        $configDataWithoutSystem = $this->fileManager->getPhpContents($this->configPath);
        $this->assertArrayNotHasKey('systemItems', $configDataWithoutSystem);
        $this->assertArrayNotHasKey('adminItems', $configDataWithoutSystem);

        $configData = $this->reflection->invokeMethod('loadConfig', array());

        $this->assertArrayHasKey('systemItems', $configData);
        $this->assertArrayHasKey('adminItems', $configData);
    }
}
