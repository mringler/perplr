<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Model;

use Propel\Generator\Exception\EngineException;
use Propel\Generator\Model\ColumnDefaultValue;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\TypeMapping;

/**
 */
class TypeMappingTest extends ModelTestCase
{
    /**
     * @return void
     */
    public function testCreateNewDomain()
    {
        $domain = new TypeMapping(ColumnType::FLOAT, 'DOUBLE', 10, 2);

        $this->assertSame(ColumnType::FLOAT, $domain->getMappingType());
        $this->assertSame('DOUBLE', $domain->getSqlType());
        $this->assertSame(10, $domain->getSize());
        $this->assertSame(2, $domain->getScale());
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideDomainData')]
    public function testSetupObject($default, $expression)
    {
        $platform = $this->getPlatformMock();
        $platform
            ->expects($this->any())
            ->method('getColumnTypeMapping')
            ->will($this->returnValue(new TypeMapping(ColumnType::BOOLEAN)));

        $domain = new TypeMapping();
        $domain->setDatabase($this->getDatabaseMock('bookstore', [
            'platform' => $platform,
        ]));
        $domain->loadMapping([
            'type' => 'BOOLEAN',
            'name' => 'foo',
            'default' => $default,
            'defaultExpr' => $expression,
            'size' => 10,
            'scale' => 2,
            'description' => 'Some description',
        ]);

        $this->assertSame(ColumnType::BOOLEAN, $domain->getMappingType());
        $this->assertSame('foo', $domain->getName());
        $this->assertInstanceOf('Propel\Generator\Model\ColumnDefaultValue', $domain->getDefaultValue());
        $this->assertSame(10, $domain->getSize());
        $this->assertSame(2, $domain->getScale());
        $this->assertSame('Some description', $domain->getDescription());
    }

    public static function provideDomainData()
    {
        return [
            [1, null],
            [null, 'NOW()'],
        ];
    }

    /**
     * @return void
     */
    public function testSetDatabase()
    {
        $domain = new TypeMapping();
        $domain->setDatabase($this->getDatabaseMock('bookstore'));

        $this->assertInstanceOf('Propel\Generator\Model\Database', $domain->getDatabase());
    }

    /**
     * @return void
     */
    public function testReplaceMappingAndSqlTypes()
    {
        $value = $this->getColumnDefaultValueMock();

        $domain = new TypeMapping(ColumnType::FLOAT, 'DOUBLE');
        $domain->replaceType(ColumnType::BOOLEAN);
        $domain->replaceSqlType('INT');
        $domain->replaceDefaultValue($value);

        $this->assertSame(ColumnType::BOOLEAN, $domain->getMappingType());
        $this->assertSame('INT', $domain->getSqlType());
        $this->assertInstanceOf('Propel\Generator\Model\ColumnDefaultValue', $value);
    }

    /**
     * @return void
     */
    public function testGetNoPhpDefaultValue()
    {
        $domain = new TypeMapping();

        $this->assertNull($domain->getPhpDefaultValue());
    }

    /**
     * @return void
     */
    public function testGetPhpDefaultValue()
    {
        $value = $this->getColumnDefaultValueMock();
        $value
            ->expects($this->once())
            ->method('getValue')
            ->will($this->returnValue('foo'));

        $domain = new TypeMapping(ColumnType::VARCHAR);
        $domain->setDefaultValue($value);

        $this->assertSame('foo', $domain->getPhpDefaultValue());
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideBooleanValues')]
    public function testGetBooleanValue($mappingType, $booleanAsString, $expected)
    {
        $value = $this->getColumnDefaultValueMock();
        $value
            ->expects($this->once())
            ->method('getValue')
            ->will($this->returnValue($booleanAsString));

        $domain = new TypeMapping($mappingType);
        $domain->setDefaultValue($value);

        $this->assertSame($expected, $domain->getPhpDefaultValue());
    }

    public static function provideBooleanValues()
    {
        return [
            [ColumnType::BOOLEAN, '1', true],
            [ColumnType::BOOLEAN, '0', false],
            [ColumnType::BOOLEAN, 't', true],
            [ColumnType::BOOLEAN, 'f', false],
            [ColumnType::BOOLEAN, 'y', true],
            [ColumnType::BOOLEAN, 'n', false],
            [ColumnType::BOOLEAN, 'yes', true],
            [ColumnType::BOOLEAN, 'no', false],
            [ColumnType::BOOLEAN, 'true', true],
            [ColumnType::BOOLEAN_EMU, 'true', true],
            [ColumnType::BOOLEAN, 'false', false],
            [ColumnType::BOOLEAN_EMU, 'false', false],
        ];
    }

    /**
     * @return void
     */
    public function testCantGetPhpDefaultValue()
    {
        $value = $this->getColumnDefaultValueMock();
        $value
            ->expects($this->once())
            ->method('isExpression')
            ->will($this->returnValue(true));

        $domain = new TypeMapping();
        $domain->setDefaultValue($value);

        $this->expectException(EngineException::class);
        $domain->getPhpDefaultValue();
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideSizeDefinitions')]
    public function testGetSizeDefinition($size, $scale, $definition)
    {
        $domain = new TypeMapping(ColumnType::FLOAT, 'DOUBLE', $size, $scale);

        $this->assertSame($definition, $domain->getSizeDefinition());
    }

    public static function provideSizeDefinitions()
    {
        return [
            [10, null, '(10)'],
            [10, 2, '(10,2)'],
            [null, null, ''],
        ];
    }

    /**
     * @return void
     */
    public function testCopyDomain()
    {
        $value = $this->getColumnDefaultValueMock();

        $domain = new TypeMapping();
        $domain->setMappingType(ColumnType::FLOAT);
        $domain->setSqlType('DOUBLE');
        $domain->setSize(10);
        $domain->setScale(2);
        $domain->setName('Mapping between FLOAT and DOUBLE');
        $domain->setDescription('Some description');
        $domain->setDefaultValue($value);

        $newDomain = new TypeMapping();
        $newDomain->copy($domain);

        $this->assertSame(ColumnType::FLOAT, $newDomain->getMappingType());
        $this->assertSame('DOUBLE', $newDomain->getSqlType());
        $this->assertSame(10, $newDomain->getSize());
        $this->assertSame(2, $newDomain->getScale());
        $this->assertSame('Mapping between FLOAT and DOUBLE', $newDomain->getName());
        $this->assertSame('Some description', $newDomain->getDescription());
        $this->assertInstanceOf(ColumnDefaultValue::class, $value);
    }

    private function getColumnDefaultValueMock()
    {
        $value = $this
            ->getMockBuilder('Propel\Generator\Model\ColumnDefaultValue')
            ->disableOriginalConstructor()
            ->getMock();

        return $value;
    }
}
