<?php

declare(strict_types = 1);

namespace Propel\Generator\Model\Datatype;

use BackedEnum;
use UnitEnum;
use function in_array;
use function is_subclass_of;

class PhpDatatype
{
 /**
  * Returns whether a passed-in PHP type is a primitive type.
  *
  * @param string $phpType
  *
  * @return bool
  */
    public static function isPhpPrimitiveType(string $phpType): bool
    {
        return in_array($phpType, ['bool', 'boolean', 'int', 'double', 'float', 'string'], true);
    }

    /**
     * Returns whether a passed-in PHP type is a primitive numeric type.
     *
     * @param string $phpType
     *
     * @return bool
     */
    public static function isPhpPrimitiveNumericType(string $phpType): bool
    {
        return in_array($phpType, ['bool', 'boolean', 'int', 'double', 'float'], true);
    }

    /**
     * Returns whether a passed-in PHP type is an object.
     *
     * @param string $phpType
     *
     * @return bool
     */
    public static function isPhpObjectType(string $phpType): bool
    {
        return !self::isPhpPrimitiveType($phpType) && !in_array($phpType, ['resource', 'array'], true);
    }

    /**
     * Returns whether a passed-in PHP type is a backed enum.
     *
     * @param string $phpType
     *
     * @return bool
     */
    public static function isPhpBackedEnumType(string $phpType): bool
    {
        return is_subclass_of($phpType, BackedEnum::class);
    }

    /**
     * Convenience method to indicate whether a passed-in PHP type is a UnitEnum (non-backed).
     *
     * @param string $phpType
     *
     * @return bool
     */
    public static function isPhpUnitEnumType(string $phpType): bool
    {
        return is_subclass_of($phpType, UnitEnum::class) && !is_subclass_of($phpType, BackedEnum::class);
    }
}
