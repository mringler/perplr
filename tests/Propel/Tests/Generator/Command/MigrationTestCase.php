<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Command;

use Propel\Generator\Command\AbstractCommand;
use Propel\Generator\Command\MigrationDiffCommand;
use Propel\Generator\Command\MigrationDownCommand;
use Propel\Generator\Command\MigrationUpCommand;
use Propel\Runtime\Perpl;
use Propel\Tests\TestCaseFixturesDatabase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use function array_merge;

abstract class MigrationTestCase extends TestCaseFixturesDatabase
{
    /**
     * Directory with default connection (can be any, schema.xml files use a
     * schema prefix for tables, so initial connection does not matter)
     *
     * @var string
     */
    private const CONNECTION_CONFIG_DIR = __DIR__ . '/../../../../Fixtures/migration';

    /**
     * Used when retrieving connection by name
     * (can be any, same as CONNECTION_CONFIG_DIR)
     *
     * @var string
     */
    protected const DATABASE_NAME = 'migration';

    /**
     * @var string
     */
    protected const OUTPUT_DIR = __DIR__ . '/../../../../migrationdiff';

    /**
     * @var string
     */
    private const SCHEMA_DIR_MIGRATE_TO_VERSION_PATTERN = __DIR__ . '/util/migrate-to-version/version-*';

    /**
     * @uses \Propel\Generator\Manager\MigrationManager::COL_VERSION
     *
     * @var string
     */
    private const COL_VERSION = 'version';

    /**
     * @var string
     */
    private const MIGRATION_TABLE = 'propel_migration';

    /**
     * @var bool
     */
    protected const MIGRATE_DOWN_AFTERWARDS = true;

    /**
     * @var string
     */
    protected const BASE_SCHEMA_DIR = __DIR__ . '/util/migrate-to-version/base';

    /**
     * @see \Propel\Generator\Command\MigrationMigrateCommand::COMMAND_OPTION_MIGRATE_TO_VERSION
     *
     * @var string
     */
    protected const COMMAND_OPTION_MIGRATE_TO_VERSION = '--migrate-to-version';

    /**
     * @see \Propel\Generator\Command\MigrationStatusCommand::COMMAND_OPTION_LAST_VERSION
     *
     * @var string
     */
    protected const COMMAND_OPTION_LAST_VERSION = '--last-version';

    /**
     * @return void
     */
    protected function deleteMigrationFiles(): void
    {
        $files = glob(self::OUTPUT_DIR . DIRECTORY_SEPARATOR . 'PropelMigration_*.php');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Runs the supplied command and returns its output.
     *
     * @param string $commandName
     * @param \Propel\Generator\Command\AbstractCommand $commandInstance
     * @param array $additionalArguments
     * @param bool $migrateDownAfterwards
     * @param bool $expectNonZeroExitCode
     *
     * @return string
     */
    protected function runCommandAndAssertSuccess(
        string $commandName,
        AbstractCommand $commandInstance,
        array $additionalArguments = [],
        bool $migrateDownAfterwards = false,
        bool $expectNonZeroExitCode = false
    ): string {
        $outputCapturer = new StreamOutput(fopen('php://temp', 'r+'));
        $exitCode = $this->runCommand($commandName, $commandInstance, $additionalArguments, $outputCapturer);

        if ($migrateDownAfterwards) {
            $this->migrateDown();
        }

        $streamedOutput = $outputCapturer->getStream();
        rewind($streamedOutput);
        $outputString = stream_get_contents($streamedOutput);

        if ($expectNonZeroExitCode) {
            $this->assertNotEquals(0, $exitCode, 'Expected non-zero exit code.');
        } else {
            $msg = "$commandName should exit successfully, but failed with message '$outputString'";
            $this->assertEquals(0, $exitCode, $msg);
        }

        return $outputString;
    }

    protected function runDiffAndAssertSuccess(
        array $additionalArguments = [],
        bool $migrateDownAfterwards = false,
        bool $expectNonZeroExitCode = false
    ) {
        return $this->runCommandAndAssertSuccess(
            'migration:diff',
            new MigrationDiffCommand(),
            array_merge(['--schema-dir' => self::BASE_SCHEMA_DIR], $additionalArguments),
            $migrateDownAfterwards,
            $expectNonZeroExitCode
        );
    }

    /**
     * @return void
     */
    protected function migrateUp(): void
    {
        $this->runCommand('migration:up', new MigrationUpCommand());
    }

    /**
     * @return void
     */
    protected function migrateDown(): void
    {
        $this->runCommand('migration:down', new MigrationDownCommand());
    }

    /**
     * Create application and run it
     *
     * @param string $commandName
     * @param \Propel\Generator\Command\AbstractCommand $commandInstance
     * @param array $additionalArguments
     * @param \Symfony\Component\Console\Output\StreamOutput|null $outputCapturer
     *
     * @return int
     */
    protected function runCommand(
        string $commandName,
        AbstractCommand $commandInstance,
        array $additionalArguments = [],
        ?StreamOutput $outputCapturer = null
    ): int {
        $applicationInputArguments = $this->buildApplicationInputArguments($commandName, $additionalArguments);

        if ($outputCapturer === null) {
            $outputCapturer = new StreamOutput(fopen('php://temp', 'r+'));
        }

        $app = new Application('Perpl', Perpl::VERSION);
        $app->addCommands([$commandInstance]); // Using addCommands for BC with Symfony < 8
        $app->setAutoExit(false);

        return $app->run($applicationInputArguments, $outputCapturer);
    }

    /**
     * @param string $commandName
     * @param array $additionalArguments
     *
     * @return \Symfony\Component\Console\Input\ArrayInput
     */
    protected function buildApplicationInputArguments(string $commandName, array $additionalArguments): ArrayInput
    {
        $additionalArguments['command'] = $commandName;

        $dsn = $this->getConnectionDsn(static::DATABASE_NAME, true);
        $connectionOption = ['migration_command=' . $dsn];

        $defaultAppArguments = [
            '--config-dir' => self::CONNECTION_CONFIG_DIR,
            '--output-dir' => self::OUTPUT_DIR,
            '--platform' => ucfirst(static::getVendorName()) . 'Platform',
            '--connection' => $connectionOption,
            '--verbose' => true,
        ];
        $args = array_merge($additionalArguments, $defaultAppArguments);

        return new ArrayInput($args);
    }

    /**
     * @param bool $containsCreateTable
     * @param string $fileGlobPattern
     *
     * @return void
     */
    protected function assertGeneratedFileContainsCreateTableStatement(bool $containsCreateTable, string $fileGlobPattern): void
    {
        $files = glob(self::OUTPUT_DIR . DIRECTORY_SEPARATOR . $fileGlobPattern);
        $this->assertCount(1, $files, 'Exactly one file should have been created');

        $file = $files[0];
        $content = file_get_contents($file);
        if ($containsCreateTable) {
            // unfortunatelly, the number of CREATE TABLE statements differs when running the tests alone or as part of the suite
            $this->assertStringContainsString('CREATE TABLE ', $content);
        } else {
            $this->assertStringNotContainsString('CREATE TABLE ', $content);
        }
    }

    /**
     * @param int $version
     *
     * @return void
     */
    protected function assertIsCurrentVersion(int $version): void
    {
        $sql = sprintf('SELECT %s FROM %s', self::COL_VERSION, self::MIGRATION_TABLE);

        $stmt = Perpl::getServiceContainer()->getConnection()->prepare($sql);
        $stmt->execute();

        $versions = $stmt->fetchAll();
        $lastVersion = array_pop($versions)[self::COL_VERSION];

        $this->assertSame($version, (int) $lastVersion);
    }

    /**
     * Creates migratoins according to the schemas in the folders
     * ./util/migrate-to-version/version-* and applies them.
     *
     * @return void
     */
    protected function setUpMigrateToVersion(): void
    {
        $this->deleteMigrationFiles();

        /** @var array<string> $versionDirectories */
        $versionDirectories = glob(self::SCHEMA_DIR_MIGRATE_TO_VERSION_PATTERN, GLOB_ONLYDIR);

        foreach ($versionDirectories as $i => $versionDirectory) {
            $timestamp = 1780000000 + $i;
            file_put_contents(self::OUTPUT_DIR . "/PropelMigration_$timestamp.php", ''); // use override to ensure distinct timestamps
            $this->runDiffAndAssertSuccess(['--schema-dir' => $versionDirectory, '--override' => true]);
            $this->migrateUp();
        }
    }

    /**
     * @param list<int> $migrationVersions
     *
     * @return void
     */
    protected function tearDownMigrateToVersion(array $migrationVersions): void
    {
        foreach ($migrationVersions as $migrationVersion) {
            $this->migrateDown();
        }

        $this->deleteMigrationFiles();
    }

    /**
     * @return list<int>
     */
    protected function lookupExistingMigrationTimestamps(): array
    {
        $migrationFiles = $this->lookupExistingMigrationFileNames();

        $migrationVersions = [];
        foreach ($migrationFiles as $migrationFile) {
            if (preg_match('/^PropelMigration_(\d+).*\.php$/', $migrationFile, $matches)) {
                $migrationVersions[] = (int) $matches[1];
            }
        }

        return $migrationVersions;
    }

    protected function lookupExistingMigrationFileNames(): array
    {
        return array_diff(scandir(static::OUTPUT_DIR) ?: [], array('..', '.'));
    }
}
