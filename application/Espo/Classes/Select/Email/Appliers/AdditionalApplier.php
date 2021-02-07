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

namespace Espo\Classes\Select\Email\Appliers;

use Espo\Core\{
    Select\Appliers\AdditionalApplier as AdditionalApplierBase,
};

use Espo\{
    ORM\QueryParams\SelectBuilder as QueryBuilder,
    Core\Select\SearchParams,
    Classes\Select\Email\Helpers\JoinHelper,
    Entities\User,
};

class AdditionalApplier extends AdditionalApplierBase
{
    protected $user;
    protected $joinHelper;

    public function __construct(User $user, JoinHelper $joinHelper)
    {
        $this->user = $user;
        $this->joinHelper = $joinHelper;
    }

    public function apply(QueryBuilder $queryBuilder, SearchParams $searchParams) : void
    {
        $folder = $this->retrieveFolder($searchParams);

        if ($folder === 'drafts') {
            $queryBuilder->useIndex('createdById');
        }
        else if ($folder === 'important') {
            // skip
        }
        else if ($this->checkApplyDateSentIndex($queryBuilder, $searchParams)) {
            $queryBuilder->useIndex('dateSent');
        }

        if ($folder !== 'drafts') {
            $this->joinEmailUser($queryBuilder);
        }
    }

    protected function joinEmailUser(QueryBuilder $queryBuilder) : void
    {
        $this->joinHelper->joinEmailUser($queryBuilder, $this->user->id);

        if ($queryBuilder->build()->getSelect() === []) {
            $queryBuilder->select('*');
        }

        $itemList = [
            'isRead',
            'isImportant',
            'inTrash',
            'folderId',
        ];

        foreach ($itemList as $item) {
            $queryBuilder->select('emailUser.' . $item, $item);
        }
    }

    protected function retrieveFolder(SearchParams $searchParams) : ?string
    {
        foreach ($searchParams->getWhere() ?? [] as $item) {
            $itemType = $item['type'] ?? null;
            $itemValue = $item['value'] ?? null;

            if ($itemType === 'inFolder') {
                return $itemValue;
            }
        }

        return null;
    }

    protected function checkApplyDateSentIndex(QueryBuilder $queryBuilder, SearchParams $searchParams) : bool
    {
        if ($searchParams->getTextFilter()) {
            return false;
        }

        if ($searchParams->getOrderBy() && $searchParams->getOrderBy() !== 'dateSent') {
            return false;
        }

        foreach ($searchParams->getWhere() ?? [] as $item) {
            $itemAttribute = $item['attribute'] ?? null;

            if (
                $itemAttribute &&
                $itemAttribute !== 'folderId' &&
                !in_array($itemAttribute, ['teams', 'users', 'status'])
            ) {
                return false;
            }
        }

        if ($queryBuilder->hasLeftJoinAlias('teamsAccess')) {
            return false;
        }

        return true;
    }
}
