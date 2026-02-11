<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Common\Util;

use PHPUnit\Framework\TestCase;
use Propel\Common\Exception\SetColumnConverterException;
use Propel\Common\Util\SetColumnConverter;

/**
 * Tests for SetColumnConverter class.
 *
 * @author Moritz Schroeder <moritz.schroeder@molabs.de>
 */
class SetColumnConverterTest extends TestCase
{
    /**
     * @dataProvider convertValuesProvider
     *
     * @param array $values
     * @param string $validInteger
     *
     * @return void
     */
    public function testconvertToBitmaskValidValues(array $values, $validInteger)
    {
        $valueSet = ['a', 'b', 'c', 'd', 'e', 'f'];
        $intValue = SetColumnConverter::convertToBitmask($values, $valueSet);
        $this->assertSame($validInteger, $intValue);
    }

    /**
     * @return void
     */
    public function testconvertToBitmaskStringValue()
    {
        $valueSet = ['a', 'b', 'c', 'd', 'e', 'f'];
        $intValue = SetColumnConverter::convertToBitmask('c', $valueSet);
        $this->assertSame(4, $intValue);
    }

    /**
     * @return void
     */
    public function testconvertToBitmaskNullValue()
    {
        $valueSet = ['a', 'b', 'c', 'd', 'e', 'f'];
        $intValue = SetColumnConverter::convertToBitmask(null, $valueSet);
        $this->assertSame(0, $intValue);
    }

    /**
     * @return void
     */
    public function testconvertToBitmaskValueNotInSet()
    {
        $this->expectException(SetColumnConverterException::class);

        $valueSet = ['a', 'b', 'c', 'd', 'e', 'f'];
        SetColumnConverter::convertToBitmask(['g'], $valueSet);
    }

    /**
     * @dataProvider convertValuesProvider
     *
     * @param array $validArray
     * @param string $intValue
     *
     * @return void
     */
    public function testconvertBitmaskToArrayValidValues(array $validArray, $intValue)
    {
        $valueSet = ['a', 'b', 'c', 'd', 'e', 'f'];
        $arrayValue = SetColumnConverter::convertBitmaskToArray($intValue, $valueSet);
        $this->assertEquals($validArray, $arrayValue);
    }

    /**
     * @return void
     */
    public function testconvertBitmaskToArrayNullValue()
    {
        $valueSet = ['a', 'b', 'c', 'd', 'e', 'f'];
        $arrayValue = SetColumnConverter::convertBitmaskToArray(null, $valueSet);
        $this->assertSame([], $arrayValue);
    }

    /**
     * @return void
     */
    public function testconvertBitmaskToArrayIntOutOfRange()
    {
        $this->expectException(SetColumnConverterException::class);

        $valueSet = ['a', 'b', 'c', 'd', 'e', 'f'];
        SetColumnConverter::convertBitmaskToArray('65', $valueSet);
    }

    public function convertValuesProvider()
    {
        return [
            [['a'],             1],
            [['a', 'f'],        33],
            [['a', 'e', 'f'],   49],
            [['e', 'f'],        48],
        ];
    }
}
