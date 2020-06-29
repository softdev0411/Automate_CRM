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

namespace Espo\Core\Loaders;

use Espo\Core\{
    InjectableFactory,
    Utils\ClassFinder,
    Utils\Config,
    ORM\EntityManager,
    AclManager as AclManagerService,
};

class AclManager implements Loader
{
    protected $injectableFactory;
    protected $classFinder;
    protected $config;
    protected $entityManager;

    public function __construct(
        InjectableFactory $injectableFactory, ClassFinder $classFinder, Config $config, EntityManager $entityManager
    ) {
        $this->injectableFactory = $injectableFactory;
        $this->classFinder = $classFinder;
        $this->config = $config;
        $this->entityManager = $entityManager;
    }

    public function load()
    {
        return new AclManagerService($this->injectableFactory, $this->classFinder, $this->config, $this->entityManager);
    }
}
