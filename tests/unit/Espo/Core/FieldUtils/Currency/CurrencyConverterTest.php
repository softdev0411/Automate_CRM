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

namespace tests\unit\Espo\Core\FieldUtils\Currency;

use Espo\Core\{
    FieldUtils\Currency\Currency,
    FieldUtils\Currency\CurrencyConverter,
    FieldUtils\Currency\CurrencyConfigDataProvider,
    FieldUtils\Currency\CurrencyRates,
};

class CurrencyConverterTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp() : void
    {

    }

    public function testConvert1()
    {
        $currencyConfigDataProvider = $this->createMock(CurrencyConfigDataProvider::class);

        $currencyConfigDataProvider
            ->expects($this->any())
            ->method('hasCurrency')
            ->with('EUR')
            ->willReturn(true);

        $currencyConfigDataProvider
            ->expects($this->any())
            ->method('getCurrencyRate')
            ->willReturnMap([
                ['USD', 1.0],
                ['EUR', 1.2],
            ]);

        $value = new Currency(2.0, 'USD');

        $converer = new CurrencyConverter($currencyConfigDataProvider);

        $convertedValue = $converer->convert($value, 'EUR');

        $this->assertEquals('EUR', $convertedValue->getCode());

        $this->assertEquals(2.0 / 1.2, $convertedValue->getAmount());
    }

    public function testConvert2()
    {
        $currencyConfigDataProvider = $this->createMock(CurrencyConfigDataProvider::class);

        $currencyConfigDataProvider
            ->expects($this->any())
            ->method('hasCurrency')
            ->with('EUR')
            ->willReturn(true);

        $currencyConfigDataProvider
            ->expects($this->any())
            ->method('getCurrencyRate')
            ->willReturnMap([
                ['USD', 1.0],
                ['EUR', 1.2],
                ['UAH', 0.035],
            ]);

        $value = new Currency(2.0, 'UAH');

        $converer = new CurrencyConverter($currencyConfigDataProvider);

        $convertedValue = $converer->convert($value, 'EUR');

        $this->assertEquals('EUR', $convertedValue->getCode());

        $this->assertEquals(2.0 * 0.035 / 1.2, $convertedValue->getAmount());
    }

    public function testConvertToDefault()
    {
        $currencyConfigDataProvider = $this->createMock(CurrencyConfigDataProvider::class);

        $currencyConfigDataProvider
            ->expects($this->any())
            ->method('getDefaultCurrency')
            ->willReturn('USD');

        $currencyConfigDataProvider
            ->expects($this->any())
            ->method('hasCurrency')
            ->with('USD')
            ->willReturn(true);

        $currencyConfigDataProvider
            ->expects($this->any())
            ->method('getCurrencyRate')
            ->willReturnMap([
                ['USD', 1.0],
                ['EUR', 1.2],
            ]);

        $value = new Currency(2.0, 'EUR');

        $converer = new CurrencyConverter($currencyConfigDataProvider);

        $convertedValue = $converer->convertToDefault($value);

        $this->assertEquals('USD', $convertedValue->getCode());

        $this->assertEquals(2.0 * 1.2, $convertedValue->getAmount());
    }

    public function testConvertWithRates()
    {
        $currencyConfigDataProvider = $this->createMock(CurrencyConfigDataProvider::class);

        $rates = CurrencyRates::fromArray([
            'USD' => 1.0,
            'EUR' => 1.2,
            'UAH' => 0.035,
        ]);

        $value = new Currency(2.0, 'UAH');

        $converer = new CurrencyConverter($currencyConfigDataProvider);

        $convertedValue = $converer->convertWithRates($value, 'EUR', $rates);

        $this->assertEquals('EUR', $convertedValue->getCode());

        $this->assertEquals(2.0 * 0.035 / 1.2, $convertedValue->getAmount());
    }
}
