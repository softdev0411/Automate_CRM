<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014  Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
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
 ************************************************************************/

namespace Espo\Core;

use \Espo\Core\Exceptions\Error;

use \Espo\Core\Utils\Util;

class ServiceFactory
{
	private $container;

	protected $cacheFile = 'data/cache/application/services.php';

	/**
     * @var array - path to Service files
     */
	protected $paths = array(
		'corePath' => 'application/Espo/Services',
    	'modulePath' => 'application/Espo/Modules/{*}/Services',
    	'customPath' => 'custom/Espo/Custom/Services',
	);

	protected $data;

    public function __construct(Container $container)
    {
    	$this->container = $container;
    }

	protected function init()
	{
		$config = $this->getContainer()->get('config');

		if (file_exists($this->cacheFile) && $config->get('useCache')) {
			$this->data = $this->getFileManager()->getContents($this->cacheFile);
		} else {
			$this->data = $this->getClassNameHash($this->paths['corePath']);

	    	foreach ($this->getContainer()->get('metadata')->getModuleList() as $moduleName) {
	    		$path = str_replace('{*}', $moduleName, $this->paths['modulePath']);
				$this->data = array_merge($this->data, $this->getClassNameHash($path));
	    	}

	    	$this->data = array_merge($this->data, $this->getClassNameHash($this->paths['customPath']));

			if ($config->get('useCache')) {
				$result = $this->getFileManager()->putContentsPHP($this->cacheFile, $this->data);
				if ($result == false) {
					throw new \Espo\Core\Exceptions\Error();
				}
			}
		}
	}

	protected function getFileManager()
	{
		return $this->container->get('fileManager');
	}

	protected function getContainer()
	{
		return $this->container;
	}

	protected function getClassName($name)
	{
		$name = Util::normilizeClassName($name);

		if (!isset($this->data)) {
			$this->init();
		}

		$name = ucfirst($name);
		if (isset($this->data[$name])) {
			return $this->data[$name];
		}

        return false;
	}

	public function checkExists($name) {
		$className = $this->getClassName($name);
		if (!empty($className)) {
			return true;
		}
	}

    public function create($name)
    {
    	$className = $this->getClassName($name);
    	if (empty($className)) {
    		throw new Error();
    	}
    	return $this->createByClassName($className);
    }

	protected function createByClassName($className)
	{
    	if (class_exists($className)) {
    		$service = new $className();
    		$dependencies = $service->getDependencyList();
    		foreach ($dependencies as $name) {
    			$service->inject($name, $this->container->get($name));
    		}
    		return $service;
    	}
    	throw new Error("Class '$className' does not exist");
	}

	// TODO delegate to another class
	protected function getClassNameHash($dirs)
	{
		if (is_string($dirs)) {
			$dirs = (array) $dirs;
		}

		$data = array();

		foreach ($dirs as $dir) {
			if (file_exists($dir)) {
	        	$fileList = $this->getFileManager()->getFileList($dir, false, '\.php$', 'file');
	            foreach ($fileList as $file) {
					$filePath = Util::concatPath($dir, $file);
                	$className = Util::getClassName($filePath);
                	$fileName = $this->getFileManager()->getFileName($filePath);
					$data[$fileName] = $className;
	            }
			}
		}
		return $data;
	}
}

