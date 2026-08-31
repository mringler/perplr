<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes;

use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Datatype\ColumnType;

class ColumnCodeProducerFactory
{
    /**
     * @param \Propel\Generator\Model\Column $column
     * @param \Propel\Generator\Builder\Om\ObjectBuilder $builder
     *
     * @return \Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes\ColumnCodeProducer
     */
    public static function create(Column $column, ObjectBuilder $builder): ColumnCodeProducer
    {
        $producer = $column->isLobType() && $column->getColumnType() !== ColumnType::OBJECT
            ? new LobColumnCodeProducer($column, $builder)
            : match ($column->getColumnType()) {
                ColumnType::DATE,
                ColumnType::DATETIME,
                ColumnType::TIME,
                ColumnType::TIMESTAMP => new TemporalColumnCodeProducer($column, $builder),
                ColumnType::OBJECT => new ObjectColumnCodeProducer($column, $builder),
                ColumnType::ARRAY => new ArrayColumnCodeProducer($column, $builder),
                ColumnType::JSON => new JsonColumnCodeProducer($column, $builder),
                ColumnType::ENUM_BINARY => new EnumBinaryColumnCodeProducer($column, $builder),
                ColumnType::SET_BINARY => new SetBinaryColumnCodeProducer($column, $builder),
                ColumnType::SET_NATIVE => new SetNativeColumnCodeProducer($column, $builder),
                ColumnType::BOOLEAN,
                ColumnType::BOOLEAN_EMU => new BoolColumnCodeProducer($column, $builder),
                default => new ColumnCodeProducer($column, $builder),
            };

        return $column->isLazyLoad()
            ? new LazyLoadColumnCodeProducer($producer)
            : $producer;
    }
}
