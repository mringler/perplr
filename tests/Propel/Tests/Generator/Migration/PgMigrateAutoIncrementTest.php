<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Migration;
use Propel\Generator\Exception\BuildException;
use Propel\Generator\Model\IdMethod;

/**
 * @group database
 * @group pgsql
 */
class PgMigrateAutoIncrementTest extends MigrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->con->exec('DROP TABLE IF EXISTS migration.migrate_auto_increment;');
    }

    /**
     * @param string $columnDef
     *
     * @return string
     */
    protected function buildDatabaseTableXml(?string $idMethod): string
    {
        $methodAttribute = ($idMethod) ? "idMethod=\"$idMethod\"" : '';
        $isAutoIncrement = ($idMethod) ? 'true' : "false";

        return <<< EOF
<database>
    <table name="migrate_auto_increment" $methodAttribute>
        <column name="id" type="integer" primaryKey="true" autoIncrement="$isAutoIncrement"/>
    </table>
</database>
EOF;
    }

    /**
     * @param string $description
     * @param string|null $idMethod
     * @param bool $changeRequired
     * @return void
     */
    protected function applyWithFail(string $description, ?string $idMethod, bool $changeRequired)
    {
        $databaseXml = $this->buildDatabaseTableXml($idMethod);

        try {
            $this->applyXmlAndTest($databaseXml, $changeRequired);
        } catch (BuildException $e) {
            $this->fail($description . "\n\n" . $e->getMessage());
        }
    }

    /**
     * @dataProvider migrateAutoIncrementDataProvider
     * @return void
     */
    public function testMigrateAutoIncrement(string $description, ?string $fromIdMethod, ?string $toIdMethod, bool $expectChange)
    {
        $this->applyWithFail($description . ' - failed to apply initial schema', $fromIdMethod, false);
        $this->applyWithFail($description . ' - failed to apply migration', $toIdMethod, $expectChange);
    }


    /**
     * @return array
     */
    public function migrateAutoIncrementDataProvider(): array
    {
        return [
            // description, first AI type, second AI type, expect change
            ['Make sequence type', null, IdMethod::SEQUENCE, false],
            ['Make identity type', null, IdMethod::IDENTITY, true],

            ['Sequence is native type', IdMethod::NATIVE, IdMethod::SEQUENCE, false],
        ];
    }
}
