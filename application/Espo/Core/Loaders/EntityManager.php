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
    Utils\Config,
    Utils\Metadata\OrmMetadata,
    InjectableFactory,
    ORM\EntityManager as EntityManagerService,
    ORM\RepositoryFactory,
    ORM\EntityFactory,
    ORM\Helper,
};

class EntityManager implements Loader
{
    protected $config;
    protected $injectableFactory;
    protected $ormMetadata;

    public function __construct(Config $config, InjectableFactory $injectableFactory, OrmMetadata $ormMetadata)
    {
        $this->config = $config;
        $this->injectableFactory = $injectableFactory;
        $this->ormMetadata = $ormMetadata;
    }

    public function load()
    {
        $entityFactory = $this->injectableFactory->create(EntityFactory::class);

        $repositoryFactory = $this->injectableFactory->createWith(RepositoryFactory::class, [
            'entityFactory' => $entityFactory,
        ]);

        $helper = $this->injectableFactory->create(Helper::class);

        $config = $this->config;

        $params = [
            'metadata' => $this->ormMetadata->getData(),
            'host' => $config->get('database.host'),
            'port' => $config->get('database.port'),
            'dbname' => $config->get('database.dbname'),
            'user' => $config->get('database.user'),
            'charset' => $config->get('database.charset', 'utf8'),
            'password' => $config->get('database.password'),
            'driver' => $config->get('database.driver'),
            'platform' => $config->get('database.platform'),
            'sslCA' => $config->get('database.sslCA'),
            'sslCert' => $config->get('database.sslCert'),
            'sslKey' => $config->get('database.sslKey'),
            'sslCAPath' => $config->get('database.sslCAPath'),
            'sslCipher' => $config->get('database.sslCipher'),
        ];

        $entityManager = $this->injectableFactory->createWith(EntityManagerService::class, [
            'params' => $params,
            'repositoryFactory' => $repositoryFactory,
            'entityFactory' => $entityFactory,
            'helper' => $helper,
        ]);

        return $entityManager;
    }
}
