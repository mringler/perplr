<?php

declare(strict_types = 1);

namespace Propel\Runtime\Map;

use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Runtime\Adapter\AdapterInterface;
use Propel\Runtime\Map\Exception\ForeignKeyNotFoundException;
use function array_key_exists;
use function array_search;
use function in_array;
use function sprintf;
use function strrpos;
use function strtoupper;
use function substr;
use function trim;

/**
 * ColumnMap is used to model a column of a table in a database.
 *
 * GENERAL NOTE
 * ------------
 * The propel.map classes are abstract building-block classes for modeling
 * the database at runtime. These classes are similar (a lite version) to the
 * propel.engine.database.model classes, which are build-time modeling classes.
 * These classes in themselves do not do any database metadata lookups.
 */
class ColumnMap
{
    protected string $columnName;

    protected string $phpName;

    protected TableMap $table;

    protected ColumnType $columnType;

    protected int $size = 0;

    protected bool $pk = false;

    protected bool $notNull = false;

    /**
     * @var scalar|null
     */
    protected $defaultValue;

    protected string $relatedTableName = '';

    protected string $relatedColumnName = '';

    /**
     * Values for ENUM or SET column
     */
    protected array $valueSet = [];

    protected bool $isPkString = false;

    /**
     * @param string $name The name of the column.
     * @param \Propel\Runtime\Map\TableMap $containingTable TableMap of the table this column is in.
     * @param string $phpName The php name of the column.
     * @param \Propel\Generator\Model\Datatype\ColumnType $type A string specifying the Propel type.
     */
    public function __construct(string $name, TableMap $containingTable, string $phpName, ColumnType $type)
    {
        $this->columnName = $name;
        $this->table = $containingTable;
        $this->phpName = $phpName;
        $this->columnType = $type;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->columnName;
    }

    /**
     * @deprecated use aptly named getTableMap().
     *
     * @return \Propel\Runtime\Map\TableMap
     */
    public function getTable(): TableMap
    {
        return $this->getTableMap();
    }

    /**
     * Get the table map this column belongs to.
     *
     * @return \Propel\Runtime\Map\TableMap
     */
    public function getTableMap(): TableMap
    {
        return $this->table;
    }

    /**
     * Get the name of the table this column is in.
     *
     * @return string|null A String with the table name.
     */
    public function getTableName(): ?string
    {
        return $this->table->getName();
    }

    /**
     * Get the table name + column name.
     *
     * @return string A String with the full column name.
     */
    public function getFullyQualifiedName(): string
    {
        return $this->getTableName() . '.' . $this->columnName;
    }

    /**
     * Set the php name of this column.
     *
     * @param string $phpName A string representing the PHP name.
     *
     * @return void
     */
    public function setPhpName(string $phpName): void
    {
        $this->phpName = $phpName;
    }

    /**
     * Get the name of a column.
     *
     * @return string A String with the column name.
     */
    public function getPhpName(): string
    {
        return $this->phpName;
    }

    /**
     * Set the Propel type of this column.
     *
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return void
     */
    public function setType(ColumnType $type): void
    {
        $this->columnType = $type;
    }

    /**
     * @return \Propel\Generator\Model\Datatype\ColumnType
     */
    public function getColumnType(): ColumnType
    {
        return $this->columnType;
    }

    /**
     * @return int The PDO::PARAM_* value
     */
    public function getPdoType(): int
    {
        return $this->columnType->toPdoType();
    }

    /**
     * Whether this is a BLOB, LONGVARBINARY, or VARBINARY.
     *
     * @return bool
     */
    public function isLob(): bool
    {
        return in_array($this->columnType, [
            ColumnType::BLOB,
            ColumnType::VARBINARY,
            ColumnType::LONGVARBINARY,
        ], true);
    }

    /**
     * Whether this is a DATE/TIME/TIMESTAMP column.
     *
     * @return bool
     */
    public function isTemporal(): bool
    {
        return $this->columnType->isTemporalType();
    }

    /**
     * Whether this column is numeric (int, decimal, bigint etc).
     *
     * @return bool
     */
    public function isNumeric(): bool
    {
        return $this->columnType->isNumericType();
    }

    /**
     * @return bool
     */
    public function isSetType(): bool
    {
        return $this->columnType === ColumnType::SET_BINARY;
    }

    /**
     * Whether this column is a text column (varchar, char, longvarchar).
     *
     * @return bool
     */
    public function isText(): bool
    {
        return in_array($this->columnType, [
            ColumnType::VARCHAR,
            ColumnType::LONGVARCHAR,
            ColumnType::CHAR,
        ], true);
    }

    /**
     * Whether this column contains UUIDs.
     *
     * @return bool
     */
    public function isUuid(): bool
    {
        return $this->columnType->isUuidType();
    }

    /**
     * Set the size of this column.
     *
     * @param int|null $size An int specifying the size.
     *
     * @return void
     */
    public function setSize(?int $size): void
    {
        $this->size = (int)$size;
    }

    /**
     * Get the size of this column.
     *
     * @return int An int specifying the size.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Set if this column is a primary key or not.
     *
     * @param bool $pk True if column is a primary key.
     *
     * @return void
     */
    public function setPrimaryKey(bool $pk): void
    {
        $this->pk = $pk;
    }

    /**
     * Is this column a primary key?
     *
     * @return bool True if column is a primary key.
     */
    public function isPrimaryKey(): bool
    {
        return $this->pk;
    }

    /**
     * @param bool $isNotNull
     *
     * @return void
     */
    public function setNotNull(bool $isNotNull): void
    {
        $this->notNull = $isNotNull;
    }

    /**
     * @return bool
     */
    public function isNotNull(): bool
    {
        return $this->notNull || $this->isPrimaryKey();
    }

    /**
     * Sets the default value for this column.
     *
     * @param scalar|null $defaultValue the default value for the column
     *
     * @return void
     */
    public function setDefaultValue($defaultValue): void
    {
        $this->defaultValue = $defaultValue;
    }

    /**
     * Gets the default value for this column.
     *
     * @return scalar|null
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * Set the foreign key for this column.
     *
     * @param string $tableName The name of the table that is foreign.
     * @param string $columnName The name of the column that is foreign.
     *
     * @return void
     */
    public function setForeignKey(string $tableName, string $columnName): void
    {
        if ($tableName && $columnName) {
            $this->relatedTableName = $tableName;
            $this->relatedColumnName = $columnName;
        } else {
            // @TODO to remove because it seems already done by default!
            $this->relatedTableName = '';
            $this->relatedColumnName = '';
        }
    }

    /**
     * Is this column a foreign key?
     *
     * @return bool True if column is a foreign key.
     */
    public function isForeignKey(): bool
    {
        return (bool)$this->relatedTableName;
    }

    /**
     * Get the RelationMap object for this foreign key
     *
     * @return \Propel\Runtime\Map\RelationMap|null
     */
    public function getRelation(): ?RelationMap
    {
        if (!$this->relatedTableName) {
            return null;
        }

        $nameFq = $this->getFullyQualifiedName();
        foreach ($this->getTableMap()->getRelations() as $relation) {
            if (
                $relation->getType() === RelationMap::MANY_TO_ONE &&
                $relation->getForeignTable()->getName() === $this->getRelatedTableName() &&
                array_key_exists($nameFq, $relation->getColumnMappings())
            ) {
                return $relation;
            }
        }

        return null;
    }

    /**
     * Get the table.column that this column is related to.
     *
     * @return string A String with the full name for the related column.
     */
    public function getRelatedName(): string
    {
        return $this->relatedTableName . '.' . $this->relatedColumnName;
    }

    /**
     * Get the table name that this column is related to.
     *
     * @return string A String with the name for the related table.
     */
    public function getRelatedTableName(): string
    {
        return $this->relatedTableName;
    }

    /**
     * Get the column name that this column is related to.
     *
     * @return string A String with the name for the related column.
     */
    public function getRelatedColumnName(): string
    {
        return $this->relatedColumnName;
    }

    /**
     * Get the TableMap object that this column is related to.
     *
     * @throws \Propel\Runtime\Map\Exception\ForeignKeyNotFoundException when called on a column with no foreign key
     *
     * @return \Propel\Runtime\Map\TableMap The related TableMap object
     */
    public function getRelatedTable(): TableMap
    {
        if (!$this->relatedTableName) {
            throw new ForeignKeyNotFoundException(sprintf('Cannot fetch RelatedTable for column with no foreign key: %s.', $this->columnName));
        }

        return $this->table->getDatabaseMap()->getTable($this->relatedTableName);
    }

    /**
     * Get the TableMap object that this column is related to.
     *
     * @return self The related ColumnMap object
     */
    public function getRelatedColumn(): self
    {
        return $this->getRelatedTable()->getColumn($this->relatedColumnName);
    }

    /**
     * Set the valueSet of this column (only valid for ENUM and SET columns).
     *
     * @param array $values A list of allowed values
     *
     * @return void
     */
    public function setValueSet(array $values): void
    {
        $this->valueSet = $values;
    }

    /**
     * Get the valueSet of this column (only valid for ENUM and SET columns).
     *
     * @return array A list of allowed values
     */
    public function getValueSet(): array
    {
        return $this->valueSet;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public function isInValueSet($value): bool
    {
        return in_array($value, $this->valueSet);
    }

    /**
     * @param mixed $value
     *
     * @return string|int|false
     */
    public function getValueSetKey($value)
    {
        return array_search($value, $this->valueSet);
    }

    /**
     * Performs DB-specific ignore case, but only if the column type necessitates it.
     *
     * @param string $str The expression we want to apply the ignore case formatting to (e.g. the column name).
     * @param \Propel\Runtime\Adapter\AdapterInterface $db
     *
     * @return string
     */
    public function ignoreCase(string $str, AdapterInterface $db): string
    {
        if ($this->isText()) {
            /** @phpstan-var \Propel\Runtime\Adapter\SqlAdapterInterface $db */
            return $db->ignoreCase($str);
        }

        return $str;
    }

    /**
     * Normalizes the column name, removing table prefix and uppercasing.
     *
     * article.first_name becomes FIRST_NAME
     *
     * @param string $name
     *
     * @return string Normalized column name.
     */
    public static function normalizeName(string $name): string
    {
        $pos = strrpos($name, '.');
        if ($pos !== false) {
            $name = substr($name, $pos + 1);
            $name = trim($name, " \t\n\r\0\x0B`'()\"[]~!-{}%^&.");
        }

        return strtoupper($name);
    }

    /**
     * Set this column to be a primaryString column.
     *
     * @param bool $pkString
     *
     * @return void
     */
    public function setPrimaryString(bool $pkString): void
    {
        $this->isPkString = $pkString;
    }

    /**
     * Is this column a primaryString column?
     *
     * @return bool True, if this column is the primaryString column.
     */
    public function isPrimaryString(): bool
    {
        return $this->isPkString;
    }
}
