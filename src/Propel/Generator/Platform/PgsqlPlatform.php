<?php

declare(strict_types = 1);

namespace Propel\Generator\Platform;

use Propel\Generator\Exception\EngineException;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\ColumnDefaultValue;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\Diff\ColumnDiff;
use Propel\Generator\Model\Diff\TableDiff;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\Index;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\Unique;
use function filter_var;
use function implode;
use function in_array;
use function preg_replace;
use function sprintf;
use function strpos;
use function strtolower;
use function strtoupper;
use function substr;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * Postgresql PlatformInterface implementation.
 */
class PgsqlPlatform extends DefaultPlatform
{
    protected string $createOrDropSequences = '';

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return string|null
     */
    #[\Override]
    protected function resolveSqlType(ColumnType $type): string|null
    {
        return match ($type) {
            ColumnType::BOOLEAN => 'BOOLEAN',
            ColumnType::TINYINT,
            ColumnType::SMALLINT,
            ColumnType::ENUM_BINARY,
             => 'INT2',
            ColumnType::BIGINT => 'INT8',
            //ColumnType::REAL => 'FLOAT',
            ColumnType::DOUBLE,
            ColumnType::FLOAT,
            => 'DOUBLE PRECISION',
            ColumnType::BINARY,
            ColumnType::VARBINARY,
            ColumnType::LONGVARBINARY,
            ColumnType::BLOB,
            ColumnType::OBJECT,
            ColumnType::UUID_BINARY,
            => 'BYTEA',
            ColumnType::LONGVARCHAR,
            ColumnType::CLOB,
            ColumnType::ARRAY
            => 'TEXT',
            ColumnType::DECIMAL => 'NUMERIC',
            ColumnType::DATETIME => 'TIMESTAMP',
            ColumnType::UUID => 'uuid',
            default => parent::resolveSqlType($type)
        };
    }

    /**
     * @return string
     */
    #[\Override]
    public function getNativeIdMethod(): string
    {
        return PlatformInterface::SERIAL;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getAutoIncrement(): string
    {
        return '';
    }

    /**
     * @return array<int>
     */
    #[\Override]
    public function getDefaultTypeSizes(): array
    {
        return [
            'char' => 1,
            'character' => 1,
            'integer' => 32,
            'bigint' => 64,
            'smallint' => 16,
            'double precision' => 54,
        ];
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $columnType
     *
     * @return bool
     */
    public static function columnTypeRequiresTransaction(ColumnType $columnType)
    {
        $pgRequiresTransactionTypes = [ColumnType::VARBINARY, ColumnType::LONGVARBINARY, ColumnType::BLOB];

        return in_array($columnType, $pgRequiresTransactionTypes, true);
    }

    /**
     * @return int
     */
    #[\Override]
    public function getMaxColumnNameLength(): int
    {
        return 63;
    }

    /**
     * @param string|int|bool $value
     *
     * @return string
     */
    #[\Override]
    public function getBooleanString($value): string
    {
        // parent method does the checking for allows string
        // representations & returns integer
        $value = parent::getBooleanString($value);

        return ($value ? "'t'" : "'f'");
    }

    /**
     * @return bool
     */
    #[\Override]
    public function supportsNativeDeleteTrigger(): bool
    {
        return true;
    }

    /**
     * Override to provide sequence names that conform to postgres' standard when
     * no id-method-parameter specified.
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function getSequenceName(Table $table): string
    {
        $result = null;
        if ($table->getIdMethod() == IdMethod::NATIVE) {
            $idMethodParams = $table->getIdMethodParameters();
            if (!$idMethodParams) {
                // We're going to ignore a check for max length (mainly
                // because I'm not sure how Postgres would handle this w/ SERIAL anyway)
                foreach ($table->getColumns() as $col) {
                    if ($col->isAutoIncrement()) {
                        $result = $table->getName() . '_' . $col->getName() . '_seq';

                        break; // there's only one auto-increment column allowed
                    }
                }
            } else {
                $result = $idMethodParams[0]->getValue();
            }
        }

        return $result;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    protected function buildAddSequenceDdl(Table $table): string
    {
        if (
            $table->getIdMethod() == IdMethod::NATIVE
            && $table->getIdMethodParameters() != null
        ) {
            $pattern = "
CREATE SEQUENCE %s;
";

            return sprintf(
                $pattern,
                $this->quoteIdentifier(strtolower($this->getSequenceName($table))),
            );
        }

        return '';
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    protected function buildDropSequenceDdl(Table $table): string
    {
        if (
            $table->getIdMethod() == IdMethod::NATIVE
            && $table->getIdMethodParameters() != null
        ) {
            $pattern = "
DROP SEQUENCE %s;
";

            return sprintf(
                $pattern,
                $this->quoteIdentifier(strtolower($this->getSequenceName($table))),
            );
        }

        return '';
    }

    /**
     * @param \Propel\Generator\Model\Database $database
     *
     * @return string
     */
    public function buildAddSchemasDdl(Database $database): string
    {
        $ret = '';
        $schemas = [];
        foreach ($database->getTables() as $table) {
            $vi = $table->getVendorInfoForType('pgsql');
            if (!$vi->hasParameter('schema') || isset($schemas[$vi->getParameter('schema')])) {
                continue;
            }
                $schemas[$vi->getParameter('schema')] = true;
            $ret .= $this->buildAddSchemaDdl($table);
        }

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildAddSchemaDdl(Table $table): string
    {
        $vi = $table->getVendorInfoForType('pgsql');
        if ($vi->hasParameter('schema')) {
            $pattern = "
CREATE SCHEMA %s;
";

            return sprintf($pattern, $this->quoteIdentifier($vi->getParameter('schema')));
        }

        return '';
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildUseSchemaDdl(Table $table): string
    {
        $vi = $table->getVendorInfoForType('pgsql');
        if ($vi->hasParameter('schema')) {
            $pattern = "
SET search_path TO %s;
";

            return sprintf($pattern, $this->quoteIdentifier($vi->getParameter('schema')));
        }

        return '';
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildResetSchemaDdl(Table $table): string
    {
        $vi = $table->getVendorInfoForType('pgsql');
        if ($vi->hasParameter('schema')) {
            return "
SET search_path TO public;
";
        }

        return '';
    }

    /**
     * @param \Propel\Generator\Model\Database $database
     *
     * @return string
     */
    #[\Override]
    public function buildAddTablesDdl(Database $database): string
    {
        $ret = $this->buildAddSchemasDdl($database);

        foreach ($database->getTablesForSql() as $table) {
            $this->normalizeTable($table);
        }

        foreach ($database->getTablesForSql() as $table) {
            $ret .= $this->buildCommentBlockDdl($table->getName());
            $ret .= $this->buildDropTableDdl($table);
            $ret .= $this->buildAddTableDdl($table);
            $ret .= $this->buildAddIndicesDdl($table);
        }
        foreach ($database->getTablesForSql() as $table) {
            $ret .= $this->buildAddForeignKeysDdl($table);
        }

        if ($ret) {
            $ret = $this->buildBeginDdl() . $ret . $this->buildEndDdl();
        }

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    #[\Override]
    public function buildForeignKeyDdl(ForeignKey $fk): string
    {
        $script = parent::buildForeignKeyDdl($fk);

        $pgVendorInfo = $fk->getVendorInfoForType('pgsql');
        if (filter_var($pgVendorInfo->getParameter('deferrable'), FILTER_VALIDATE_BOOLEAN)) {
            $script .= ' DEFERRABLE';
            if (filter_var($pgVendorInfo->getParameter('initiallyDeferred'), FILTER_VALIDATE_BOOLEAN)) {
                $script .= ' INITIALLY DEFERRED';
            }
        }

        return $script;
    }

    /**
     * @return string
     */
    #[\Override]
    public function buildBeginDdl(): string
    {
        return "
BEGIN;
";
    }

    /**
     * @return string
     */
    #[\Override]
    public function buildEndDdl(): string
    {
        return "
COMMIT;
";
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function buildAddForeignKeysDdl(Table $table): string
    {
        return $this->mapConcat([$this, 'buildAddForeignKeyDdl'], $table->getForeignKeys());
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function buildAddTableDdl(Table $table): string
    {
        $ret = $this->buildUseSchemaDdl($table);
        $ret .= $this->buildAddSequenceDdl($table);

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
CREATE TABLE %s
(
    %s
);
";
        $ret .= sprintf(
            $pattern,
            $this->quoteIdentifier($table->getName()),
            implode($sep, $lines),
        );

        if ($table->hasDescription()) {
            $pattern = "
COMMENT ON TABLE %s IS %s;
";
            $ret .= sprintf(
                $pattern,
                $this->quoteIdentifier($table->getName()),
                $this->quote($table->getDescription()),
            );
        }

        $ret .= $this->getAddColumnsComments($table);
        $ret .= $this->getResetSchemaDDL($table);

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    protected function getAddColumnsComments(Table $table): string
    {
        $ret = '';
        foreach ($table->getColumns() as $column) {
            $ret .= $this->getAddColumnComment($column);
        }

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string
     */
    protected function getAddColumnComment(Column $column): string
    {
        $pattern = "
COMMENT ON COLUMN %s.%s IS %s;
";
        if ($column->getDescription()) {
            return sprintf(
                $pattern,
                $this->quoteIdentifier($column->getTable()->getName()),
                $this->quoteIdentifier($column->getName()),
                $this->quote($column->getDescription()),
            );
        }

        return '';
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function buildDropTableDdl(Table $table): string
    {
        $tableName = $this->quoteIdentifier($table->getName());

        return $this->buildUseSchemaDdl($table)
            . "\nDROP TABLE IF EXISTS $tableName CASCADE;\n"
            . $this->buildDropSequenceDdl($table)
            . $this->buildResetSchemaDdl($table);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function getPrimaryKeyName(Table $table): string
    {
        $tableName = $table->getCommonName();

        return $tableName . '_pkey';
    }

    /**
     * @param \Propel\Generator\Model\Column $col
     *
     * @return string
     */
    #[\Override]
    public function buildColumnDefaultValueDdl(Column $col): string
    {
        }

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
     * @param \Propel\Generator\Model\Unique $unique
     *
     * @return string
     */
    #[\Override]
    public function buildUniqueDdl(Unique $unique): string
    {
        $name = $this->quoteIdentifier($unique->getName());
        $ddl = $this->buildColumnListDdl($unique->getColumnObjects());

        return "CONSTRAINT $name UNIQUE ($ddl)";
    }

    /**
     * @param string $fromTableName
     * @param string $toTableName
     *
     * @return string
     */
    #[\Override]
    public function buildRenameTableDdl(string $fromTableName, string $toTableName): string
    {
        $pos = strpos($toTableName, '.');
        if ($pos !== false) {
            $toTableName = substr($toTableName, $pos + 1);
        }

        $pattern = "
ALTER TABLE %s RENAME TO %s;
";

        return sprintf(
            $pattern,
            $this->quoteIdentifier($fromTableName),
            $this->quoteIdentifier($toTableName),
        );
    }

    /**
     * @see Platform::supportsSchemas()
     *
     * @return bool
     */
    #[\Override]
    public function supportsSchemas(): bool
    {
        return true;
    }

    /**
     * @param string $sqlType
     *
     * @return bool
     */
    #[\Override]
    public function hasSize(string $sqlType): bool
    {
        return !in_array(strtoupper($sqlType), ['BYTEA', 'TEXT', 'DOUBLE PRECISION'], true);
    }

    /**
     * @return bool
     */
    #[\Override]
    public function hasStreamBlobImpl(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function supportsVarcharWithoutSize(): bool
    {
        return true;
    }

    /**
     * @param \Propel\Generator\Model\Diff\TableDiff $tableDiff
     *
     * @return string
     */
    #[\Override]
    public function getModifyTableDDL(TableDiff $tableDiff): string
    {
        $ret = parent::getModifyTableDDL($tableDiff);

        if ($this->createOrDropSequences) {
            $ret = $this->createOrDropSequences . $ret;
        }

        $this->createOrDropSequences = '';

        return $ret;
    }

    /**
     * Overrides the implementation from DefaultPlatform
     *
     * @see DefaultPlatform::buildModifyColumnDdl
     *
     * @param \Propel\Generator\Model\Diff\ColumnDiff $columnDiff
     *
     * @return string
     */
    #[\Override]
    public function buildModifyColumnDdl(ColumnDiff $columnDiff): string
    {
        $ret = '';
        $changedProperties = $columnDiff->getChangedProperties();

        $fromColumn = $columnDiff->getFromColumn();
        $toColumn = clone $columnDiff->getToColumn();

        $fromTable = $fromColumn->getTable();
        $table = $toColumn->getTable();

        $colName = $this->quoteIdentifier($toColumn->getName());

        $pattern = "
ALTER TABLE %s ALTER COLUMN %s;
";

        if ($table && isset($changedProperties['autoIncrement'])) {
            $tableName = $table->getName();
            $colPlainName = $toColumn->getName();
            $seqName = "{$tableName}_{$colPlainName}_seq";

            if ($toColumn->isAutoIncrement() && $table->getIdMethodParameters() == null) {
                $defaultValue = "nextval('$seqName'::regclass)";
                $toColumn->setDefaultValue($defaultValue);
                $changedProperties['defaultValueValue'] = [null, $defaultValue];

                //add sequence
                if (!$fromTable->getDatabase()->hasSequence($seqName)) {
                    $this->createOrDropSequences .= sprintf(
                        "
CREATE SEQUENCE %s;
",
                        $seqName,
                    );
                    $fromTable->getDatabase()->addSequence($seqName);
                }
            }

            if (!$toColumn->isAutoIncrement() && $fromColumn->isAutoIncrement()) {
                //remove sequence
                if ($fromTable->getDatabase()->hasSequence($seqName)) {
                    $this->createOrDropSequences .= sprintf(
                        "
DROP SEQUENCE %s CASCADE;
",
                        $seqName,
                    );
                    $fromTable->getDatabase()->removeSequence($seqName);
                }
            }
        }

        if (isset($changedProperties['size']) || isset($changedProperties['type']) || isset($changedProperties['sqlType']) || isset($changedProperties['scale'])) {
            $sqlType = $toColumn->resolveSqlTypeName();

            if ($this->hasSize($sqlType) && $toColumn->isDefaultSqlType($this)) {
                if ($this->isNumber($sqlType)) {
                    if (strtoupper($sqlType) === 'NUMERIC') {
                        $sqlType .= $toColumn->getSizeDefinition();
                    }
                } else {
                    $sqlType .= $toColumn->getSizeDefinition();
                }
            }

            $using = $this->getUsingCast($fromColumn, $toColumn);
            if ($using) {
                $sqlType .= $using;
            }

            $ret .= sprintf(
                $pattern,
                $this->quoteIdentifier($table->getName()),
                $colName . ' TYPE ' . $sqlType,
            );
        }

        if (isset($changedProperties['defaultValueValue'])) {
            $property = $changedProperties['defaultValueValue'];
            if ($property[0] !== null && $property[1] === null) {
                $ret .= sprintf($pattern, $this->quoteIdentifier($table->getName()), $colName . ' DROP DEFAULT');
            } else {
                $ret .= sprintf($pattern, $this->quoteIdentifier($table->getName()), $colName . ' SET ' . $this->getColumnDefaultValueDDL($toColumn));
            }
        }

        if (isset($changedProperties['notNull'])) {
            $property = $changedProperties['notNull'];
            $notNull = ' DROP NOT NULL';
            if ($property[1]) {
                $notNull = ' SET NOT NULL';
            }
            $ret .= sprintf($pattern, $this->quoteIdentifier($table->getName()), $colName . $notNull);
        }

        return $ret;
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public function isUuid(string $type): bool
    {
        $strings = ['UUID'];

        return in_array(strtoupper($type), $strings, true);
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public function isString(string $type): bool
    {
        $strings = ['VARCHAR'];

        return in_array(strtoupper($type), $strings, true);
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public function isNumber(string $type): bool
    {
        $numbers = ['INTEGER', 'INT4', 'INT2', 'NUMBER', 'NUMERIC', 'SMALLINT', 'BIGINT', 'DECIMAL', 'REAL', 'DOUBLE PRECISION', 'SERIAL', 'BIGSERIAL'];

        return in_array(strtoupper($type), $numbers, true);
    }

    /**
     * @param \Propel\Generator\Model\Column $fromColumn
     * @param \Propel\Generator\Model\Column $toColumn
     *
     * @return string
     */
    public function getUsingCast(Column $fromColumn, Column $toColumn): string
    {
        $fromSqlType = strtoupper($fromColumn->resolveSqlTypeName());
        $toSqlType = strtoupper($toColumn->resolveSqlTypeName());
        $name = $fromColumn->getName();

        if ($this->isString($fromSqlType) && $this->isNumber($toSqlType)) {
            //cast from string to int
            return "
   USING CASE WHEN trim($name) SIMILAR TO '[0-9]+'
        THEN CAST(trim($name) AS integer)
        ELSE NULL END";
        }

        if ($this->isNumber($fromSqlType) && $toSqlType === 'BYTEA') {
            return " USING decode(CAST($name as text), 'escape')";
        }

        if (
            ($this->isNumber($fromSqlType) && $this->isNumber($toSqlType)) ||
            ($this->isString($fromSqlType) && $this->isString($toSqlType)) ||
            ($this->isNumber($fromSqlType) && $this->isString($toSqlType)) ||
            ($this->isUuid($fromSqlType) && $this->isString($toSqlType))
        ) {
            // no cast necessary
            return '';
        }

        if ($this->isString($fromSqlType) && $this->isUuid($toSqlType)) {
            return " USING $name::uuid";
        }

        return ' USING NULL';
    }

    /**
     * @see DefaultPlatform::buildModifyColumnsDdl()
     *
     * @param array<\Propel\Generator\Model\Diff\ColumnDiff> $columnDiffs
     *
     * @return string
     */
    #[\Override]
    public function buildModifyColumnsDdl(array $columnDiffs): string
    {
        return $this->mapConcat([$this, 'buildModifyColumnDdl'], $columnDiffs);
    }

    /**
     * @see DefaultPlatform::getAddColumnsDLL
     *
     * @param array<\Propel\Generator\Model\Column> $columns
     *
     * @return string
     */
    #[\Override]
    public function buildAddColumnsDdl(array $columns): string
    {
        return $this->mapConcat([$this, 'buildAddColumnDdl'], $columns);
    }

    /**
     * Overrides the implementation from DefaultPlatform
     *
     * @see DefaultPlatform::buildDropIndexDdl()
     *
     * @param \Propel\Generator\Model\Index $index
     *
     * @return string
     */
    #[\Override]
    public function buildDropIndexDdl(Index $index): string
    {
        if (!$index instanceof Unique) {
            return parent::buildDropIndexDdl($index);
        }

        $tableName = $this->quoteIdentifier($index->getTable()->getName());
        $indexName = $this->quoteIdentifier($index->getName());

        return "\nALTER TABLE $tableName DROP CONSTRAINT $indexName;\n";
    }

    /**
     * Get the PHP snippet for getting a Pk from the database.
     * Warning: duplicates logic from PgsqlAdapter::getId().
     * Any code modification here must be ported there.
     *
     * @param string $columnValueMutator
     * @param string $connectionVariableName
     * @param string $sequenceName
     * @param string $tab
     * @param string|null $phpType
     *
     * @throws \Propel\Generator\Exception\EngineException
     *
     * @return string
     */
    #[\Override]
    public function getIdentifierPhp(
        string $columnValueMutator,
        string $connectionVariableName = '$con',
        string $sequenceName = '',
        string $tab = '            ',
        ?string $phpType = null
    ): string {
        if (!$sequenceName) {
            throw new EngineException('PostgreSQL needs a sequence name to fetch primary keys');
        }
        $snippet = "
\$dataFetcher = %s->query(\"SELECT nextval('%s')\");
%s = %s\$dataFetcher->fetchColumn();";
        $script = sprintf(
            $snippet,
            $connectionVariableName,
            $sequenceName,
            $columnValueMutator,
            $phpType ? '(' . $phpType . ') ' : '',
        );

        return preg_replace('/^/m', $tab, $script);
    }

    /**
     * @param \Propel\Generator\Model\Index $index
     *
     * @return string
     */
    #[\Override]
    public function buildAddIndexDdl(Index $index): string
    {
        if (!$index->isUnique()) {
            return parent::buildAddIndexDdl($index);
        }

        $tableName = $this->quoteIdentifier($index->getTable()->getName());
        $indexName = $this->quoteIdentifier($index->getName());
        $ddl = $this->buildColumnListDdl($index->getColumnObjects());

        return "\nALTER TABLE $tableName ADD CONSTRAINT $indexName UNIQUE ($ddl);\n";
    }
}
