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

namespace Espo\ORM\Query;

use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\SelectExpression;
use Espo\ORM\Query\Part\OrderExpression;

use RuntimeException;

/**
 * Select parameters.
 *
 * @todo Add validation and normalization (from ORM\DB\BaseQuery).
 */
class Select implements SelectingQuery
{
    use SelectingTrait;
    use BaseTrait;

    public const ORDER_ASC = OrderExpression::ASC;

    public const ORDER_DESC = OrderExpression::DESC;

    /**
     * Get an entity type.
     */
    public function getFrom(): ?string
    {
        return $this->params['from'] ?? null;
    }

    /**
     * Get SELECT items.
     *
     * @return SelectExpression[]
     */
    public function getSelect(): array
    {
        return array_map(
            function ($item) {
                if (is_array($item) && count($item)) {
                    return SelectExpression::fromString($item[0])
                        ->withAlias($item[1] ?? null);
                }

                if (is_string($item)) {
                    return SelectExpression::fromString($item);
                }

                throw new RuntimeException("Bad select item.");
            },
            $this->params['select'] ?? []
        );
    }

    /**
     * Get ORDER items.
     *
     * @return OrderExpression[]
     */
    public function getOrder(): array
    {
        return array_map(
            function ($item) {
                if (is_array($item) && count($item)) {
                    $itemValue = is_int($item[0]) ? (string) $item[0] : $item[0];

                    return OrderExpression::fromString($itemValue)
                        ->withDirection($item[1] ?? OrderExpression::ASC);
                }

                if (is_string($item)) {
                    return OrderExpression::fromString($item);
                }

                throw new RuntimeException("Bad order item.");
            },
            $this->params['orderBy'] ?? []
        );
    }

    /**
     * Whether DISTINCT is applied.
     */
    public function isDistinct(): bool
    {
        return $this->params['distinct'] ?? false;
    }

    /**
     * Get GROUP BY items.
     */
    public function getGroupBy(): array
    {
        return $this->params['orderBy'] ?? [];
    }

    /**
     * Get WHERE clause.
     */
    public function getWhere(): ?WhereClause
    {
        $whereClause = $this->params['whereClause'] ?? null;

        if ($whereClause === null || $whereClause === []) {
            return null;
        }

        return WhereClause::fromRaw($whereClause);
    }

    private function validateRawParams(array $params): void
    {
        $this->validateRawParamsSelecting($params);

        if (
            (
                !empty($params['joins']) ||
                !empty($params['leftJoins']) ||
                !empty($params['whereClause']) ||
                !empty($params['orderBy'])
            )
            &&
            empty($params['from']) && empty($params['fromQuery'])
        ) {
            throw new RuntimeException("Select params: Missing 'from'.");
        }
    }
}
