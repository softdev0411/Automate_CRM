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

use Espo\Core\{
    InjectableFactory,
    Utils\ClassFinder,
    Utils\Util,
    Api\Request,
    Api\Response,
    Exceptions\NotFound,
    Exceptions\BadRequest,
};

use ReflectionClass;
use StdClass;

/**
 * Creates controller instances and processes actions.
 */
class ControllerManager
{
    protected $injectableFactory;
    protected $classFinder;

    public function __construct(InjectableFactory $injectableFactory, ClassFinder $classFinder)
    {
        $this->injectableFactory = $injectableFactory;
        $this->classFinder = $classFinder;
    }

    public function process(
        string $controllerName,
        string $requestMethod,
        string $actionName,
        array $params,
        $data,
        Request $request,
        Response $response
    ) {
        $controller = $this->createController($controllerName);

        if ($actionName == 'index') {
            $actionName = $controller::$defaultAction ?? 'index';
        }

        $actionNameUcfirst = ucfirst($actionName);

        $beforeMethodName = 'before' . $actionNameUcfirst;
        $actionMethodName = 'action' . $actionNameUcfirst;
        $afterMethodName = 'after' . $actionNameUcfirst;

        $fullActionMethodName = strtolower($requestMethod) . ucfirst($actionMethodName);

        if (method_exists($controller, $fullActionMethodName)) {
            $primaryActionMethodName = $fullActionMethodName;
        } else {
            $primaryActionMethodName = $actionMethodName;
        }

        if (!method_exists($controller, $primaryActionMethodName)) {
            throw new NotFound(
                "Action {$requestMethod} '{$actionName}' does not exist in controller '{$controllerName}'."
            );
        }

        $this->processContentTypeCheck($controller, $primaryActionMethodName, 1);

        if (method_exists($controller, $beforeMethodName)) {
            $controller->$beforeMethodName($params, $data, $request, $response);
        }

        $result = $controller->$primaryActionMethodName($params, $data, $request, $response);

        if (method_exists($controller, $afterMethodName)) {
            $controller->$afterMethodName($params, $data, $request, $response);
        }

        return $result;
    }

    protected function processContentTypeCheck(object $controller, string $primaryActionMethodName, int $parameterIndex)
    {
        $class = new ReflectionClass($controller);

        $method = $class->getMethod($primaryActionMethodName);

        $args = $method->getParameters();

        if (count($args) <= $parameterIndex) {
            return;
        }

        $param = $args[$parameterIndex];

        if (! $param->hasType()) {
            return;
        }

        $dataClass = $param->getClass();

        if (!$dataClass) {
            return;
        }

        if (strtolower($dataClass->getName()) !== strtolower(StdClass::class)) {
            return;
        }

        if (! $data instanceof StdClass) {
            throw new BadRequest(
                "{$controllerName} {$requestMethod} {$actionName}: Content-Type should be 'application/json'."
            );
        }
    }

    protected function getControllerClassName(string $name) : string
    {
        $className = $this->classFinder->find('Controllers', $name);

        if (!$className) {
            throw new NotFound("Controller '{$name}' does not exist.");
        }

        if (!class_exists($className)) {
            throw new NotFound("Class not found for controller '{$name}'.");
        }

        return $className;
    }

    protected function createController(string $name) : object
    {
        return $this->injectableFactory->createWith($this->getControllerClassName($name), [
            'name' => $name,
        ]);
    }
}
