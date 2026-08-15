<?php

declare(strict_types = 1);

namespace Propel\Generator\Model\Datatype;

use Error;
use InvalidArgumentException;
use PDO;
use Propel\Generator\Model\TypeMapping;
use RuntimeException;
use function array_map;
use function constant;
use function implode;
use function in_array;
use function sort;
use function strtoupper;

enum ColumnType
{
    case CHAR;
    case VARCHAR;
    case LONGVARCHAR;
    case CLOB;
    case CLOB_EMU;
    case NUMERIC;
    case DECIMAL;
    case TINYINT;
    case SMALLINT;
    case INTEGER;
    case BIGINT;
    case REAL;
    case FLOAT;
    case DOUBLE;
    case BINARY;
    case VARBINARY;
    case LONGVARBINARY;
    case BLOB;
    case DATE;
    case DATETIME;
    case TIME;
    case TIMESTAMP;
    case BU_DATE;
    case BU_TIMESTAMP;
    case BOOLEAN;
    case BOOLEAN_EMU;
    case OBJECT;
    case ARRAY;
    case GEOMETRY;
    case JSON;
    case UUID;
    case UUID_BINARY;

    /**
     * Alias ENUM type (legacy behavior uses ENUM_BINARY, expected behavior should be ENUM_NATIVE where available)
     */
    case ENUM;

    /**
     * Alias SET type (legacy behavior uses SET_BINARY, expected behavior should be SET_NATIVE where available)
     */
    case SET;

    /**
     * Simulated ENUM type based on bit representation.
     */
    case ENUM_BINARY;

    /**
     * Simulated SET type based on bit representation.
     */
    case SET_BINARY;

    /**
     * ENUM type using native DB type (if available).
     */
    case ENUM_NATIVE;

    /**
     * SET type using native DB type (if available).
     */
    case SET_NATIVE;

    /**
     * @param string $literal
     *
     * @throws \RuntimeException
     *
     * @return self
     */
    public static function fromLiteral(string $literal): self
    {
        $literal = strtoupper($literal);

        try {
            return constant("self::$literal"); // no ColumnType::{$literal} before php 8.3
        } catch (Error $e) {
            $caseNames = array_map(fn ($c) => $c->name, self::cases());
            sort($caseNames);
            $message = "Invalid column type: `$literal`. Available types are [" . implode(', ', $caseNames) . ']';

            throw new RuntimeException($message);
        }
    }

    /**
     * @return array<\Propel\Generator\Model\TypeMapping>
     */
    public static function buildDefaultTypeMapping(): array
    {
        /** @var array<\Propel\Generator\Model\TypeMapping> $map */
        $map = [];
        $specialTypes = [self::CLOB_EMU, self::GEOMETRY, self::ENUM, self::SET, self::BU_DATE, self::BU_TIMESTAMP];
        foreach (self::cases() as $type) {
            if (in_array($type, $specialTypes)) {
                continue;
            }

            $map[$type->name] = new TypeMapping($type);
        }

        $map[self::ENUM->name] = $map[self::ENUM_BINARY->name];
        $map[self::SET->name] = $map[self::SET_BINARY->name];
        $map[self::BU_DATE->name] = $map[self::DATE->name];
        $map[self::BU_TIMESTAMP->name] = $map[self::TIMESTAMP->name];
        $map[self::BOOLEAN->name]->setSqlType('INTEGER');

        return $map;
    }

    /**
     * @return string
     */
    public function toPhpTypeName(): string
    {
        return match ($this) {
            self::TINYINT,
            self::SMALLINT,
            self::INTEGER,
            self::ENUM_BINARY,
            self::SET_BINARY
            => 'int',
            self::REAL,
            self::FLOAT,
            self::DOUBLE
            => 'float',
            self::BLOB,
            self::CLOB_EMU
            => 'resource',
            self::BOOLEAN,
            self::BOOLEAN_EMU
            => 'bool',
            self::OBJECT => '', // sic
            self::ARRAY => 'array',
            self::GEOMETRY => 'GEOMETRY', // ??
            default => 'string'
        };
    }

    /**
     * Returns the PDO type (PDO::PARAM_* constant) value.
     *
     * @return int
     */
    public function toPdoType(): int
    {
        return match ($this) {
            self::TINYINT,
            self::SMALLINT,
            self::INTEGER,
            self::BIGINT,
            self::BOOLEAN_EMU,
            self::ENUM_BINARY,
            self::SET_BINARY
            => PDO::PARAM_INT,
            self::VARBINARY,
            self::LONGVARBINARY,
            self::BLOB,
            self::OBJECT,
            self::GEOMETRY,
            self::UUID_BINARY
            => PDO::PARAM_LOB,
            self::BOOLEAN
            => PDO::PARAM_BOOL,
            default => PDO::PARAM_STR
        };
    }

    /**
     * @param int $pdoType
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    public static function resolvePdoConstantName(int $pdoType): string
    {
        return match ($pdoType) {
            PDO::PARAM_BOOL => 'PDO::PARAM_BOOL',
            PDO::PARAM_INT => 'PDO::PARAM_INT',
            PDO::PARAM_LOB => 'PDO::PARAM_LOB',
            PDO::PARAM_NULL => 'PDO::PARAM_NULL',
            PDO::PARAM_STR => 'PDO::PARAM_STR',
            default => throw new InvalidArgumentException("Cannot resolve PDO type for constant `$pdoType`")
        };
    }

    /**
     * @return string
     */
    public function toPdoConstantName(): string
    {
        return self::resolvePdoConstantName($this->toPdoType());
    }

    /**
     * @return bool
     */
    public function isTemporalType(): bool
    {
        return in_array($this, [
            self::DATE,
            self::DATETIME,
            self::TIME,
            self::TIMESTAMP,
            self::BU_DATE,
            self::BU_TIMESTAMP,
        ], true);
    }

    /**
     * @return bool
     */
    public function isTextType(): bool
    {
        return in_array($this, [
            self::CHAR,
            self::VARCHAR,
            self::LONGVARCHAR,
            self::CLOB,
            self::DATE,
            self::DATETIME,
            self::TIME,
            self::TIMESTAMP,
            self::BU_DATE,
            self::BU_TIMESTAMP,
            self::JSON,
            self::ENUM_NATIVE,
        ], true);
    }

    /**
     * @return bool
     */
    public function isNumericType(): bool
    {
        return in_array($this, [
            self::SMALLINT,
            self::TINYINT,
            self::INTEGER,
            self::BIGINT,
            self::FLOAT,
            self::DOUBLE,
            self::NUMERIC,
            self::DECIMAL,
            self::REAL,
        ], true);
    }

    /**
     * @return bool
     */
    public function isBooleanType(): bool
    {
        return in_array($this, [self::BOOLEAN, self::BOOLEAN_EMU], true);
    }

    /**
     * @return bool
     */
    public function isLobType(): bool
    {
        return in_array($this, [self::VARBINARY, self::LONGVARBINARY, self::BLOB, self::OBJECT, self::GEOMETRY], true);
    }

    /**
     * @return bool
     */
    public function isUuidType(): bool
    {
        return in_array($this, [self::UUID, self::UUID_BINARY], true);
    }

    /**
     * @return bool
     */
    public function isPhpArrayType(): bool
    {
        return $this === self::ARRAY;
    }
}
