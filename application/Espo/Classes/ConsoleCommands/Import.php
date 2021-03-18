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

namespace Espo\Classes\ConsoleCommands;

use Espo\Core\{
    ServiceFactory,
    Console\Command,
    Console\Params,
    Console\IO,
};

use Throwable;

class Import implements Command
{
    protected $serviceFactory;

    public function __construct(ServiceFactory $serviceFactory)
    {
        $this->serviceFactory = $serviceFactory;
    }

    public function run(Params $params, IO $io) : void
    {
        $id = $params->getOption('id');
        $filePath = $params->getOption('file');
        $paramsId = $params->getOption('paramsId');

        $forceResume = $params->hasFlag('resume');
        $revert = $params->hasFlag('revert');

        $service = $this->serviceFactory->create('Import');

        if (!$id && $filePath) {
            if (!$paramsId) {
                $io->writeLine("You need to specify --params-id option.");

                return;
            }

            if (!file_exists($filePath)) {
                $io->writeLine("File not found.");

                return;
            }

            $contents = file_get_contents($filePath);

            try {
                $result = $service->importFileWithParamsId($contents, $paramsId);

                $resultId = $result->id;
                $countCreated = $result->countCreated;
                $countUpdated = $result->countUpdated;
            }
            catch (Throwable $e) {
                $io->writeLine("Error occured: ". $e->getMessage() . "");

                return;
            }

            $io->writeLine("Finished. Import ID: {$resultId}. Created: {$countCreated}. Updated: {$countUpdated}.");

            return;
        }

        if ($id && $revert) {
            $io->writeLine("Reverting import...");

            try {
                $service->revert($id);
            }
            catch (Throwable $e) {
                $io->writeLine("Error occured: " . $e->getMessage() . "");

                return;
            }

            $io->writeLine("Finished.");

            return;
        }

        if ($id) {
            $io->writeLine("Running import, this may take a while...");

            try {
                $result = $service->importById($id, true, $forceResume);
            }
            catch (Throwable $e) {
                $io->writeLine("Error occured: " . $e->getMessage() . "");

                return;
            }

            $countCreated = $result->countCreated;
            $countUpdated = $result->countUpdated;

            $io->writeLine("Finished. Created: {$countCreated}. Updated: {$countUpdated}.");

            return;
        }

        $io->writeLine("Not enough params passed.");
    }
}
