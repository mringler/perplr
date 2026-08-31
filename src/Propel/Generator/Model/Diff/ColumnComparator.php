<?php

declare(strict_types = 1);

namespace Propel\Generator\Model\Diff;

use Propel\Generator\Model\Column;
use function strtoupper;

/**
 * Service class for comparing Column objects.
 * Heavily inspired by Doctrine2's Migrations
 * (see http://github.com/doctrine/dbal/tree/master/lib/Doctrine/DBAL/Schema/)
 */
class ColumnComparator
{
    /**
     * Compute and return the difference between two column objects
     *
     * @param \Propel\Generator\Model\Column $fromColumn
     * @param \Propel\Generator\Model\Column $toColumn
     *
     * @return \Propel\Generator\Model\Diff\ColumnDiff|false return false if the two columns are similar
     */
    public static function computeDiff(Column $fromColumn, Column $toColumn)
    {
        $changedProperties = self::compareColumns($fromColumn, $toColumn);
        if (!$changedProperties) {
            return false;
        }

        $platform = $fromColumn->getPlatform() ?: $toColumn->getPlatform();
        if ($platform && $platform->getColumnDDL($fromColumn) === $platform->getColumnDDL($toColumn)) {
            return false;
        }
        $columnDiff = new ColumnDiff($fromColumn, $toColumn);
        $columnDiff->setChangedProperties($changedProperties);

        return $columnDiff;
    }

    /**
     * @param \Propel\Generator\Model\Column $fromColumn
     * @param \Propel\Generator\Model\Column $toColumn
     *
     * @return array
     */
    public static function compareColumns(Column $fromColumn, Column $toColumn): array
    {
        $changedProperties = [];

        // compare column types
        $fromType = $fromColumn->getTypeMapping();
        $toType = $toColumn->getTypeMapping();

        if ($fromType->getScale() !== $toType->getScale()) {
            $changedProperties['scale'] = [$fromType->getScale(), $toType->getScale()];
        }
        if ($fromType->getSize() !== $toType->getSize()) {
            $changedProperties['size'] = [$fromType->getSize(), $toType->getSize()];
        }

        $fromSqlType = $fromType->getSqlType() === null ?: strtoupper($fromType->getSqlType());
        $toSqlType = $toType->getSqlType() === null ?: strtoupper($toType->getSqlType());

        if ($fromSqlType !== $toSqlType) {
            $changedProperties['sqlType'] = [$fromType->getSqlType(), $toType->getSqlType()];

            if ($fromType->getColumnType() !== $toType->getColumnType()) {
                $changedProperties['type'] = [$fromType->getColumnType(), $toType->getColumnType()];
            }
        }

        if ($fromColumn->isNotNull() !== $toColumn->isNotNull()) {
            $changedProperties['notNull'] = [$fromColumn->isNotNull(), $toColumn->isNotNull()];
        }

        // compare column default value
        $fromDefaultValue = $fromColumn->getDefaultValue();
        $toDefaultValue = $toColumn->getDefaultValue();
        if ($fromDefaultValue && !$toDefaultValue) {
            $changedProperties['defaultValueType'] = [$fromDefaultValue->getType(), null];
            $changedProperties['defaultValueValue'] = [$fromDefaultValue->getValue(), null];
        } elseif (!$fromDefaultValue && $toDefaultValue) {
            $changedProperties['defaultValueType'] = [null, $toDefaultValue->getType()];
            $changedProperties['defaultValueValue'] = [null, $toDefaultValue->getValue()];
        } elseif ($fromDefaultValue) {
            if (!$fromDefaultValue->equals($toDefaultValue)) {
                if ($fromDefaultValue->getType() !== $toDefaultValue->getType()) {
                    $changedProperties['defaultValueType'] = [$fromDefaultValue->getType(), $toDefaultValue->getType()];
                }
                if ($fromDefaultValue->getValue() !== $toDefaultValue->getValue()) {
                    $changedProperties['defaultValueValue'] = [$fromDefaultValue->getValue(), $toDefaultValue->getValue()];
                }
            }
        }

        if ($fromColumn->isAutoIncrement() !== $toColumn->isAutoIncrement()) {
            $changedProperties['autoIncrement'] = [$fromColumn->isAutoIncrement(), $toColumn->isAutoIncrement()];
        }

        return $changedProperties;
    }
}
