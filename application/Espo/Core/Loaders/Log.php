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

namespace Espo\Core\Loaders;

use Espo\Core\Utils;
use Monolog\Handler;

class Log
{
	private $container;

	public function __construct(\Espo\Core\Container $container)
	{
		$this->container = $container;
	}

	protected function getContainer()
	{
    	return $this->container;
	}

	public function load()
	{
		$config = $this->getContainer()->get('config');

		$logConfig = $config->get('logger');
		
		$log = new Utils\Log('Espo');	
		$levelCode = $log->getLevelCode($logConfig['level']);	

		if ($logConfig['isRotate']) {
			$handler = new Handler\RotatingFileHandler($logConfig['path'], $logConfig['maxRotateFiles'], $levelCode);
		} else {
			$handler = new Handler\StreamHandler($logConfig['path'], $levelCode);
		}
		$log->pushHandler($handler);
		\Monolog\ErrorHandler::register($log);			

		return $log;
	}
}

