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

namespace Espo\ORM;

use PDO;
use PDOStatement;

use Exception;
use RuntimeException;

/**
 * Executes SQL queries.
 */
class SqlExecutor
{
    protected $pdo;

    const MAX_ATTEMPT_COUNT = 4;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Execute a query.
     */
    public function execute(string $sql, bool $rerunIfDeadlock = false) : PDOStatement
    {
        if (!$rerunIfDeadlock) {
            return $this->executeSqlWithDeadlockHandling($sql, 1);
        }

        return $this->executeSqlWithDeadlockHandling($sql);
    }

    protected function executeSqlWithDeadlockHandling(string $sql, ?int $counter = null) : PDOStatement
    {
        $counter = $counter ?? self::MAX_ATTEMPT_COUNT;

        $sth = null;

        try {
            $sth = $this->pdo->query($sql);
        } catch (Exception $e) {
            $counter--;

            if ($counter === 0 || !$this->isExceptionIsDeadlock($e)) {
                throw $e;
            }

            return $this->executeSqlWithDeadlockHandling($sql, $counter);
        }

        if (!$sth) {
            throw new RuntimeException("Query execution failure.");
        }

        return $sth;
    }

    protected function isExceptionIsDeadlock(Exception $e)
    {
        return isset($e->errorInfo) && $e->errorInfo[0] == 40001 && $e->errorInfo[1] == 1213;
    }
}
