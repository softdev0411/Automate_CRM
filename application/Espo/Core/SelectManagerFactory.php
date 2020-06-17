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

namespace Espo\Core;

use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Util;

use Espo\Core\InjectableFactory;
use Espo\Core\SelectManagers\Base as BaseSelectManager;
use Espo\Entities\User;
use Espo\Core\ORM\EntityManager;

class SelectManagerFactory
{
    private $entityManager;

    private $user;

    private $acl;

    private $metadata;

    private $injectableFactory;

    private $fieldManagerUtil;

    private $classFinder;

    protected $baseClassName = '\\Espo\\Core\\SelectManagers\\Base';

    public function __construct(
        EntityManager $entityManager,
        User $user,
        Acl $acl,
        AclManager $aclManager,
        Utils\Metadata $metadata,
        Utils\Config $config,
        Utils\FieldManagerUtil $fieldManagerUtil,
        InjectableFactory $injectableFactory,
        Utils\ClassFinder $classFinder)
    {
        $this->entityManager = $entityManager;
        $this->user = $user;
        $this->acl = $acl;
        $this->aclManager = $aclManager;
        $this->metadata = $metadata;
        $this->config = $config;
        $this->fieldManagerUtil = $fieldManagerUtil;
        $this->injectableFactory = $injectableFactory;
        $this->classFinder = $classFinder;
    }

    public function create(string $entityType, ?User $user = null) : BaseSelectManager
    {
        $className = $this->classFinder->find('SelectManagers', $entityType);

        if (!$className || !class_exists($className)) {
            $className = $this->baseClassName;
        }

        if ($user) {
            $acl = $this->aclManager->createUserAcl($user);
        } else {
            $acl = $this->acl;
            $user = $this->user;
        }

        $selectManager = new $className(
            $this->entityManager,
            $user,
            $acl,
            $this->aclManager,
            $this->metadata,
            $this->config,
            $this->fieldManagerUtil,
            $this->injectableFactory
        );
        $selectManager->setEntityType($entityType);

        return $selectManager;
    }
}
