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

namespace EspoDev\PHPStan\Extensions;

use PHPStan\Type\DynamicMethodReturnTypeExtension;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Type;
use PHPStan\Type\ObjectType;
use PHPStan\Type\UnionType;
use PHPStan\Type\NullType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Generic\TemplateGenericObjectType;

use PhpParser\Node\Scalar\String_;

use RuntimeException;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

use Espo\Core\Utils\Util;

use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\Repository;

class EntityManagerReturnType implements DynamicMethodReturnTypeExtension
{
    private $supportedMethodNameList = [
        'getEntity',
        'getNewEntity',
        'createEntity',
        'getRDBRepository',
        'getRepository',
    ];

    private $entityNamespaceList = [
        '\\Espo\\Modules\\Crm\\Entities',
        '\\Espo\\Entities',
    ];

    public function getClass(): string
    {
        return EntityManager::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), $this->supportedMethodNameList);
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): Type {

        $methodName = $methodReflection->getName();

        if ($methodName === 'getEntity') {
            return $this->getGetEntity($methodReflection, $methodCall, $scope);
        }

        if ($methodName === 'getNewEntity' || $methodName === 'createEntity') {
            return $this->getGetEntityNotNull($methodReflection, $methodCall, $scope);
        }

        if ($methodName === 'getRDBRepository') {
            return $this->getGetRDBRepository($methodReflection, $methodCall, $scope);
        }

        if ($methodName === 'getRepository') {
            return $this->getGetRepository($methodReflection, $methodCall, $scope);
        }

        throw new RuntimeException("Not supported method.");
    }

    private function getGetEntity(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): Type {

        $value = $methodCall->args[0]->value;

        if (!$value instanceof String_) {
            return new UnionType([
                new ObjectType(Entity::class),
                new NullType(),
            ]);
        }

        $entityType = $value->value;

        $className = $this->findEntityClassName($entityType) ?? Entity::class;

        return new UnionType([
            new ObjectType($className),
            new NullType(),
        ]);
    }

    private function getGetEntityNotNull(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): Type {

        $value = $methodCall->args[0]->value;

        if (!$value instanceof String_) {
            return new ObjectType(Entity::class);
        }

        $entityType = $value->value;

        $className = $this->findEntityClassName($entityType) ?? Entity::class;

        return new ObjectType($className);
    }

    private function findEntityClassName(string $entityType): ?string
    {
        foreach ($this->entityNamespaceList as $namespace) {
            $className = $namespace . '\\' . Util::normilizeClassName($entityType);

            if (class_exists($className)) {
                return $className;
            }
        }

        return null;
    }

    private function getGetRDBRepository(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): Type {

        $value = $methodCall->args[0]->value;

        if (!$value instanceof String_) {
            return new ObjectType(RDBRepository::class);
        }

        $entityType = $value->value;

        $entityClassName = $this->findEntityClassName($entityType);

        if ($entityClassName) {
            return new GenericObjectType(RDBRepository::class, [new ObjectType($entityClassName)]);
        }

        return new ObjectType(RDBRepository::class);
    }

    private function getGetRepository(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): Type {

        $value = $methodCall->args[0]->value;

        if (!$value instanceof ObjectType) {
            return new ObjectType(Repository::class);
        }

        $entityType = $value->value;

        $entityClassName = $this->findEntityClassName($entityType);

        if ($entityClassName) {
            return new TemplateGenericObjectType(Repository::class, [new ObjectType($entityClassName)]);
        }

        return new ObjectType(Repository::class);
    }
}
