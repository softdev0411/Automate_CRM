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

namespace Espo\Core\ApplicationRunners;

use Espo\Core\{
    Exceptions\NotFound,
    Utils\ClientManager,
    Utils\Config,
    Portal\Application as PortalApplication,
    Portal\ApplicationRunners\Client as PortalPortalClient,
    Portal\Utils\Url,
    Api\ErrorOutput as ApiErrorOutput,
    Api\RequestWrapper,
    Api\ResponseWrapper,
};

use Slim\{
    ResponseEmitter,
    Factory\ServerRequestCreatorFactory,
    Psr7\Response,
};

use StdClass;
use Exception;

/**
 * Runs a portal client.
 */
class PortalClient implements ApplicationRunner
{
    protected $params;

    protected $clientManager;

    protected $config;

    public function __construct(ClientManager $clientManager, Config $config, ?StdClass $params = null)
    {
        $this->clientManager = $clientManager;
        $this->config = $config;

        $this->params = $params ?? (object) [];
    }

    public function run() : void
    {
        $id = $this->params->id ??
            Url::detectPortalId() ??
            $this->config->get('defaultPortalId');

        $basePath = $this->params->basePath ?? $this->clientManager->getBasePath();

        $requestWrapped = new RequestWrapper(
            ServerRequestCreatorFactory::create()->createServerRequestFromGlobals()
        );

        $responseWrapped = new ResponseWrapper(
            new Response()
        );

        if ($requestWrapped->getMethod() !== 'GET') {
            throw new Error("Only GET request is allowed.");
        }

        try {
            if (!$id) {
                throw new NotFound("Portal ID not detected.");
            }

            $application = new PortalApplication($id);
        }
        catch (Exception $e) {
            $this->processError($requestWrapped, $responseWrapped, $e);

            return;
        }

        $application->setClientBasePath($basePath);

        $application->run(PortalPortalClient::class);
    }

    protected function processError(RequestWrapper $request, ResponseWrapper $response, Exception $exception)
    {
        (new ApiErrorOutput($request))->process($response, $exception, true);

        (new ResponseEmitter())->emit($response->getResponse());
    }
}
