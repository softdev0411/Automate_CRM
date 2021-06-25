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

namespace Espo\Core\Utils;

use Espo\Core\{
    Utils\File\Manager as FileManager,
    Utils\DataCache,
    Utils\Json,
};

/**
 * Gets module parameters.
 */
class Module
{
    private const DEFAULT_ORDER = 11;

    private $useCache;

    private $data = null;

    private $list = null;

    private $cacheKey = 'modules';

    private $pathToModules = 'application/Espo/Modules';

    private $moduleFilePath = 'Resources/module.json';

    private $fileManager;

    private $dataCache;

    public function __construct(
        FileManager $fileManager,
        ?DataCache $dataCache = null,
        bool $useCache = false
    ) {

        $this->fileManager = $fileManager;
        $this->dataCache = $dataCache;

        $this->useCache = $useCache;
    }

    /**
     * Get module parameters.
     *
     * @param string|array|null $key
     * @param mixed $defaultValue
     * @return mixed
     */
    public function get($key = null, $defaultValue = null)
    {
        if ($this->data === null) {
            $this->init();
        }

        if ($key === null) {
            return $this->data;
        }

        return Util::getValueByKey($this->data, $key, $defaultValue);
    }

    private function init(): void
    {
        if ($this->useCache && $this->dataCache->has($this->cacheKey)) {
            $this->data = $this->dataCache->get($this->cacheKey);

            return;
        }

        $this->data = $this->loadData();

        if ($this->useCache) {
            $this->dataCache->store($this->cacheKey, $this->data);
        }
    }

    /**
     * Get an ordered list of modules.
     *
     * @return string[]
     *
     * @todo Use cache if available.
     */
    public function getOrderedList(): array
    {
        $moduleNameList = $this->getList();

        $modulesToSort = [];

        foreach ($moduleNameList as $moduleName) {
            if (empty($moduleName)) {
                continue;
            }

            if (isset($modulesToSort[$moduleName])) {
                continue;
            }

            $modulesToSort[$moduleName] = $this->get([$moduleName,  'order'], self::DEFAULT_ORDER);
        }

        array_multisort(
            array_values($modulesToSort),
            SORT_ASC,
            array_keys($modulesToSort),
            SORT_ASC,
            $modulesToSort
        );

        return array_keys($modulesToSort);
    }

    private function getList(): array
    {
        if ($this->list === null) {
            $this->list = $this->fileManager->getDirList($this->pathToModules);
        }

        return $this->list;
    }

    private function loadData(): array
    {
        $data = [];

        foreach ($this->getList() as $moduleName) {
            $path = $this->pathToModules . '/' . $moduleName . '/' . $this->moduleFilePath;

            $itemContents = $this->fileManager->getContents($path);

            $data[$moduleName] = Json::decode($itemContents, true);

            $data[$moduleName]['order'] = $data[$moduleName]['order'] ?? self::DEFAULT_ORDER;
        }

        return $data;
    }
}
