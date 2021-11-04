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

namespace Espo\Tools\Import;

use stdClass;
use TypeError;

class Params
{
    public const ACTION_CREATE = 'create';

    public const ACTION_CREATE_AND_UPDATE = 'createAndUpdate';

    public const ACTION_UPDATE = 'update';

    private $action = null;

    private $delimiter = null;

    private $textQualifier = null;

    private $personNameFormat = null;

    private $idleMode = false;

    private $manualMode = false;

    private $silentMode = false;

    private $headerRow = false;

    private $skipDuplicateChecking = false;

    private $startFromLastIndex = false;

    private $updateBy = [];

    private $defaultValues;

    private $dateFormat = null;

    private $timeFormat = null;

    private $currency = null;

    private $timezone = null;

    private $decimalMark = null;

    private function __construct()
    {
        $this->defaultValues = (object) [];
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function getDelimiter(): ?string
    {
        return $this->delimiter;
    }

    public function getTextQualifier(): ?string
    {
        return $this->textQualifier;
    }

    public function getPersonNameFormat(): ?string
    {
        return $this->personNameFormat;
    }

    public function isIdleMode(): bool
    {
        return $this->idleMode;
    }

    public function isManualMode(): bool
    {
        return $this->manualMode;
    }

    public function isSilentMode(): bool
    {
        return $this->silentMode;
    }

    public function headerRow(): bool
    {
        return $this->headerRow;
    }

    public function skipDuplicateChecking(): bool
    {
        return $this->skipDuplicateChecking;
    }

    public function startFromLastIndex(): bool
    {
        return $this->startFromLastIndex;
    }

    public function getUpdateBy(): array
    {
        return $this->updateBy;
    }

    public function getDefaultValues(): stdClass
    {
        return $this->defaultValues;
    }

    public function getDateFormat(): ?string
    {
        return $this->dateFormat;
    }

    public function getTimeFormat(): ?string
    {
        return $this->timeFormat;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function getDecimalMark(): ?string
    {
        return $this->decimalMark;
    }

    public static function create(): self
    {
        return new self();
    }

    public function withAction(?string $action): self
    {
        $obj = clone $this;
        $obj->action = $action;

        return $obj;
    }

    public function withDelimiter(?string $delimiter): self
    {
        $obj = clone $this;
        $obj->delimiter = $delimiter;

        return $obj;
    }

    public function withTextQualifier(?string $textQualifier): self
    {
        $obj = clone $this;
        $obj->textQualifier = $textQualifier;

        return $obj;
    }

    public function withPersonNameFormat(?string $personNameFormat): self
    {
        $obj = clone $this;
        $obj->personNameFormat = $personNameFormat;

        return $obj;
    }

    public function withIdleMode(bool $idleMode = true): self
    {
        $obj = clone $this;
        $obj->idleMode = $idleMode;

        return $obj;
    }

    public function withManualMode(bool $manualMode = true): self
    {
        $obj = clone $this;
        $obj->manualMode = $manualMode;

        return $obj;
    }

    public function withSilentMode(bool $silentMode = true): self
    {
        $obj = clone $this;
        $obj->silentMode = $silentMode;

        return $obj;
    }

    public function withHeaderRow(bool $headerRow = true): self
    {
        $obj = clone $this;
        $obj->headerRow = $headerRow;

        return $obj;
    }

    public function withSkipDuplicateChecking(bool $skipDuplicateChecking = true): self
    {
        $obj = clone $this;
        $obj->skipDuplicateChecking = $skipDuplicateChecking;

        return $obj;
    }

    public function withStartFromLastIndex(bool $startFromLastIndex = true): self
    {
        $obj = clone $this;
        $obj->startFromLastIndex = $startFromLastIndex;

        return $obj;
    }

    public function withUpdateBy(array $updateBy): self
    {
        $obj = clone $this;
        $obj->updateBy = $updateBy;

        return $obj;
    }

    /**
     * @param stdClass|array|null $defaultValues
     */
    public function withDefaultValues($defaultValues): self
    {
        if (is_array($defaultValues)) {
            $defaultValues = (object) $defaultValues;
        }

        if (is_null($defaultValues)) {
            $defaultValues = (object) [];
        }

        /** @var object|scalar $defaultValues */

        if (!is_object($defaultValues)) {
            throw new TypeError();
        }

        $obj = clone $this;
        $obj->defaultValues = $defaultValues;

        return $obj;
    }

    public function withDateFormat(?string $dateFormat): self
    {
        $obj = clone $this;
        $obj->dateFormat = $dateFormat;

        return $obj;
    }

    public function withTimeFormat(?string $timeFormat): self
    {
        $obj = clone $this;
        $obj->timeFormat = $timeFormat;

        return $obj;
    }

    public function withCurrency(?string $currency): self
    {
        $obj = clone $this;
        $obj->currency = $currency;

        return $obj;
    }

    public function withTimezone(?string $timezone): self
    {
        $obj = clone $this;
        $obj->timezone = $timezone;

        return $obj;
    }

    public function withDecimalMark(?string $decimalMark): self
    {
        $obj = clone $this;
        $obj->decimalMark = $decimalMark;

        return $obj;
    }

    /**
     * @param stdClass|array|null $params
     */
    public static function fromRaw($params): self
    {
        if ($params === null) {
            $params = (object) [];
        }

        $raw = (object) $params;

        $obj = self::create()
            ->withAction($raw->action ?? null)
            ->withCurrency($raw->currency ?? null)
            ->withDateFormat($raw->dateFormat ?? null)
            ->withDecimalMark($raw->decimalMark ?? null)
            ->withDefaultValues($raw->defaultValues ?? null)
            ->withDelimiter($raw->delimiter ?? null)
            ->withHeaderRow($raw->headerRow ?? false)
            ->withIdleMode($raw->idleMode ?? false)
            ->withManualMode($raw->manualMode ?? false)
            ->withPersonNameFormat($raw->personNameFormat ?? null)
            ->withSilentMode($raw->silentMode ?? false)
            ->withSkipDuplicateChecking($raw->skipDuplicateChecking ?? false)
            ->withStartFromLastIndex($raw->startFromLastIndex ?? false)
            ->withTextQualifier($raw->textQualifier ?? null)
            ->withTimeFormat($raw->timeFormat ?? false)
            ->withTimezone($raw->timezone ?? null)
            ->withUpdateBy($raw->updateBy ?? []);

        return $obj;
    }

    public function getRaw(): array
    {
        return [
            'action' => $this->action,
            'currency' => $this->currency,
            'dateFormat' => $this->dateFormat,
            'decimalMark' => $this->decimalMark,
            'defaultValues' => $this->defaultValues,
            'delimiter' => $this->delimiter,
            'headerRow' => $this->headerRow,
            'idleMode' => $this->idleMode,
            'manualMode' => $this->manualMode,
            'personNameFormat' => $this->personNameFormat,
            'silentMode' => $this->silentMode,
            'skipDuplicateChecking' => $this->skipDuplicateChecking,
            'startFromLastIndex' => $this->startFromLastIndex,
            'textQualifier' => $this->textQualifier,
            'timeFormat' => $this->timeFormat,
            'timezone' => $this->timezone,
            'updateBy' => $this->updateBy,
        ];
    }
}
