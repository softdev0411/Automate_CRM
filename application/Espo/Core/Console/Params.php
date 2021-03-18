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

namespace Espo\Core\Console;

/**
 * Command parameters.
 */
class Params
{
    /**
     * @var array<string,string>
     */
    private $options;

    /**
     * @var array<string>
     */
    private $flagList;

    /**
     * @var array<string>
     */
    private $argumentList;

    public function __construct(array $params)
    {
        $this->options = $params['options'] ?? [];
        $this->flagList = $params['flagList'] ?? [];
        $this->argumentList = $params['argumentList'] ?? [];
    }

    /**
     * @return array<string,string>
     */
    public function getOptions() : array
    {
        return $this->options;
    }

    /**
     * @return array<string>
     */
    public function getFlagList() : array
    {
        return $this->flagList;
    }

    /**
     * @return array<string>
     */
    public function getArgumentList() : array
    {
        return $this->argumentList;
    }

    /**
     * Has an option.
     */
    public function hasOption(string $name) : bool
    {
        return array_key_exists($name, $this->options);
    }

    /**
     * Get an option.
     */
    public function getOption(string $name) : ?string
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Has a flag.
     */
    public function hasFlag(string $name) : bool
    {
        return in_array($name, $this->flagList);
    }

    /**
     * Get an argument by index.
     */
    public function getArgument(int $index) : ?string
    {
        return $this->argumentList[$index] ?? null;
    }
}
