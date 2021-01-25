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

namespace tests\unit\Espo\Core\Select\Appliers;

use Espo\Core\{
    Exceptions\Error,
    Select\Appliers\TextFilterApplier,
    Utils\Config,
    Select\Text\MetadataProvider,
    Select\Text\FilterParams,
    Select\Text\FullTextSearchData,
    Select\Text\FullTextSearchDataComposer,
    Select\Text\FullTextSearchDataComposerFactory,
    Select\Text\FullTextSearchDataComposerParams,
};

use Espo\{
    ORM\QueryParams\SelectBuilder as QueryBuilder,
    ORM\Entity,
    Entities\User,
};

class TextFilterApplierTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp() : void
    {
        $this->user = $this->createMock(User::class);
        $this->metadataProvider = $this->createMock(MetadataProvider::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->filterParams = $this->createMock(FilterParams::class);
        $this->config = $this->createMock(Config::class);
        $this->fullTextSearchDataComposerFactory = $this->createMock(FullTextSearchDataComposerFactory::class);

        $this->entityType = 'Test';

        $this->applier = new TextFilterApplier(
            $this->entityType,
            $this->user,
            $this->config,
            $this->metadataProvider,
            $this->fullTextSearchDataComposerFactory
        );
    }

    public function testApply1()
    {
        $this->initTest(false);
    }

    public function testApply2()
    {
        $this->initTest(true);
    }

    public function testApply3()
    {
        $this->initTest(false, '1000');
    }

    protected function initTest(bool $noFullTextSearch = false, ?string $filter = null)
    {
        $filter = $filter ?? 'test';

        $filterParams = $this->createMock(FilterParams::class);

        $filterParams
            ->expects($this->any())
            ->method('preferFullTextSearch')
            ->willReturn(false);

        $filterParams
            ->expects($this->any())
            ->method('noFullTextSearch')
            ->willReturn($noFullTextSearch);

        $this->config
            ->expects($this->any())
            ->method('get')
            ->will(
                $this->returnValueMap(
                    [
                        ['textFilterUseContainsForVarchar', false],
                        ['textFilterContainsMinLength', 4],
                    ]
                )
            );

        $this->metadataProvider
            ->expects($this->any())
            ->method('getFullTextSearchOrderType')
            ->with($this->entityType)
            ->willReturn(null);

        $this->metadataProvider
            ->expects($this->any())
            ->method('getAttributeType')
            ->will(
                $this->returnValueMap(
                    [
                        [$this->entityType, 'fieldVarchar', Entity::VARCHAR],
                        [$this->entityType, 'fieldText', Entity::TEXT],
                        [$this->entityType, 'fieldFullText', Entity::TEXT],
                        [$this->entityType, 'fieldInt', Entity::INT],
                        [$this->entityType, 'fieldForeign', Entity::FOREIGN],
                        ['ForeignEntityType', 'field', Entity::VARCHAR],
                    ]
                )
            );

        $this->metadataProvider
            ->expects($this->any())
            ->method('getAttributeRelationParam')
            ->with($this->entityType, 'fieldForeign')
            ->willReturn('link1');

        $this->metadataProvider
            ->expects($this->any())
            ->method('getRelationType')
            ->with($this->entityType, 'link2')
            ->willReturn(Entity::HAS_MANY);

        $this->metadataProvider
            ->expects($this->any())
            ->method('getRelationEntityType')
            ->with($this->entityType, 'link2')
            ->willReturn('ForeignEntityType');

        $this->metadataProvider
            ->expects($this->any())
            ->method('getTextFilterFieldList')
            ->with($this->entityType)
            ->willReturn(
                ['fieldVarchar', 'fieldText', 'fieldFullText', 'fieldInt', 'fieldForeign', 'link2.field']
            );

        if (!$noFullTextSearch) {
            $this->initFullTextSearchData($filter, true, ['fieldFullText'], 'TEST:(test)');
        }

        $expectedWhere = [
            'OR' => [
                'fieldVarchar*' => $filter . '%',
                'fieldText*' => '%' . $filter . '%',
                'fieldForeign*' => $filter . '%',
                'link2.field*' => $filter . '%',
            ],
        ];

        if (!$noFullTextSearch) {
            $expectedWhere['OR']['AND'] = [
                'TEST:(test)',
            ];
        }

        if (is_numeric($filter)) {
            $expectedWhere['OR']['fieldInt'] = intval($filter);
        }

        if ($noFullTextSearch) {
            $expectedWhere['OR']['fieldFullText*'] = '%' . $filter . '%';
        }

        $this->queryBuilder
            ->expects($this->once())
            ->method('where')
            ->with($expectedWhere);

        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('leftJoin')
            ->withConsecutive(
                ['link1'],
                ['link2']
            );

        $this->queryBuilder
            ->expects($this->once())
            ->method('distinct');

        $this->applier->apply($this->queryBuilder, $filter, $this->filterParams);
    }

    protected function initFullTextSearchData(string $filter, bool $isAuxiliaryUse, array $fieldList, string $expression)
    {
        $fullTextSearchData = $this->createMock(FullTextSearchData::class);

        $fullTextSearchDataComposer = $this->createMock(FullTextSearchDataComposer::class);

        $fullTextSearchDataComposerParams = FullTextSearchDataComposerParams::fromArray([
            'isAuxiliaryUse' => $isAuxiliaryUse,
        ]);

        $this->fullTextSearchDataComposerFactory
            ->expects($this->once())
            ->method('create')
            ->with($this->entityType)
            ->willReturn($fullTextSearchDataComposer);

        $fullTextSearchData
            ->expects($this->any())
            ->method('getFieldList')
            ->willReturn($fieldList);

        $fullTextSearchData
            ->expects($this->any())
            ->method('getExpression')
            ->willReturn($expression);

        $fullTextSearchDataComposer
            ->expects($this->any())
            ->method('compose')
            ->with($filter, $fullTextSearchDataComposerParams)
            ->willReturn($fullTextSearchData);
    }
}
