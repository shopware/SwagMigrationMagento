<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Migration\Mapping\Registry;

class AbstractMappingRegistry implements MappingRegistryInterface
{
    protected static $mapping = [];

    public static function get(string $identifier): ?array
    {
        if (!isset(static::$mapping[$identifier])) {
            return null;
        }

        return static::$mapping[$identifier];
    }
}
