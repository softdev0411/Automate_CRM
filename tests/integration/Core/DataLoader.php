<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2023 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
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

namespace tests\integration\Core;

use Espo\Core\Application;
use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Database\Helper as DatabaseHelper;
use Espo\Core\Utils\File\Manager as FileManager;
use Espo\Core\Utils\PasswordHash;
use Espo\Entities\Preferences;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Exception;

class DataLoader
{
    private Application $application;
    private PasswordHash $passwordHash;

    public function __construct(Application $application)
    {
        $this->application = $application;

        $config = $this->getContainer()->getByClass(Config::class);

        $this->passwordHash = new PasswordHash($config);
    }

    private function getContainer(): Container
    {
        return $this->application->getContainer();
    }

    private function getPasswordHash(): PasswordHash
    {
        return $this->passwordHash;
    }

    public function loadData(string $dataFile): void
    {
        if (!file_exists($dataFile)) {
            return;
        }

        $data = include($dataFile);

        $this->handleData($data);
    }

    public function setData(array $data): void
    {
        $this->handleData($data);
    }

    protected function handleData(array $fullData): void
    {
        foreach ($fullData as $type => $data) {
            $methodName = 'load' . ucfirst($type);

            if (!method_exists($this, $methodName)) {
                throw new Exception('DataLoader: Data type is not supported in dataFile.');
            }

            $this->$methodName($data);
        }
    }

    public function loadFiles(string $path): void
    {
        try {
            $fileManager = $this->getContainer()->getByClass(FileManager::class);

            $fileManager->copy($path, '.', true);
        }
        catch (Exception $e) {
            throw new Exception('Error loadFiles: ' . $e->getMessage());
        }
    }

    protected function loadEntities(array $data)
    {
        $entityManager = $this->getContainer()->getByClass(EntityManager::class);

        foreach ($data as $entityType => $entities) {
            foreach($entities as $entityData) {
                $entity = $entityManager->getEntityById($entityType, $entityData['id']);

                if (empty($entity)) {
                    $entity = $entityManager->getNewEntity($entityType);
                }

                foreach($entityData as $field => $value) {
                    if ($field == 'password' && $entityType == User::ENTITY_TYPE) {
                        $value = $this->getPasswordHash()->hash($value);
                    }

                    $entity->set($field, $value);
                }

                try {
                    $entityManager->saveEntity($entity);
                }
                catch (Exception $e) {
                    throw new Exception('Error loadEntities: ' . $e->getMessage() . ', ' . print_r($entityData, true));
                }
            }
        }
    }

    private function loadConfig(array $data): void
    {
        if (empty($data)) {
            return;
        }

        $config = $this->getContainer()->getByClass(Config::class);
        $config->set($data);

        try {
            $config->save();
        }
        catch (Exception $e) {
            throw new Exception('Error loadConfig: ' . $e->getMessage());
        }
    }

    private function loadPreferences(array $data): void
    {
        $entityManager = $this->getContainer()->getByClass(EntityManager::class);

        foreach ($data as $userId => $params) {
            $entityManager->getRepository(Preferences::ENTITY_TYPE)->resetToDefaults($userId);

            $preferences = $entityManager->getEntityById(Preferences::ENTITY_TYPE, $userId);
            $preferences->set($params);

            try {
                $entityManager->saveEntity($preferences);
            }
            catch (Exception $e) {
                throw new Exception('Error loadPreferences: ' . $e->getMessage());
            }
        }
    }

    /*private function loadSql(array $data): void
    {
        if (empty($data)) {
            return;
        }

        $helper = $this->getContainer()
            ->getByClass(InjectableFactory::class)
            ->create(DatabaseHelper::class);

        $pdo = $helper->getPDO();

        foreach ($data as $sql) {
            try {
                $pdo->query($sql);
            }
            catch (Exception $e) {
                throw new Exception('Error loadSql: ' . $e->getMessage() . ', sql: ' . $sql);
            }
        }
    }*/
}
