<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\Profile;

use Swag\MigrationMagento\Profile\Magento\MagentoProfileInterface;

abstract class Magento2Profile implements MagentoProfileInterface
{
    public const SOURCE_SYSTEM_NAME = 'Magento';

    public const PROFILE_NAME = 'magento';

    public const SOURCE_SYSTEM_VERSION = 'X';

    public const AUTHOR_NAME = 'shopware AG';

    public const ICON_PATH = '/swagmigrationassistant/static/img/magento-profile-icon.svg';

    public function getName(): string
    {
        return static::PROFILE_NAME;
    }

    public function getVersion(): string
    {
        return static::SOURCE_SYSTEM_VERSION;
    }

    public function getSourceSystemName(): string
    {
        return self::SOURCE_SYSTEM_NAME;
    }

    public function getAuthorName(): string
    {
        return self::AUTHOR_NAME;
    }

    public function getIconPath(): string
    {
        return self::ICON_PATH;
    }
}
