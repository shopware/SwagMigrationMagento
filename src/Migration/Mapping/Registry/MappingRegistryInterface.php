<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Migration\Mapping\Registry;

interface MappingRegistryInterface
{
    public static function get(string $identifier): ?array;
}
