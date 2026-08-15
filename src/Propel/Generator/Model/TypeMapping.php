<?php

declare(strict_types = 1);

namespace Propel\Generator\Model;

use LogicException;
use Propel\Generator\Exception\EngineException;
use Propel\Generator\Model\Datatype\ColumnType;
use function sprintf;
use function strtoupper;

/**
 * Type mapping for a column
 */
class TypeMapping extends MappingModel
{
    private string|null $name = null;

    private string|null $description = null;

    private int|null $size = null;

    private int|null $scale = null;

    private ColumnType|null $mappingType = null;

    private string|null $sqlType;

    private ColumnDefaultValue|null $defaultValue = null;

    private Database|null $database = null;

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType|null $type Propel type.
     * @param string|null $sqlType SQL type.
     * @param int|null $size
     * @param int|null $scale
     */
    public function __construct(ColumnType|null $type = null, ?string $sqlType = null, ?int $size = null, ?int $scale = null)
    {
        if ($type !== null) {
            $this->setMappingType($type);
        }

        if ($size !== null) {
            $this->setSize($size);
        }

        if ($scale !== null) {
            $this->setScale($scale);
        }

        $this->setSqlType($sqlType ?? $type?->name);
    }

    /**
     * Copies the values from current object into passed-in mapping.
     *
     * @param \Propel\Generator\Model\TypeMapping $mapping Mapping to copy values into.
     *
     * @return void
     */
    public function copy(TypeMapping $mapping): void
    {
        $this->defaultValue = $mapping->getDefaultValue();
        $this->description = $mapping->getDescription();
        $this->name = $mapping->getName();
        $this->scale = $mapping->getScale();
        $this->size = $mapping->getSize();
        $this->sqlType = $mapping->getSqlType();
        $this->mappingType = $mapping->getMappingType();
    }

    /**
     * @return void
     */
    #[\Override]
    protected function setupObject(): void
    {
        $type = $this->getAttribute('type');
        if ($type) {
            $type = strtoupper($type);
            $mappingType = ColumnType::fromLiteral($type);

            $this->copy($this->database->getPlatform()->getColumnTypeMapping($mappingType));
        }

        $this->name = $this->getAttribute('name');

        // Default value
        $defval = $this->getAttribute('defaultValue', $this->getAttribute('default'));
        if ($defval !== null) {
            $this->setDefaultValue(new ColumnDefaultValue($defval, ColumnDefaultValue::TYPE_VALUE));
        } elseif ($this->getAttribute('defaultExpr') !== null) {
            $this->setDefaultValue(new ColumnDefaultValue($this->getAttribute('defaultExpr'), ColumnDefaultValue::TYPE_EXPR));
        }

        $this->size = $this->getAttribute('size') ? (int)$this->getAttribute('size') : null;
        $this->scale = $this->getAttribute('scale') ? (int)$this->getAttribute('scale') : null;
        $this->description = $this->getAttribute('description');
    }

    /**
     * Sets the owning database object (if setup via XML).
     *
     * @param \Propel\Generator\Model\Database $database
     *
     * @return void
     */
    public function setDatabase(Database $database): void
    {
        $this->database = $database;
    }

    /**
     * Returns the owning database object (if setup via XML).
     *
     * @return \Propel\Generator\Model\Database|null
     */
    public function getDatabase(): ?Database
    {
        return $this->database;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     *
     * @return void
     */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     *
     * @return void
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return int|null
     */
    public function getScale(): ?int
    {
        return $this->scale;
    }

    /**
     * @param int|null $scale
     *
     * @return void
     */
    public function setScale(?int $scale): void
    {
        $this->scale = $scale;
    }

    /**
     * Replaces the size if the new value is not null.
     *
     * @param int|null $scale
     *
     * @return void
     */
    public function replaceScale(?int $scale): void
    {
        if ($scale !== null) {
            $this->scale = $scale;
        }
    }

    /**
     * @return int|null
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * @param int|null $size
     *
     * @return void
     */
    public function setSize(?int $size): void
    {
        $this->size = $size;
    }

    /**
     * Replaces the size if the new value is not null.
     *
     * @param int|null $size
     *
     * @return void
     */
    public function replaceSize(?int $size): void
    {
        if ($size !== null) {
            $this->size = $size;
        }
    }

    /**
     * @throws \LogicException
     *
     * @return \Propel\Generator\Model\Datatype\ColumnType
     */
    public function getMappingType(): ColumnType
    {
        if (!$this->mappingType) {
            throw new LogicException('Mapping type not set');
        }

        return $this->mappingType;
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType|null $mappingType
     *
     * @return void
     */
    public function setMappingType(?ColumnType $mappingType): void
    {
        $this->mappingType = $mappingType;
    }

    /**
     * Replaces the mapping type if the new value is not null.
     *
     * @param \Propel\Generator\Model\Datatype\ColumnType|null $mappingType
     *
     * @return void
     */
    public function replaceType(?ColumnType $mappingType): void
    {
        if ($mappingType !== null) {
            $this->mappingType = $mappingType;
        }
    }

    /**
     * @return \Propel\Generator\Model\ColumnDefaultValue|null
     */
    public function getDefaultValue(): ?ColumnDefaultValue
    {
        return $this->defaultValue;
    }

    /**
     * Returns the default value, type-casted for use in PHP OM.
     *
     * @throws \Propel\Generator\Exception\EngineException
     *
     * @return array|string|int|bool|null
     */
    public function getPhpDefaultValue()
    {
        if ($this->defaultValue === null) {
            return null;
        }

        if ($this->defaultValue->isExpression()) {
            throw new EngineException('Cannot get PHP version of default value for default value EXPRESSION.');
        }

        $value = $this->defaultValue->getValue();

        return match ($this->mappingType) {
            ColumnType::BOOLEAN,
            ColumnType::BOOLEAN_EMU => $this->booleanValue($value),
            ColumnType::ARRAY => $this->buildDefaultValueExpressionForArray((string)$value),
            ColumnType::SET_BINARY => $this->buildDefaultValueExpressionForSet((string)$value),
            default => $value
        };
    }

    /**
     * Sets the default value.
     *
     * @param \Propel\Generator\Model\ColumnDefaultValue $value
     *
     * @return void
     */
    public function setDefaultValue(ColumnDefaultValue $value): void
    {
        $this->defaultValue = $value;
    }

    /**
     * Put a default value on the column
     *
     * @param string|int $value
     * @param bool $isExpression
     *
     * @return void
     */
    public function createDefaultValue(string|int|null $value, bool $isExpression = false): void
    {
        $type = $isExpression ? ColumnDefaultValue::TYPE_EXPR : ColumnDefaultValue::TYPE_VALUE;
        $this->defaultValue = new ColumnDefaultValue($value, $type);
    }

    /**
     * Replaces the default value if the new value is not null.
     *
     * @param \Propel\Generator\Model\ColumnDefaultValue|null $value
     *
     * @return void
     */
    public function replaceDefaultValue(?ColumnDefaultValue $value = null): void
    {
        if ($value !== null) {
            $this->defaultValue = $value;
        }
    }

    /**
     * Returns the SQL type.
     *
     * @return string|null
     */
    public function getSqlType(): ?string
    {
        return $this->sqlType;
    }

    /**
     * Sets the SQL type.
     *
     * @param string|null $sqlType
     *
     * @return void
     */
    public function setSqlType(?string $sqlType): void
    {
        $this->sqlType = $sqlType;
    }

    /**
     * Replaces the SQL type if the new value is not null.
     *
     * @param string|null $sqlType
     *
     * @return void
     */
    public function replaceSqlType(?string $sqlType): void
    {
        if ($sqlType !== null) {
            $this->sqlType = $sqlType;
        }
    }

    /**
     * Returns the size and scale in brackets for use in an sql schema.
     *
     * @return string
     */
    public function getSizeDefinition(): string
    {
        if ($this->size === null) {
            return '';
        }

        if ($this->scale !== null) {
            return sprintf('(%u,%u)', $this->size, $this->scale);
        }

        return sprintf('(%u)', $this->size);
    }

    /**
     * @return void
     */
    public function __clone()
    {
        if ($this->defaultValue) {
            $this->defaultValue = clone $this->defaultValue;
        }
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return static
     */
    public function cloneAs(ColumnType $type): static
    {
        $clonedMapping = clone $this;
        $clonedMapping->setMappingType($type);

        return $clonedMapping;
    }
}
