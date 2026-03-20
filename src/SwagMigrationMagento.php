<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

#[Package('fundamentals@after-sales')]
class SwagMigrationMagento extends Plugin
{
    final public const DEPENDENCY_LOCATION = __DIR__ . '/DependencyInjection/';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $locator = new FileLocator(self::DEPENDENCY_LOCATION);

        $phpLoader = new PhpFileLoader($container, $locator);
        $phpLoader->load('magento.php');
        $phpLoader->load('magento20.php');
        $phpLoader->load('magento21.php');
        $phpLoader->load('magento22.php');
        $phpLoader->load('magento23.php');
        $phpLoader->load('magento24.php');
    }

    public function rebuildContainer(): bool
    {
        return false;
    }
}
