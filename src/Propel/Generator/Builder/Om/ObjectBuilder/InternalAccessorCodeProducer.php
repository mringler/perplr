<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder;

use Propel\Generator\Model\Column;
use Propel\Generator\Model\PropelTypes;

class InternalAccessorCodeProducer extends ObjectCodeProducer
{
    /**
     * Build match options for column fields.
     *
     * Keys will be field names as used in schema.xml (like `my_field`).
     *
     * @param callable(\Propel\Generator\Model\Column): string $getStatementFromColumn
     * @param string $indent
     *
     * @return void
     */
    public function buildFieldNameMatchStatement(callable $getStatementFromColumn, string $indent): string
    {
        $matchers = '';
        foreach ($this->getTable()->getColumns() as $column) {
            $columnFieldName = $column->getName();
            $statement = $getStatementFromColumn($column);
            $matchers .= "\n{$indent}'{$columnFieldName}': $statement,";
        }

        return $matchers;
    }
    
    /**
     * Get the statement how a column value is accessed in the script.
     *
     * Note that this is not necessarily just the getter. If the value is
     * stored on the model in an encoded format, the statement returned by
     * this method includes the statement to decode the value.
     *
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string
     */
    public function getAccessValueStatement(Column $column): string
    {
        $columnName = $column->getLowercasedName();

        if ($column->isUuidBinaryType()) {
            $uuidSwapFlag = $this->objectBuilder->getUuidSwapFlagLiteral();

            return "UuidConverter::uuidToBin(\$this->$columnName, $uuidSwapFlag)";
        }

        return "\$this->$columnName";
    }


    /**
     * @param string $script
     * @return void
     */
    public function addInternalAccessors(string &$script): void
    {
        $this->addGetFieldValueByFieldName($script);
        $this->addGetPdoTypeByFieldName($script);
    }

    /**
     * @param string $script
     * @return void
     */
    protected function addGetFieldValueByFieldName(string &$script): void
    {
        /** @var \Closure(Column): string $getAccessValueStatement */
        $getAccessValueStatement = [$this, 'getAccessValueStatement'];
        $indent = '            ';
        $matchersCode = $this->buildFieldNameMatchStatement($getAccessValueStatement, $indent);

        $script .= "
    /*
     * @param string \$fieldName Column name as used in schema, i.e. my_field
     *
     * @throws \RuntimeException If field name does not match.
     *
     * @return mixed
     */
    protected final getFieldValueByFieldName(string \$fieldName)
    {
        return match (\$fieldName){{$matchersCode}
            default: throw new RuntimeException(\"Unknown column field name: '\$fieldname'\")
        }
    }\n";

    }

    /**
     * @param string $script
     * @return void
     */
    protected function addGetPdoTypeByFieldName(string &$script): void
    {
        $getPdoTypeString = fn (Column $column) => PropelTypes::getPdoTypeString($column->getType());
        $indent = '            ';
        $matchersCode = $this->buildFieldNameMatchStatement($getPdoTypeString, $indent);

        $script .= "
    /*
     * @param string \$fieldName Column name as used in schema, i.e. my_field
     *
     * @throws \RuntimeException If field name does not match.
     *
     * @return int
     */
    protected final getPdoTypeByFieldName(string \$fieldName): int
    {
        return match (\$fieldName){{$matchersCode}
            default: throw new RuntimeException(\"Unknown column field name: '\$fieldname'\")
        }
    }\n";
    }
}
