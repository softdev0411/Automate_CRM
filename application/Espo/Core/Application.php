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

use Espo\Core\Exceptions\{
    Error,
};

use Espo\Core\{
    ContainerConfiguration,
    EntryPointManager,
    CronManager,
    Utils\Auth,
    Utils\Api\Auth as ApiAuth,
    Utils\Route,
    Utils\Autoload,
    Portal\Application as PortalApplication,
    Loaders\Config as ConfigLoader,
    Loaders\Log as LogLoader,
    Loaders\FileManager as FileManagerLoader,
    Loaders\DataManager as DataManagerLoader,
    Loaders\Metadata as MetadataLoader,
};

/**
 * The main entry point of the application.
 */
class Application
{
    protected $container;

    protected $loaderClassNames = [
        'config' => ConfigLoader::class,
        'log' => LogLoader::class,
        'fileManager' => FileManagerLoader::class,
        'dataManager' => DataManagerLoader::class,
        'metadata' => MetadataLoader::class,
    ];

    public function __construct()
    {
        date_default_timezone_set('UTC');

        $this->initContainer();
        $this->initAutoloads();
        $this->initPreloads();
    }

    protected function initContainer()
    {
        $this->container = new Container(ContainerConfiguration::class, $this->loaderClassNames);
    }

    /**
     * Run REST API.
     */
    public function run(string $name = 'default')
    {
        $this->routeHooks();
        $this->initRoutes();
        $this->getSlim()->run();
    }

    /**
     * Display the main HTML page.
     */
    public function runClient()
    {
        $this->container->get('clientManager')->display();
        exit;
    }

    /**
     * Run entryPoint.
     */
    public function runEntryPoint(string $entryPoint, array $data = [], bool $final = false)
    {
        if (empty($entryPoint)) {
            throw new Error();
        }

        $slim = $this->getSlim();
        $container = $this->container;

        $slim->any('.*', function() {});

        $injectableFactory = $container->get('injectableFactory');
        $classFinder = $container->get('classFinder');

        $entryPointManager = new EntryPointManager($injectableFactory, $classFinder);

        try {
            $authRequired = $entryPointManager->checkAuthRequired($entryPoint);
            $authNotStrict = $entryPointManager->checkNotStrictAuth($entryPoint);
            if ($authRequired && !$authNotStrict) {
                if (!$final && $portalId = $this->detectPortalId()) {
                    $app = new PortalApplication($portalId);
                    $app->setBasePath($this->getBasePath());
                    $app->runEntryPoint($entryPoint, $data, true);
                    exit;
                }
            }
            $auth = new Auth($this->container, $authNotStrict);
            $apiAuth = new ApiAuth($auth, $authRequired, true);
            $slim->add($apiAuth);

            $request = $slim->request();

            $slim->hook('slim.before.dispatch', function () use ($entryPointManager, $entryPoint, $request, $data) {
                $entryPointManager->run($entryPoint, $request, $data);
            });

            $slim->run();
        } catch (\Exception $e) {
            try {
                $container->get('output')->processError($e->getMessage(), $e->getCode(), true, $e);
            } catch (\Slim\Exception\Stop $e) {}
        }
    }

    /**
     * Run cron.
     */
    public function runCron()
    {
        if ($this->getConfig()->get('cronDisabled')) {
            $GLOBALS['log']->warning("Cron is not run because it's disabled with 'cronDisabled' param.");
            return;
        }

        $auth = $this->createAuth();
        $auth->useNoAuth();

        $cronManager = $this->container->get('cronManager');
        $cronManager->run();
    }

    /**
     * Run daemon.
     */
    public function runDaemon()
    {
        $maxProcessNumber = $this->getConfig()->get('daemonMaxProcessNumber');
        $interval = $this->getConfig()->get('daemonInterval');
        $timeout = $this->getConfig()->get('daemonProcessTimeout');

        $phpExecutablePath = $this->getConfig()->get('phpExecutablePath');
        if (!$phpExecutablePath) {
            $phpExecutablePath = (new \Symfony\Component\Process\PhpExecutableFinder)->find();
        }

        if (!$maxProcessNumber || !$interval) {
            $GLOBALS['log']->error("Daemon config params are not set.");
            return;
        }

        $processList = [];
        while (true) {
            $toSkip = false;
            $runningCount = 0;
            foreach ($processList as $i => $process) {
                if ($process->isRunning()) {
                    $runningCount++;
                } else {
                    unset($processList[$i]);
                }
            }
            $processList = array_values($processList);
            if ($runningCount >= $maxProcessNumber) {
                $toSkip = true;
            }
            if (!$toSkip) {
                $process = new \Symfony\Component\Process\Process([$phpExecutablePath, 'cron.php']);
                $process->setTimeout($timeout);
                $process->run();
                $processList[] = $process;
            }
            sleep($interval);
        }
    }

    /**
     * Run a job by ID. A job record should exist in database.
     */
    public function runJob(string $id)
    {
        $auth = $this->createAuth();
        $auth->useNoAuth();

        $cronManager = $this->container->get('cronManager');
        $cronManager->runJobById($id);
    }

    /**
     * Rebuild application.
     */
    public function runRebuild()
    {
        $dataManager = $this->container->get('dataManager');
        $dataManager->rebuild();
    }

    /**
     * Clear application cache.
     */
    public function runClearCache()
    {
        $dataManager = $this->container->get('dataManager');
        $dataManager->clearCache();
    }

    /**
     * Run command in Console Command framework.
     */
    public function runCommand(string $command)
    {
        $auth = $this->createAuth();
        $auth->useNoAuth();

        $consoleCommandManager = $this->container->get('consoleCommandManager');
        return $consoleCommandManager->run($command);
    }

    /**
     * The whether the application is installed.
     */
    public function isInstalled() : bool
    {
        $config = $this->getConfig();

        if (file_exists($config->getConfigPath()) && $config->get('isInstalled')) {
            return true;
        }

        return false;
    }

    /**
     * Get the service container.
     */
    public function getContainer() : Container
    {
        return $this->container;
    }

    protected function getSlim()
    {
        return $this->container->get('slim');
    }

    protected function getMetadata()
    {
        return $this->container->get('metadata');
    }

    protected function getConfig()
    {
        return $this->container->get('config');
    }

    protected function createAuth()
    {
        return new Auth($this->container);
    }

    protected function createApiAuth(Auth $auth) : ApiAuth
    {
        return new ApiAuth($auth);
    }

    protected function routeHooks()
    {
        $container = $this->container;
        $slim = $this->getSlim();

        try {
            $auth = $this->createAuth();
        } catch (\Exception $e) {
            $container->get('output')->processError($e->getMessage(), $e->getCode(), false, $e);
        }

        $apiAuth = $this->createApiAuth($auth);

        $this->getSlim()->add($apiAuth);
        $this->getSlim()->hook('slim.before.dispatch', function () use ($slim, $container) {

            $route = $slim->router()->getCurrentRoute();
            $conditions = $route->getConditions();

            $response = $slim->response();
            $response->headers->set('Content-Type', 'application/json');

            $routeOptions = call_user_func($route->getCallable());
            $routeKeys = is_array($routeOptions) ? array_keys($routeOptions) : [];

            if (!in_array('controller', $routeKeys, true)) {
                return $container->get('output')->render($routeOptions);
            }

            $params = $route->getParams();
            $data = $slim->request()->getBody();

            foreach ($routeOptions as $key => $value) {
                if (strstr($value, ':')) {
                    $paramName = str_replace(':', '', $value);
                    $value = $params[$paramName];
                }
                $controllerParams[$key] = $value;
            }

            $params = array_merge($params, $controllerParams);

            $controllerName = ucfirst($controllerParams['controller']);

            if (!empty($controllerParams['action'])) {
                $actionName = $controllerParams['action'];
            } else {
                $httpMethod = strtolower($slim->request()->getMethod());
                $crudList = $container->get('config')->get('crud');
                $actionName = $crudList[$httpMethod] ?? null;
                if (!$actionName) {
                    throw new Error("No action for {$httpMethod} request.");
                }
            }

            try {
                $controllerManager = $this->container->get('controllerManager');
                $result = $controllerManager->process(
                    $controllerName, $actionName, $params, $data, $slim->request(), $slim->response()
                );
                $container->get('output')->render($result);
            } catch (\Exception $e) {
                $container->get('output')->processError($e->getMessage(), $e->getCode(), false, $e);
            }
        });

        $this->getSlim()->hook('slim.after.router', function () use (&$slim) {
            $response = $slim->response();

            if (!$response->headers->has('Content-Type')) {
                $response->headers->set('Content-Type', 'application/json');
            }

            $response->headers->set('Expires', '0');
            $response->headers->set('Last-Modified', gmdate("D, d M Y H:i:s") . " GMT");
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            $response->headers->set('Pragma', 'no-cache');
        });
    }

    protected function getRouteList()
    {
        $routes = new Route($this->getConfig(), $this->getMetadata(), $this->container->get('fileManager'));
        return $routes->getAll();
    }

    protected function initRoutes()
    {
        $crudList = array_keys($this->getConfig()->get('crud'));

        foreach ($this->getRouteList() as $route) {
            $method = strtolower($route['method']);
            if (!in_array($method, $crudList) && $method !== 'options') {
                $GLOBALS['log']->error(
                    'Route: Method ['.$method.'] does not exist. Please check your route ['.$route['route'].']'
                );
                continue;
            }

            $currentRoute = $this->getSlim()->$method($route['route'], function() use ($route) {
                return $route['params'];
            });

            if (isset($route['conditions'])) {
                $currentRoute->conditions($route['conditions']);
            }
        }
    }

    protected function initAutoloads()
    {
        $autoload = new Autoload($this->getConfig(), $this->getMetadata(), $this->container->get('fileManager'));
        $autoload->register();
    }

    protected function initPreloads()
    {
        foreach ($this->getMetadata()->get(['app', 'containerServices']) ?? [] as $name => $defs) {
            if ($defs['preload'] ?? false) {
                $this->container->get($name);
            }
        }
    }

    public function setBasePath(string $basePath)
    {
        $this->container->get('clientManager')->setBasePath($basePath);
    }

    public function getBasePath() : string
    {
        return $this->container->get('clientManager')->getBasePath();
    }

    public function detectPortalId() : ?string
    {
        if (!empty($_GET['portalId'])) {
            return $_GET['portalId'];
        }
        if (!empty($_COOKIE['auth-token'])) {
            $token =
                $this->container->get('entityManager')
                    ->getRepository('AuthToken')->where(['token' => $_COOKIE['auth-token']])->findOne();

            if ($token && $token->get('portalId')) {
                return $token->get('portalId');
            }
        }
        return null;
    }

    /**
     * Setup the system user. The system user is used when no user is logged in.
     */
    public function setupSystemUser()
    {
        $user = $this->container->get('entityManager')->getEntity('User', 'system');
        $user->set('type', 'system');
        $this->container->set('user', $user);
    }
}
