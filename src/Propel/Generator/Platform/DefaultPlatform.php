<?php

declare(strict_types = 1);

namespace Propel\Generator\Platform;

use Propel\Common\Util\SetColumnConverter;
use Propel\Generator\Config\AbstractGeneratorConfig;
use Propel\Generator\Exception\EngineException;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\Diff\ColumnDiff;
use Propel\Generator\Model\Diff\DatabaseDiff;
use Propel\Generator\Model\Diff\TableDiff;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\Index;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\TypeMapping;
use Propel\Generator\Model\Unique;
use Propel\Generator\Platform\Util\AlterTableStatementMerger;
use Propel\Runtime\Connection\ConnectionInterface;
use ReflectionClass;
use function array_search;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function preg_replace;
use function sprintf;
use function str_contains;
use function str_replace;
use function strlen;
use function strpos;
use function strtolower;
use function strtr;
use function substr;
use function trim;

/**
 * Default implementation for the PlatformInterface interface.
 */
class DefaultPlatform implements PlatformInterface
{
    protected ConnectionInterface|null $con = null;

    protected bool $identifierQuoting = true;

    protected bool $defaultToNativeEnumeratedColumnTypes = false;

    protected bool $hasNativeEnumType = false;

    /**
     * @var array<string, \Propel\Generator\Model\TypeMapping>
     */
    protected array $columnTypeMappingCache = [];

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional database connection to use in this platform.
     */
    public function __construct(?ConnectionInterface $con = null)
    {
        if ($con !== null) {
            $this->setConnection($con);
        }

        $this->initialize();
    }

    /**
     * @template T
     *
     * @param callable(T): string $fun
     * @param array<T> $array
     * @param string $separator
     *
     * @return string
     */
    final protected function mapConcat(callable $fun, array $array, string $separator = ''): string
    {
        $lines = array_map($fun, $array);

        return implode($separator, $lines);
    }

    /**
     * @param string $type
     *
     * @return string
     */
    public function getObjectBuilderClass(string $type): string
    {
        return '';
    }

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Database connection to use in this platform.
     *
     * @return void
     */
    #[\Override]
    public function setConnection(?ConnectionInterface $con = null): void
    {
        $this->con = $con;
    }

    /**
     * @return \Propel\Runtime\Connection\ConnectionInterface|null
     */
    #[\Override]
    public function getConnection(): ?ConnectionInterface
    {
        return $this->con;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function isIdentifierQuotingEnabled(): bool
    {
        return $this->identifierQuoting;
    }

    /**
     * @param bool $enabled
     *
     * @return void
     */
    #[\Override]
    public function setIdentifierQuoting(bool $enabled): void
    {
        $this->identifierQuoting = $enabled;
    }

    /**
     * Sets the GeneratorConfigInterface to use in the parsing.
     *
     * @param \Propel\Generator\Config\AbstractGeneratorConfig $generatorConfig
     *
     * @return void
     */
    #[\Override]
    public function setGeneratorConfig(AbstractGeneratorConfig $generatorConfig): void
    {
        $this->columnTypeMappingCache = [];
        $this->defaultToNativeEnumeratedColumnTypes = (bool)($generatorConfig->getConfigProperty('generator.defaultToNativeEnumeratedColumnTypes') ?? false);
    }

    /**
     * @return void
     */
    protected function initialize(): void
    {
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return \Propel\Generator\Model\TypeMapping
     */
    #[\Override]
    final public function getColumnTypeMapping(ColumnType $type): TypeMapping
    {
        $key = $type->name;
        if (empty($this->columnTypeMappingCache[$key])) {
            $this->columnTypeMappingCache[$key] = $this->resolveColumnTypeMapping($type);
        }

        return clone $this->columnTypeMappingCache[$key];
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return \Propel\Generator\Model\TypeMapping
     */
    protected function resolveColumnTypeMapping(ColumnType $type): TypeMapping
    {
        $resolvedType = $this->resolveColumnTypeAlias($type);
        $sqlType = $this->resolveSqlType($resolvedType);
        $size = $this->resolveTypeSize($resolvedType);

        return new TypeMapping($resolvedType, $sqlType, $size);
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return \Propel\Generator\Model\Datatype\ColumnType
     */
    protected function resolveColumnTypeAlias(ColumnType $type): ColumnType
    {
        return match ($type) {
            ColumnType::ENUM => $this->resolveColumnTypeAlias($this->defaultToNativeEnumeratedColumnTypes ? ColumnType::ENUM_NATIVE : ColumnType::ENUM_BINARY),
            ColumnType::SET => $this->resolveColumnTypeAlias($this->defaultToNativeEnumeratedColumnTypes ? ColumnType::SET_NATIVE : ColumnType::SET_BINARY),
            ColumnType::BU_DATE => ColumnType::DATE,
            ColumnType::BU_TIMESTAMP => ColumnType::TIMESTAMP,
            ColumnType::SET_NATIVE => $this->hasNativeEnumType ? $type : ColumnType::SET_BINARY,
            ColumnType::ENUM_NATIVE => $this->hasNativeEnumType ? $type : ColumnType::ENUM_BINARY,

            default => $type
        };
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return string|null
     */
    protected function resolveSqlType(ColumnType $type): string|null
    {
        return match ($type) {
            ColumnType::BOOLEAN,
            ColumnType::SET_BINARY
                => 'INTEGER',
            ColumnType::ENUM_BINARY
                => 'TINYINT',
            ColumnType::SET_NATIVE,
            ColumnType::ENUM_NATIVE
                => 'VARCHAR',
            default => null
        };
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return int|null
     */
    protected function resolveTypeSize(ColumnType $type): int|null
    {
        return match ($type) {
            default => null
        };
    }

    /**
     * Returns the short name of the database type that this platform represents.
     * For example MysqlPlatform->getDatabaseType() returns 'mysql'.
     *
     * @return string
     */
    #[\Override]
    public function getDatabaseType(): string
    {
        $reflectionClass = new ReflectionClass($this);
        $platformShortName = $reflectionClass->getShortName();
        $pos = strpos($platformShortName, 'Platform') ?: null;

        return strtolower(substr($platformShortName, 0, $pos));
    }

    /**
     * Returns the max column length supported by the db.
     *
     * @return int
     */
    #[\Override]
    public function getMaxColumnNameLength(): int
    {
        return 64;
    }

    /**
     * @phpstan-return non-empty-string
     *
     * @return string
     */
    #[\Override]
    public function getSchemaDelimiter(): string
    {
        return '.';
    }

    /**
     * Returns the native IdMethod (sequence|identity)
     *
     * @return string The native IdMethod (PlatformInterface:IDENTITY, PlatformInterface::SEQUENCE).
     */
    #[\Override]
    public function getNativeIdMethod(): string
    {
        return PlatformInterface::IDENTITY;
    }

    /**
     * @return bool
     */
    public function isNativeIdMethodAutoIncrement(): bool
    {
        return $this->getNativeIdMethod() === PlatformInterface::IDENTITY;
    }

    /**
     * @deprecated Use {@see static::getColumnTypeMapping()}
     *
     * @param \Propel\Generator\Model\Datatype\ColumnType $propelType
     *
     * @return \Propel\Generator\Model\TypeMapping
     */
    public function getDomainForType(ColumnType $propelType): TypeMapping
    {
        return $this->getColumnTypeMapping($propelType);
    }

    /**
     * Returns the NOT NULL string for the configured RDBMS.
     *
     * @param bool $notNull
     *
     * @return string
     */
    #[\Override]
    public function getNullString(bool $notNull): string
    {
        return $notNull ? 'NOT NULL' : '';
    }

    /**
     * Returns the auto increment strategy for the configured RDBMS.
     *
     * @return string
     */
    #[\Override]
    public function getAutoIncrement(): string
    {
        return 'IDENTITY';
    }

    /**
     * Returns the name to use for creating a table sequence.
     *
     * This will create a new name or use one specified in an
     * id-method-parameter tag, if specified.
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string|null
     */
    public function getSequenceName(Table $table): ?string
    {
        static $longNamesMap = [];
        $result = null;
        if ($table->getIdMethod() === IdMethod::NATIVE) {
            $idMethodParams = $table->getIdMethodParameters();
            $maxIdentifierLength = $this->getMaxColumnNameLength();
            if (!$idMethodParams) {
                if (strlen($table->getName() . '_SEQ') > $maxIdentifierLength) {
                    if (!isset($longNamesMap[$table->getName()])) {
                        $longNamesMap[$table->getName()] = (string)(count($longNamesMap) + 1);
                    }
                    $result = substr($table->getName(), 0, $maxIdentifierLength - strlen('_SEQ_' . $longNamesMap[$table->getName()])) . '_SEQ_' . $longNamesMap[$table->getName()];
                } else {
                    $result = substr($table->getName(), 0, $maxIdentifierLength - 4) . '_SEQ';
                }
            } else {
                $result = (string)substr($idMethodParams[0]->getValue(), 0, $maxIdentifierLength);
            }
        }

        return $result;
    }

    /**
     * Returns the DDL SQL to add the tables of a database
     * together with index and foreign keys
     *
     * @param \Propel\Generator\Model\Database $database
     *
     * @return string
     */
    public function buildAddTablesDdl(Database $database): string
    {
        $ret = $this->buildBeginDdl();
        foreach ($database->getTablesForSql() as $table) {
            $this->normalizeTable($table);
        }
        foreach ($database->getTablesForSql() as $table) {
            $ret .= $this->buildCommentBlockDdl($table->getName());
            $ret .= $this->buildDropTableDdl($table);
            $ret .= $this->buildAddTableDdl($table);
            $ret .= $this->buildAddIndicesDdl($table);
            $ret .= $this->buildAddForeignKeysDdl($table);
        }
        $ret .= $this->buildEndDdl();

        return $ret;
    }

    /**
     * Gets the requests to execute at the beginning of a DDL file
     *
     * @return string
     */
    public function buildBeginDdl(): string
    {
        return '';
    }

    /**
     * Gets the requests to execute at the end of a DDL file
     *
     * @return string
     */
    public function buildEndDdl(): string
    {
        return '';
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildDropTableDdl(Table $table): string
    {
        return "
DROP TABLE IF EXISTS " . $this->quoteIdentifier($table->getName()) . ";
";
    }

    /**
     * Builds the DDL SQL to add a table
     * without index and foreign keys
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function buildAddTableDdl(Table $table): string
    {
        $tableDescription = $table->hasDescription()
            ? $this->buildCommentLineDdl($table->getDescription())
            : '';

        $lines = [];

        foreach ($table->getColumns() as $column) {
            $lines[] = $this->buildColumnDdl($column);
        }

        if ($table->hasPrimaryKey()) {
            $lines[] = $this->buildPrimaryKeyDdl($table);
        }

        foreach ($table->getUnices() as $unique) {
            $lines[] = $this->buildUniqueDdl($unique);
        }

        $sep = ",
    ";

        $pattern = "
%sCREATE TABLE %s
(
    %s
);
";

        return sprintf(
            $pattern,
            $tableDescription,
            $this->quoteIdentifier($table->getName()),
            implode($sep, $lines),
        );
    }

    /**
     * @param \Propel\Generator\Model\Column $col
     *
     * @return string
     */
    #[\Override]
    public function buildColumnDdl(Column $col): string
    {
        $ddl = [$this->quoteIdentifier($col->getName())];
        $typeDeclaration = $col->resolveSqlTypeName();
        if ($this->hasSize($typeDeclaration) && $col->isDefaultSqlType($this)) {
            $typeDeclaration .= $col->getSizeDefinition();
        }
        $ddl[] = $typeDeclaration;

        $default = $this->getColumnDefaultValueDDL($col);

        if ($default) {
            $ddl[] = $default;
        }

        $notNull = $this->getNullString($col->isNotNull());

        if ($notNull) {
            $ddl[] = $notNull;
        }

        $autoIncrement = $col->getAutoIncrementString();

        if ($autoIncrement) {
            $ddl[] = $autoIncrement;
        }

        return implode(' ', $ddl);
    }

    /**
     * Returns the SQL for the default value of a Column object
     *
     * @param \Propel\Generator\Model\Column $col
     *
     * @return string
     */
    #[\Override]
    public function getColumnDefaultValueDDL(Column $col): string
    {
        $defaultValueObject = $col->getDefaultValue();
        if ($defaultValueObject === null) {
            return '';
        }

        $value = $defaultValueObject->getValue();
        if ($defaultValueObject->isExpression()) {
            return "DEFAULT $value";
        }

        if ($col->isTextType()) {
            $value = $this->quote((string)$value);
        } elseif (in_array($col->getColumnType(), [ColumnType::BOOLEAN, ColumnType::BOOLEAN_EMU], true)) {
            $value = $this->getBooleanString($value);
        } elseif ($col->isBinaryEnumType()) {
            $value = array_search($value, $col->getValueSet());
        } elseif ($col->isBinarySetType()) {
            $items = SetColumnConverter::itemsCsvToArray($value);
            $value = SetColumnConverter::convertToBitmask($items, $col->getValueSet());
        } elseif ($col->getColumnType() === ColumnType::SET_NATIVE) {
            if (str_contains($value, ',')) {
                return ''; // MySQL does not allow multiple values as default
            }
            $value = $this->quote((string)$value);
        } elseif ($col->isPhpArrayType()) {
            $value = $this->getPhpArrayString((string)$value);

            if ($value === null) {
                return '';
            }
        }

        return "DEFAULT $value";
    }

    /**
     * Creates a delimiter-delimited string list of column names, quoted using quoteIdentifier().
     *
     * @example
     * <code>
     * echo $platform->buildColumnListDdl(array('foo', 'bar');
     * // '"foo","bar"'
     * </code>
     *
     * @param array<\Propel\Generator\Model\Column> $columns
     * @param string $delimiter The delimiter to use in separating the column names.
     *
     * @return string
     */
    #[\Override]
    public function buildColumnListDdl(array $columns, string $delimiter = ','): string
    {
        $list = [];
        foreach ($columns as $column) {
            $columnName = $column->getName();
            $list[] = $this->quoteIdentifier($columnName);
        }

        return implode($delimiter, $list);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function getPrimaryKeyName(Table $table): string
    {
        $tableName = $table->getCommonName();

        return $tableName . '_pk';
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function buildPrimaryKeyDdl(Table $table): string
    {
        if ($table->hasPrimaryKey()) {
            return 'PRIMARY KEY (' . $this->getColumnListDDL($table->getPrimaryKey()) . ')';
        }

        return '';
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildDropPrimaryKeyDdl(Table $table): string
    {
        if (!$table->hasPrimaryKey()) {
            return '';
        }

        $pattern = "
ALTER TABLE %s DROP CONSTRAINT %s;
";

        return sprintf(
            $pattern,
            $this->quoteIdentifier($table->getName()),
            $this->quoteIdentifier($this->getPrimaryKeyName($table)),
        );
    }

    /**
     * Returns the DDL SQL to add the primary key of a table.
     *
     * @param \Propel\Generator\Model\Table $table From Table
     *
     * @return string
     */
    public function buildAddPrimaryKeyDdl(Table $table): string
    {
        if (!$table->hasPrimaryKey()) {
            return '';
        }

        $pattern = "
ALTER TABLE %s ADD %s;
";

        return sprintf(
            $pattern,
            $this->quoteIdentifier($table->getName()),
            $this->getPrimaryKeyDDL($table),
        );
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildAddIndicesDdl(Table $table): string
    {
        $ret = '';
        foreach ($table->getIndices() as $fk) {
            $ret .= $this->getAddIndexDDL($fk);
        }

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Index $index
     *
     * @return string
     */
    public function buildAddIndexDdl(Index $index): string
    {
        $pattern = "
CREATE %sINDEX %s ON %s (%s);
";

        return sprintf(
            $pattern,
            $index->isUnique() ? 'UNIQUE ' : '',
            $this->quoteIdentifier($index->getName()),
            $this->quoteIdentifier($index->getTable()->getName()),
            $this->getColumnListDDL($index->getColumnObjects()),
        );
    }

    /**
     * Builds the DDL SQL to drop an Index.
     *
     * @param \Propel\Generator\Model\Index $index
     *
     * @return string
     */
    public function buildDropIndexDdl(Index $index): string
    {
        $pattern = "
DROP INDEX %s;
";

        return sprintf(
            $pattern,
            $this->quoteIdentifier($index->getFQName()),
        );
    }

    /**
     * @param \Propel\Generator\Model\Index $index
     *
     * @return string
     */
    public function buildIndexDdl(Index $index): string
    {
        return sprintf(
            '%sINDEX %s (%s)',
            $index->isUnique() ? 'UNIQUE ' : '',
            $this->quoteIdentifier($index->getName()),
            $this->getColumnListDDL($index->getColumnObjects()),
        );
    }

    /**
     * @param \Propel\Generator\Model\Unique $unique
     *
     * @return string
     */
    public function buildUniqueDdl(Unique $unique): string
    {
        return sprintf('UNIQUE (%s)', $this->getColumnListDDL($unique->getColumnObjects()));
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildAddForeignKeysDdl(Table $table): string
    {
        $ret = '';
        foreach ($table->getForeignKeys() as $fk) {
            $ret .= $this->getAddForeignKeyDDL($fk);
        }

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    public function buildAddForeignKeyDdl(ForeignKey $fk): string
    {
        if ($fk->isSkipSql() || $fk->isPolymorphic()) {
            return '';
        }
        $pattern = "
ALTER TABLE %s ADD %s;
";

        return sprintf(
            $pattern,
            $this->quoteIdentifier($fk->getTable()->getName()),
            $this->getForeignKeyDDL($fk),
        );
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    public function buildDropForeignKeyDdl(ForeignKey $fk): string
    {
        if ($fk->isSkipSql() || $fk->isPolymorphic()) {
            return null;
        }
        $pattern = "
ALTER TABLE %s DROP CONSTRAINT %s;
";

        return sprintf(
            $pattern,
            $this->quoteIdentifier($fk->getTable()->getName()),
            $this->quoteIdentifier($fk->getName()),
        );
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    public function buildForeignKeyDdl(ForeignKey $fk): string
    {
        if ($fk->isSkipSql() || $fk->isPolymorphic()) {
            return '';
        }

        $pattern = "CONSTRAINT %s
    FOREIGN KEY (%s)
    REFERENCES %s (%s)";
        $script = sprintf(
            $pattern,
            $this->quoteIdentifier($fk->getName()),
            $this->getColumnListDDL($fk->getLocalColumnObjects()),
            $this->quoteIdentifier($fk->getForeignTableName()),
            $this->getColumnListDDL($fk->getForeignColumnObjects()),
        );
        if ($fk->hasOnUpdate()) {
            $script .= "
    ON UPDATE " . $fk->getOnUpdate();
        }
        if ($fk->hasOnDelete()) {
            $script .= "
    ON DELETE " . $fk->getOnDelete();
        }

        return $script;
    }

    /**
     * @param string $comment
     *
     * @return string
     */
    public function buildCommentLineDdl(string $comment): string
    {
        $pattern = "-- %s
";

        return sprintf($pattern, $comment);
    }

    /**
     * @param string $comment
     *
     * @return string
     */
    public function buildCommentBlockDdl(string $comment): string
    {
        $pattern = "
-----------------------------------------------------------------------
-- %s
-----------------------------------------------------------------------
";

        return sprintf($pattern, $comment);
    }

    /**
     * @param \Propel\Generator\Model\Diff\DatabaseDiff $databaseDiff
     *
     * @return string
     */
    public function buildModifyDatabaseDdl(DatabaseDiff $databaseDiff): string
    {
        $ret = '';
        foreach ($databaseDiff->getRemovedTables() as $table) {
            $ret .= $this->buildDropTableDdl($table);
        }

        foreach ($databaseDiff->getRenamedTables() as $fromTableName => $toTableName) {
            $ret .= $this->buildRenameTableDdl($fromTableName, $toTableName);
        }

        foreach ($databaseDiff->getAddedTables() as $table) {
            $ret .= $this->buildAddTableDdl($table);
            $ret .= $this->buildAddIndicesDdl($table);
        }

        foreach ($databaseDiff->getModifiedTables() as $tableDiff) {
            $ret .= $this->buildModifyTableDdl($tableDiff);
        }

        foreach ($databaseDiff->getAddedTables() as $table) {
            $ret .= $this->buildAddForeignKeysDdl($table);
        }

        return $ret
            ? $this->buildBeginDdl() . $ret . $this->buildEndDdl()
            : '';
    }

    /**
     * @param string $currentTableName
     * @param string $newTableName
     *
     * @return string
     */
    public function buildRenameTableDdl(string $currentTableName, string $newTableName): string
    {
        $currentTableName = $this->quoteIdentifier($currentTableName);
        $newTableName = $this->quoteIdentifier($newTableName);

        return "\nALTER TABLE $currentTableName RENAME TO $newTableName;\n";
    }

    /**
     * @param \Propel\Generator\Model\Diff\TableDiff $tableDiff
     *
     * @return string
     */
    public function buildModifyTableDdl(TableDiff $tableDiff): string
    {
        $ret = '';

        $toTable = $tableDiff->getToTable();

        // drop indices, foreign keys
        $ret .= $this->mapConcat([$this, 'buildDropForeignKeyDdl'], $tableDiff->getRemovedFks());
        $fromFks = array_column($tableDiff->getModifiedFks(), 0);
        $ret .= $this->mapConcat([$this, 'buildDropForeignKeyDdl'], $fromFks);

        $ret .= $this->mapConcat([$this, 'buildDropIndexDdl'], $tableDiff->getRemovedIndices());
        $fromIndexes = array_column($tableDiff->getModifiedIndices(), 0);
        $ret .= $this->mapConcat([$this, 'buildDropIndexDdl'], $fromIndexes);

        $columnChangeString = '';

        // alter table structure
        if ($tableDiff->hasModifiedPk()) {
            $columnChangeString .= $this->buildDropPrimaryKeyDdl($tableDiff->getFromTable());
        }
        foreach ($tableDiff->getRenamedColumns() as $columnRenaming) {
            $columnChangeString .= $this->buildRenameColumnDdl(...$columnRenaming);
        }

        $modifiedColumns = $tableDiff->getModifiedColumns();

        if ($modifiedColumns) {
            $columnChangeString .= $this->buildModifyColumnsDdl($modifiedColumns);
        }

        $addedColumns = $tableDiff->getAddedColumns();

        if ($addedColumns) {
            $columnChangeString .= $this->buildAddColumnsDdl($addedColumns);
        }
        $columnChangeString .= $this->mapConcat([$this, 'buildRemoveColumnDdl'], $tableDiff->getRemovedColumns());

        // add new indices and foreign keys
        if ($tableDiff->hasModifiedPk()) {
            $columnChangeString .= $this->buildAddPrimaryKeyDdl($tableDiff->getToTable());
        }

        $ret .= AlterTableStatementMerger::merge($toTable, $columnChangeString);

        // create indices, foreign keys
        $toIndex = array_column($tableDiff->getModifiedIndices(), 1);
        $ret .= $this->mapConcat([$this, 'buildAddIndexDdl'], $toIndex);
        $ret .= $this->mapConcat([$this, 'buildAddIndexDdl'], $tableDiff->getAddedIndices());

        $toFks = array_column($tableDiff->getModifiedFks(), 1);
        $ret .= $this->mapConcat([$this, 'buildAddForeignKeyDdl'], $toFks);
        $ret .= $this->mapConcat([$this, 'buildAddForeignKeyDdl'], $tableDiff->getAddedFks());

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Diff\TableDiff $tableDiff
     *
     * @return string
     */
    public function buildModifyTableColumnsDdl(TableDiff $tableDiff): string
    {
        $ret = '';

        $ret .= $this->mapConcat([$this, 'buildRemoveColumnDdl'], $tableDiff->getRemovedColumns());

        foreach ($tableDiff->getRenamedColumns() as $columnRenaming) {
            $ret .= $this->buildRenameColumnDdl(...$columnRenaming);
        }

        $modifiedColumns = $tableDiff->getModifiedColumns();

        if ($modifiedColumns) {
            $ret .= $this->getModifyColumnsDDL($modifiedColumns);
        }

        $addedColumns = $tableDiff->getAddedColumns();

        if ($addedColumns) {
            $ret .= $this->getAddColumnsDDL($addedColumns);
        }

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Diff\TableDiff $tableDiff
     *
     * @return string
     */
    public function buildModifyTablePrimaryKeyDdl(TableDiff $tableDiff): string
    {
        $ret = '';

        if ($tableDiff->hasModifiedPk()) {
            $ret .= $this->getDropPrimaryKeyDDL($tableDiff->getFromTable());
            $ret .= $this->getAddPrimaryKeyDDL($tableDiff->getToTable());
        }

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Diff\TableDiff $tableDiff
     *
     * @return string
     */
    public function buildModifyTableIndicesDdl(TableDiff $tableDiff): string
    {
        $ret = '';

        foreach ($tableDiff->getRemovedIndices() as $index) {
            $ret .= $this->getDropIndexDDL($index);
        }

        foreach ($tableDiff->getAddedIndices() as $index) {
            $ret .= $this->getAddIndexDDL($index);
        }

        foreach ($tableDiff->getModifiedIndices() as $indexModification) {
            [$fromIndex, $toIndex] = $indexModification;
            $ret .= $this->getDropIndexDDL($fromIndex);
            $ret .= $this->getAddIndexDDL($toIndex);
        }

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Diff\TableDiff $tableDiff
     *
     * @return string
     */
    public function buildModifyTableForeignKeysDdl(TableDiff $tableDiff): string
    {
        $ret = '';

        foreach ($tableDiff->getRemovedFks() as $fk) {
            $ret .= $this->getDropForeignKeyDDL($fk);
        }

        foreach ($tableDiff->getAddedFks() as $fk) {
            $ret .= $this->getAddForeignKeyDDL($fk);
        }

        foreach ($tableDiff->getModifiedFks() as $fkModification) {
            [$fromFk, $toFk] = $fkModification;
            $ret .= $this->getDropForeignKeyDDL($fromFk);
            $ret .= $this->getAddForeignKeyDDL($toFk);
        }

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string
     */
    public function buildRemoveColumnDdl(Column $column): string
    {
        $pattern = "
ALTER TABLE %s DROP COLUMN %s;
";

        return sprintf(
            $pattern,
            $this->quoteIdentifier($column->getTable()->getName()),
            $this->quoteIdentifier($column->getName()),
        );
    }

    /**
     * Builds the DDL SQL to rename a column
     *
     * @param \Propel\Generator\Model\Column $fromColumn
     * @param \Propel\Generator\Model\Column $toColumn
     *
     * @return string
     */
    public function buildRenameColumnDdl(Column $fromColumn, Column $toColumn): string
    {
        $pattern = "
ALTER TABLE %s RENAME COLUMN %s TO %s;
";

        return sprintf(
            $pattern,
            $this->quoteIdentifier($fromColumn->getTable()->getName()),
            $this->quoteIdentifier($fromColumn->getName()),
            $this->quoteIdentifier($toColumn->getName()),
        );
    }

    /**
     * Builds the DDL SQL to modify a column
     *
     * @param \Propel\Generator\Model\Diff\ColumnDiff $columnDiff
     *
     * @return string
     */
    public function buildModifyColumnDdl(ColumnDiff $columnDiff): string
    {
        $toColumn = $columnDiff->getToColumn();
        $pattern = "
ALTER TABLE %s MODIFY %s;
";

        return sprintf(
            $pattern,
            $this->quoteIdentifier($toColumn->getTable()->getName()),
            $this->getColumnDDL($toColumn),
        );
    }

    /**
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string
     */
    public function buildAddColumnDdl(Column $column): string
    {
        $lines = [];
        $table = null;
        foreach ($columnDiffs as $columnDiff) {
            $toColumn = $columnDiff->getToColumn();
            if ($table === null) {
                $table = $toColumn->getTable();
            }
            $lines[] = $this->getColumnDDL($toColumn);
        }

        $sep = ",
    ";

        $pattern = "
ALTER TABLE %s MODIFY
(
    %s
);
";

        return sprintf(
            $pattern,
            $this->quoteIdentifier($table->getName()),
            implode($sep, $lines),
        );
    }

    /**
     * @param array<\Propel\Generator\Model\Diff\ColumnDiff> $columnDiffs
     *
     * @return string
     */
    public function buildModifyColumnsDdl(array $columnDiffs): string
    {
        $pattern = "
ALTER TABLE %s ADD %s;
";

        return sprintf(
            $pattern,
            $this->quoteIdentifier($column->getTable()->getName()),
            $this->getColumnDDL($column),
        );
    }

    /**
     * @param array<\Propel\Generator\Model\Column> $columns
     *
     * @return string
     */
    public function buildAddColumnsDdl(array $columns): string
    {
        $lines = [];
        $table = null;
        foreach ($columns as $column) {
            if ($table === null) {
                $table = $column->getTable();
            }
            $lines[] = $this->getColumnDDL($column);
        }

        $sep = ",
    ";

        $pattern = "
ALTER TABLE %s ADD
(
    %s
);
";

        return sprintf(
            $pattern,
            $this->quoteIdentifier($table->getName()),
            implode($sep, $lines),
        );
    }

    /**
     * Returns if the RDBMS-specific SQL type has a size attribute.
     *
     * @param string $sqlType
     *
     * @return bool
     */
    #[\Override]
    public function hasSize(string $sqlType): bool
    {
        return true;
    }

    /**
     * Returns if the RDBMS-specific SQL type has a scale attribute.
     *
     * @param string $sqlType
     *
     * @return bool
     */
    #[\Override]
    public function hasScale(string $sqlType): bool
    {
        return true;
    }

    /**
     * Quote and escape needed characters in the string for underlying RDBMS.
     *
     * @param string $text
     *
     * @return string
     */
    #[\Override]
    public function quote(string $text): string
    {
        $con = $this->getConnection();
        if ($con) {
            return $con->quote($text);
        }

        return "'" . $this->disconnectedEscapeText($text) . "'";
    }

    /**
     * Method to escape text when no connection has been set.
     *
     * The subclasses can implement this using string replacement functions
     * or native DB methods.
     *
     * @param string $text Text that needs to be escaped.
     *
     * @return string
     */
    protected function disconnectedEscapeText(string $text): string
    {
        return str_replace("'", "''", $text);
    }

    /**
     * Quotes identifiers used in database SQL if isIdentifierQuotingEnabled is true.
     * Calls doQuoting() when identifierQuoting is enabled.
     *
     * @param string $text
     *
     * @return string Quoted identifier.
     */
    #[\Override]
    public function quoteIdentifier(string $text): string
    {
        return $this->isIdentifierQuotingEnabled() ? $this->doQuoting($text) : $text;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function doQuoting(string $text): string
    {
        return '"' . strtr($text, ['.' => '"."']) . '"';
    }

    /**
     * Whether RDBMS supports native ON DELETE triggers (e.g. ON DELETE CASCADE).
     *
     * @return bool
     */
    #[\Override]
    public function supportsNativeDeleteTrigger(): bool
    {
        return false;
    }

    /**
     * Whether RDBMS supports INSERT null values in autoincremented primary keys
     *
     * @return bool
     */
    #[\Override]
    public function supportsInsertNullPk(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function supportsIndexSize(): bool
    {
        return false;
    }

    /**
     * Whether the underlying PDO driver for this platform returns BLOB columns as streams (instead of strings).
     *
     * @return bool
     */
    #[\Override]
    public function hasStreamBlobImpl(): bool
    {
        return false;
    }

    /**
     * @see Platform::supportsSchemas()
     *
     * @return bool
     */
    #[\Override]
    public function supportsSchemas(): bool
    {
        return false;
    }

    /**
     * @see Platform::supportsMigrations()
     *
     * @return bool
     */
    #[\Override]
    public function supportsMigrations(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function supportsVarcharWithoutSize(): bool
    {
        return false;
    }

    /**
     * Returns the boolean value.
     *
     * This value should match the boolean value that is set
     * when using Propel's PreparedStatement::setBoolean().
     *
     * This function is used to set default column values when building
     * SQL.
     *
     * @param string|int|bool $value A Boolean or string representation of Boolean ('y', 'true').
     *
     * @return string
     */
    #[\Override]
    public function getBooleanString($value): string
    {
        if ($value === true || $value === 1) {
            return '1';
        }

        if (
            is_string($value)
            && in_array(strtolower($value), ['1', 'true', 'y', 'yes'], true)
        ) {
            return '1';
        }

        return '0';
    }

    /**
     * @param string $stringValue
     *
     * @return string|null
     */
    public function getPhpArrayString(string $stringValue): ?string
    {
        $stringValue = trim($stringValue);
        if (!$stringValue) {
            return null;
        }

        $values = [];
        foreach (explode(',', $stringValue) as $v) {
            $values[] = trim($v);
        }

        $value = implode(' | ', $values);
        if ($value === ' | ') {
            return null;
        }

        return $this->quote(sprintf('||%s||', $value));
    }

    /**
     * Gets the preferred timestamp formatter for setting date/time values.
     *
     * @param bool $withMilliseconds
     *
     * @return string
     */
    #[\Override]
    public function getTimestampFormatter(bool $withMilliseconds = true): string
    {
        return $this->getDateFormatter() . ' ' . $this->getTimeFormatter($withMilliseconds);
    }

    /**
     * Gets the preferred time formatter for setting date/time values.
     *
     * @param bool $withMilliseconds
     *
     * @return string
     */
    #[\Override]
    public function getTimeFormatter(bool $withMilliseconds = true): string
    {
        return 'H:i:s' . ($withMilliseconds ? '.u' : '');
    }

    /**
     * Gets the preferred date formatter for setting date/time values.
     *
     * @return string
     */
    #[\Override]
    public function getDateFormatter(): string
    {
        return 'Y-m-d';
    }

    /**
     * Returns the appropriate formatter for a date/time column.
     *
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string|null
     */
    #[\Override]
    public function getTemporalFormatter(Column $column): string|null
    {
        $withMilliseconds = (bool)$column->getTypeMapping()->getSize();

        return match ($column->getColumnType()) {
            ColumnType::DATE => $this->getDateFormatter(),
            ColumnType::TIME => $this->getTimeFormatter($withMilliseconds),
            ColumnType::TIMESTAMP,
            ColumnType::DATETIME => $this->getTimestampFormatter($withMilliseconds),
            default => null,
        };
    }

    /**
     * Get the default On Delete behavior for foreign keys when not explicitly set.
     *
     * @return string
     */
    #[\Override]
    public function getDefaultForeignKeyOnDeleteBehavior(): string
    {
        return ForeignKey::NONE;
    }

    /**
     * Get the default On Update behavior for foreign keys when not explicitly set.
     *
     * @return string
     */
    #[\Override]
    public function getDefaultForeignKeyOnUpdateBehavior(): string
    {
        return ForeignKey::NONE;
    }

    /**
     * Get the PHP snippet for binding a value to a column.
     * Warning: duplicates logic from AdapterInterface::bindValue().
     * Any code modification here must be ported there.
     *
     * @param \Propel\Generator\Model\Column $column
     * @param string $identifier
     * @param string $columnValueAccessor
     * @param string $tab
     *
     * @return string
     */
    #[\Override]
    public function getColumnBindingPHP(Column $column, string $identifier, string $columnValueAccessor, string $tab = '            '): string
    {
        $script = '';
        if ($column->isLobType()) {
            // we always need to make sure that the stream is rewound, otherwise nothing will
            // get written to database.
            $script .= "
if (is_resource($columnValueAccessor)) {
    rewind($columnValueAccessor);
}";
        }

        $pdoType = $column->getColumnType()->toPdoConstantName();
        $script .= "\n\$stmt->bindValue($identifier, $columnValueAccessor, $pdoType);";

        return preg_replace('/^(.+)/m', $tab . '$1', $script);
    }

    /**
     * Get the PHP snippet for getting a Pk from the database.
     *
     * Typical output:
     * <code>
     * $this->id = $con->lastInsertId();
     * </code>
     *
     * @param string $columnValueMutator
     * @param string $connectionVariableName
     * @param string $sequenceName
     * @param string $tab
     * @param string|null $phpType
     *
     * @return string
     */
    public function getIdentifierPhp(
        string $columnValueMutator,
        string $connectionVariableName = '$con',
        string $sequenceName = '',
        string $tab = '            ',
        ?string $phpType = null
    ): string {
        return sprintf(
            "
%s%s = %s%s->lastInsertId(%s);",
            $tab,
            $columnValueMutator,
            $connectionVariableName,
            $phpType ? '(' . $phpType . ') ' : '',
            $sequenceName ? ("'" . $sequenceName . "'") : '',
        );
    }

    /**
     * Returns an integer indexed array of default type sizes.
     *
     * @return array<int> type indexed array of integers
     */
    public function getDefaultTypeSizes(): array
    {
        return [];
    }

    /**
     * Returns the default size of a specific type.
     *
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return int
     */
    public function getDefaultTypeSize(ColumnType $type): int
    {
        $sizes = $this->getDefaultTypeSizes();

        return $sizes[strtolower($type->name)] ?? 0;
    }

    /**
     * Normalizes a table for the current platform. Very important for the TableComparator to not
     * generate useless diffs.
     * Useful for checking needed definitions/structures. E.g. Unique Indexes for ForeignKey columns,
     * which the most Platforms requires but which is not always explicitly defined in the table model.
     *
     * @param \Propel\Generator\Model\Table $table The table object which gets modified.
     *
     * @return void
     */
    #[\Override]
    public function normalizeTable(Table $table): void
    {
        if ($table->hasForeignKeys()) {
            foreach ($table->getForeignKeys() as $fk) {
                if ($fk->getForeignTable() && !$fk->getForeignTable()->isUnique($fk->getForeignColumnObjects())) {
                    $unique = new Unique();
                    $unique->setColumns($fk->getForeignColumnObjects());
                    $fk->getForeignTable()->addUnique($unique);
                }
            }
        }

        if (!$this->supportsIndexSize() && $table->getIndices()) {
            // when the platform does not support index sizes we reset it
            foreach ($table->getIndices() as $index) {
                $index->resetColumnsSize();
            }
        }

        foreach ($table->getColumns() as $column) {
            $defaultSize = $this->getDefaultTypeSize($column->getColumnType());

            if ($column->getSize() && $defaultSize) {
                if ($column->getScale() === null && (int)$column->getSize() === $defaultSize) {
                    $column->setSize(null);
                }
            }
        }
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $columnType
     * @param array<string> $valueSet
     *
     * @throws \Propel\Generator\Exception\EngineException
     *
     * @return string
     */
    #[\Override]
    public function buildNativeEnumeratedColumnSqlType(ColumnType $columnType, array $valueSet): string
    {
        if (!in_array($columnType, [ColumnType::ENUM_NATIVE, ColumnType::SET_NATIVE])) {
            throw new EngineException("Only native ENUM or SET type columns can be turned to sql type, but type is {$columnType->name}");
        }

        $typeLiteral = $columnType === ColumnType::ENUM_NATIVE ? 'ENUM' : 'SET';
        $valuesCsv = "'" . implode("','", $valueSet) . "'";

        return "$typeLiteral($valuesCsv)";
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (str_starts_with($name, 'get') && str_ends_with($name, 'DDL')) {
            $newName = 'build' . substr($name, 3, -3) . 'Ddl';
            trigger_deprecation('Perpl', '2.10.4', "Update to new function name: $name() is now $newName()");

            return $this->$newName(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Undefined method %s::%s()', self::class, $name));
    }
}
