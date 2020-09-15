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

use Espo\Core\Exceptions\Error;

class BeforeUpgrade
{
    public function run($container)
    {
        $this->container = $container;

        $this->processMyIsamCheck();

        $this->processNextNumberAlterTable();
    }

    protected function processMyIsamCheck()
    {
        $myisamTableList = $this->getMyIsamTableList();

        if (empty($myisamTableList)) {
            return;
        }

        $isCli = (substr(php_sapi_name(), 0, 3) == 'cli') ? true : false;

        $tableListString = implode(", ", $myisamTableList);

        $lineBreak = $isCli ? "\n" : "<br>";

        $link = "https://www.espocrm.com/blog/converting-myisam-engine-to-innodb";

        $linkString = $isCli ? $link : "<a href=\"{$link}\" target=\"_blank\">link</a>";

        $message =
            "In v6.0 we have dropped a support of MyISAM engine for DB tables. " .
            "You have the following tables that use MyISAM: {$tableListString}.{$lineBreak}" .
            "Please change the engine to InnoDB for these tables then run upgrade again.{$lineBreak}" .
            "See: {$linkString}.";

        throw new Error($message);
    }

    protected function getMyIsamTableList()
    {
        $container = $this->container;

        $pdo = $container->get('entityManager')->getPDO();
        $databaseInfo = $container->get('config')->get('database');

        try {
            $sth = $pdo->prepare("
                SELECT TABLE_NAME as tableName
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = '". $databaseInfo['dbname'] ."'
                AND ENGINE = 'MyISAM'
            ");

            $sth->execute();
        }
        catch (Exception $e) {
            return [];
        }

        $tableList = $sth->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tableList)) {
            return [];
        }

        return $tableList;
    }

    protected function processNextNumberAlterTable()
    {
        $pdo = $this->container->get('entityManager')->getPDO();

        $q1 = "ALTER TABLE `next_number` CHANGE `entity_type` `entity_type` VARCHAR(100)";
        $q2 = "ALTER TABLE `next_number` CHANGE `field_name` `field_name` VARCHAR(100)";

        $pdo->exec($q1);
        $pdo->exec($q2);
    }
}
