<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento;

use SwagMigrationAssistant\Migration\Profile\ProfileInterface;

class Magento19Profile implements ProfileInterface
{
    public const PROFILE_NAME = 'magento19';

    public const SOURCE_SYSTEM_NAME = 'Magento';

    public const SOURCE_SYSTEM_VERSION = '1.9';

    public const AUTHOR_NAME = 'shopware AG';

    public function getName(): string
    {
        return self::PROFILE_NAME;
    }

    public function getSourceSystemName(): string
    {
        return self::SOURCE_SYSTEM_NAME;
    }

    public function getVersion(): string
    {
        return self::SOURCE_SYSTEM_VERSION;
    }

    public function getAuthorName(): string
    {
        return self::AUTHOR_NAME;
    }
}
