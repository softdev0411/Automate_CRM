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

namespace Espo\Core\Select;

use Espo\Core\Select\{
    Factory\ApplierFactory,
    Appliers\WhereApplier,
    Appliers\SelectApplier,
    Appliers\OrderApplier,
    Appliers\LimitApplier,
    Appliers\AccessControlFilterApplier,
    Appliers\PrimaryFilterApplier,
    Appliers\BoolFilterListApplier,
    Appliers\TextFilterApplier,
    Appliers\AdditionalApplier,
    Where\Params as WhereParams,
    Where\Item as WhereItem,
    Order\Params as OrderParams,
    Text\FilterParams as TextFilterParams,
};

use Espo\{
    ORM\QueryParams\Select as Query,
    ORM\QueryParams\SelectBuilder as QueryBuilder,
    Entities\User,
};

use LogicException;

/**
 * Builds select queries for ORM.
 * Applies search parameters (passed from frontend), ACL restrictions, filters, etc.
 */
class SelectBuilder
{
    protected $entityType = null;

    protected $queryBuilder;

    protected $user = null;

    protected $sourceQuery = null;

    protected $searchParams = null;

    protected $applyAccessControlFilter = false;

    protected $applyDefaultOrder = false;

    protected $textFilter = null;

    protected $primaryFilter = null;

    protected $boolFilterList = [];

    protected $whereItemList = [];

    protected $applyWherePermissionCheck = false;

    protected $applyComplexExpressionsForbidden = false;

    protected $applierFactory;

    public function __construct(User $user, ApplierFactory $applierFactory)
    {
        $this->applierFactory = $applierFactory;

        $this->user = $user;
    }

    /**
     * Specify an entity type to select from.
     */
    public function from(string $entityType) : self
    {
        if ($this->sourceQuery) {
            throw new LogicException("Can't call 'from' after 'clone'.");
        }

        $this->entityType = $entityType;

        return $this;
    }

    /**
     * Start building from an existing select query.
     */
    public function clone(Query $query) : self
    {
        if ($this->entityType && $this->entityType !== $query->getFrom()) {
            throw new LogicException("Not matching entity type.");
        }

        $this->entityType = $query->getFrom();

        $this->sourceQuery = $query;

        return $this;
    }

    /**
     * Build a result query.
     */
    public function build() : Query
    {
        return $this->buildQueryBuilder()->build();
    }

    /**
     * Build an ORM query builder. Used to continue building but by means of ORM.
     */
    public function buildQueryBuilder() : QueryBuilder
    {
        $this->queryBuilder = new OrmSelectBuilder();

        if (!$this->entityType) {
            throw new LogicException("No entity type.");
        }

        if ($this->sourceQuery) {
            $this->queryBuilder->clone($this->sourceQuery);
        }
        else {
            $this->queryBuilder->from($this->entityType);
        }

        $this->applyFromSearchParams();

        if (count($this->whereItemList)) {
            $this->applyWhereItemList();
        }

        if ($this->applyDefaultOrder) {
            $this->applyDefaultOrder();
        }

        if ($this->primaryFilter) {
            $this->applyPrimaryFilter();
        }

        if (count($this->boolFilterList)) {
            $this->applyBoolFilterList();
        }

        if ($this->textFilter) {
            $this->applyTextFilter();
        }

        if ($this->applyAccessControlFilter) {
            $this->applyAccessControlFilter();
        }

        $this->applyAdditional();

        return $this->queryBuilder;
    }

    /**
     * Switch a user for whom a select query will be built.
     */
    public function forUser(User $user) : self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Apply search parameters.
     *
     * Note: If there's no order set in the search parameters then a default order will be applied.
     */
    public function withSearchParams(SearchParams $searchParams) : self
    {
        $this->searchParams = $searchParams;

        $this->withBoolFilterList(
            $searchParams->getBoolFilterList()
        );

        $primaryFilter = $searchParams->getPrimaryFilter();

        if ($primaryFilter) {
            $this->withPrimaryFilter($primaryFilter);
        }

        $textFilter = $searchParams->getTextFilter();

        if ($textFilter) {
            $this->withTextFilter($textFilter);
        }

        return $this;
    }

    /**
     * Apply maximum restrictions for a user.
     */
    public function withStrictAccessControl() : self
    {
        $this->withAccessControlFilter();
        $this->withWherePermissionCheck();
        $this->withComplexExpressionsForbidden();

        return $this;
    }

    /**
     * Apply an access control filter.
     */
    public function withAccessControlFilter() : self
    {
        $this->applyAccessControlFilter = true;

        return $this;
    }

    /**
     * Apply a default order.
     */
    public function withDefaultOrder() : self
    {
        $this->applyDefaultOrder = true;

        return $this;
    }

    /**
     * Check permissions to where items.
     */
    public function withWherePermissionCheck() : self
    {
        $this->applyWherePermissionCheck = true;

        return $this;
    }

    /**
     * Forbid complex expression usage.
     */
    public function withComplexExpressionsForbidden() : self
    {
        $this->applyComplexExpressionsForbidden = true;

        return $this;
    }

    /**
     * Apply a text filter.
     */
    public function withTextFilter(string $textFilter) : self
    {
        $this->textFilter = $textFilter;

        return $this;
    }

    /**
     * Apply a primary filter.
     */
    public function withPrimaryFilter(string $primaryFilter) : self
    {
        $this->primaryFilter = $primaryFilter;

        return $this;
    }

    /**
     * Apply a bool filter.
     */
    public function withBoolFilter(string $boolFilter) : self
    {
        $this->boolFilterList[] = $boolFilter;

        return $this;
    }

    /**
     * Apply a list of bool filters.
     */
    public function withBoolFilterList(array $boolFilterList) : self
    {
        $this->boolFilterList = array_merge($this->boolFilterList, $boolFilterList);

        return $this;
    }

    /**
     * Apply a Where Item.
     */
    public function withWhere(WhereItem $whereItem) : self
    {
        $this->whereItemList[] = $whereItem;

        return $this;
    }

    protected function applyPrimaryFilter() : void
    {
        $this->createPrimaryFilterApplier()
            ->apply(
                $this->queryBuilder,
                $this->primaryFilter
            );
    }

    protected function applyBoolFilterList() : void
    {
        $this->createBoolFilterListApplier()
            ->apply(
                $this->queryBuilder,
                $this->boolFilterList
            );
    }

    protected function applyTextFilter() : void
    {
        $noFullTextSearch = false;

        if ($this->searchParams && $this->searchParams->noFullTextSearch()) {
            $noFullTextSearch = true;
        }

        $this->createTextFilterApplier()
            ->apply(
                $this->queryBuilder,
                $this->textFilter,
                TextFilterParams::fromArray([
                    'noFullTextSearch' => $noFullTextSearch,
                ])
            );
    }

    protected function applyAccessControlFilter() : void
    {
        $this->createAccessControlFilterApplier()
            ->apply(
                $this->queryBuilder
            );
    }

    protected function applyDefaultOrder() : void
    {
        $order = null;

        if ($this->searchParams) {
            $order = $this->searchParams->getOrder();
        }

        $params = OrderParams::fromArray([
            'forceDefault' => true,
            'order' => $order,
        ]);

        $this->createOrderApplier()
            ->apply(
                $this->queryBuilder,
                $params
            );
    }

    protected function applyWhereItemList() : void
    {
        foreach ($this->whereItemList as $whereItem) {
            $this->applyWhereItem($whereItem);
        }
    }

    protected function applyWhereItem(WhereItem $whereItem) : void
    {
        $params = WhereParams::fromArray([
            'applyPermissionCheck' => $this->applyWherePermissionCheck,
            'forbidComplexExpressions' => $this->applyComplexExpressionsForbidden,
        ]);

        $this->createWhereApplier()
            ->apply(
                $this->queryBuilder,
                $whereItem,
                $params
            );
    }

    protected function applyFromSearchParams() : void
    {
        if (!$this->searchParams) {
            return;
        }

        if (
            !$this->applyDefaultOrder &&
            ($this->searchParams->getOrderBy() || $this->searchParams->getOrder())
        ) {
            $params = OrderParams::fromArray([
                'forbidComplexExpressions' => $this->applyComplexExpressionsForbidden,
                'orderBy' => $this->searchParams->getOrderBy(),
                'order' => $this->searchParams->getOrder(),
            ]);

            $this->createOrderApplier()
                ->apply(
                    $this->queryBuilder,
                    $params
                );
        }

        if (!$this->searchParams->getOrderBy() && !$this->searchParams->getOrder()) {
            $this->withDefaultOrder();
        }

        if ($this->searchParams->getMaxSize() || $this->searchParams->getOffset()) {
            $this->createLimitApplier()
                ->apply(
                    $this->queryBuilder,
                    $this->searchParams->getOffset(),
                    $this->searchParams->getMaxSize()
                );
        }

        if ($this->searchParams->getSelect()) {
            $this->createSelectApplier()
                ->apply(
                    $this->queryBuilder,
                    $this->searchParams
                );
        }

        if ($this->searchParams->getWhere()) {
            $whereItem = WhereItem::fromRaw([
                'type' => 'and',
                'value' => $this->searchParams->getWhere(),
            ]);

            $this->whereItemList[] = $whereItem;
        }
    }

    protected function applyAdditional() : void
    {
        $searchParams = SearchParams::fromRaw([
            'boolFilterList' => $this->boolFilterList,
            'primaryFilter' => $this->primaryFilter,
            'textFilter' => $this->textFilter,
        ]);

        if ($this->searchParams) {
            $searchParams = SearchParams::merge($searchParams, $this->searchParams);
        }

        $this->createAdditionalApplier()->apply(
            $this->queryBuilder,
            $searchParams
        );
    }

    protected function createWhereApplier() : WhereApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::WHERE);
    }

    protected function createSelectApplier() : SelectApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::SELECT);
    }

    protected function createOrderApplier() : OrderApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::ORDER);
    }

    protected function createLimitApplier() : LimitApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::LIMIT);
    }

    protected function createAccessControlFilterApplier() : AccessControlFilterApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::ACCESS_CONTROL_FILTER);
    }

    protected function createTextFilterApplier() : TextFilterApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::TEXT_FILTER);
    }

    protected function createPrimaryFilterApplier() : PrimaryFilterApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::PRIMARY_FILTER);
    }

    protected function createBoolFilterListApplier() : BoolFilterListApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::BOOL_FILTER_LIST);
    }

    protected function createAdditionalApplier() : AdditionalApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::ADDITIONAL);
    }
}
