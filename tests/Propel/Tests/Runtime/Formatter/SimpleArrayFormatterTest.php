<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\Formatter;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Formatter\SimpleArrayFormatter;
use Propel\Runtime\Propel;
use Propel\Tests\Bookstore\Map\BookTableMap;
use Propel\Tests\Helpers\Bookstore\BookstoreDataPopulator;
use Propel\Tests\Helpers\Bookstore\BookstoreEmptyTestBase;

/**
 * @group database
 */
class SimpleArrayFormatterTest extends BookstoreEmptyTestBase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        BookstoreDataPopulator::populate();
    }

    /**
     * @return void
     */
    public function testFormatWithOneRowAndValueIsNotZero()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        $stmt = $con->query('SELECT 1 FROM book');

        $formatter = new SimpleArrayFormatter();
        $formatter->init(new ModelCriteria('bookstore', '\Propel\Tests\Bookstore\Book'));

        $books = $formatter->format($stmt);
        $this->assertInstanceOf('\Propel\Runtime\Collection\Collection', $books);
        $this->assertCount(4, $books);
        $this->assertSame(1, $books[0] + 0);
    }

    /**
     * @return void
     */
    public function testFormatWithOneRowAndValueEqualsZero()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        $stmt = $con->query('SELECT 0 FROM book');

        $formatter = new SimpleArrayFormatter();
        $formatter->init(new ModelCriteria('bookstore', '\Propel\Tests\Bookstore\Book'));

        $books = $formatter->format($stmt);
        $this->assertInstanceOf('\Propel\Runtime\Collection\Collection', $books);
        $this->assertCount(4, $books);
        $this->assertSame(0, $books[0] + 0);
    }

    public static function SelectValueDataProvider(): array
    {
        return [
            [0], [1]
        ];
    }

    /**
     * @return void
     */
    #[DataProvider('SelectValueDataProvider')]
    public function testFormatOneWithOneRowAndValueEqualsZero(int $selectValue)
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        $sql = $this->runningOnMySQL()
            ? "SELECT $selectValue FROM book LIMIT 0, 1"
            : "SELECT $selectValue FROM book LIMIT 1";

        $stmt = $con->query($sql);

        $formatter = new SimpleArrayFormatter();
        $formatter->init(new ModelCriteria('bookstore', '\Propel\Tests\Bookstore\Book'));

        $book = $formatter->formatOne($stmt);
        $this->assertSame($selectValue, $book + 0);
    }
}
