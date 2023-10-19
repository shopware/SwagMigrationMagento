<?php declare(strict_types=1);

/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\DevOps\StaticAnalyze\StaticAnalyzeKernel;
use Swag\MigrationMagento\SwagMigrationMagento;
use SwagMigrationAssistant\SwagMigrationAssistant;
use Symfony\Component\Dotenv\Dotenv;

$projectRoot = dirname(__DIR__, 4);
$pluginRootPath = dirname(__DIR__);

$classLoader = require $projectRoot . '/vendor/autoload.php';
if (file_exists($projectRoot . '/.env')) {
    (new Dotenv())->usePutEnv()->load($projectRoot . '/.env');
}


$magentoPluginRootPath = dirname(__DIR__);
$magentoComposerJson = json_decode((string) file_get_contents($magentoPluginRootPath . '/composer.json'), true);
$assistantPluginRootPath = $magentoPluginRootPath . '/../SwagMigrationAssistant';
$assistantComposerJson = json_decode((string) file_get_contents($assistantPluginRootPath . '/composer.json'), true);

$swagAssistant = [
    'autoload' => $assistantComposerJson['autoload'],
    'baseClass' => SwagMigrationAssistant::class,
    'managedByComposer' => false,
    'name' => 'SwagMigrationAssistant',
    'version' => $assistantComposerJson['version'],
    'active' => true,
    'path' => $assistantPluginRootPath,
];
$swagMagento = [
    'autoload' => $magentoComposerJson['autoload'],
    'baseClass' => SwagMigrationMagento::class,
    'managedByComposer' => false,
    'name' => 'SwagMigrationMagento',
    'version' => $magentoComposerJson['version'],
    'active' => true,
    'path' => $magentoPluginRootPath,
];
$pluginLoader = new StaticKernelPluginLoader($classLoader, null, [$swagAssistant, $swagMagento]);

$kernel = new StaticAnalyzeKernel('dev', true, $pluginLoader, 'phpstan-test-cache-id');
$kernel->boot();

$configurationOption = getopt('c:', ['configuration:']);
$configFile = 'phpstan.neon.dist';
if (isset($configurationOption['configuration'])) {
    $configFile = $configurationOption['configuration'];
}

$phpStanConfigDist = file_get_contents($pluginRootPath . '/' . $configFile);
if ($phpStanConfigDist === false) {
    throw new RuntimeException('phpstan.neon.dist file not found');
}

// because the cache dir is hashed by Shopware, we need to set the PHPStan config dynamically
$phpStanConfig = str_replace(
    [
        '%ShopwareHashedCacheDir%',
        '%ShopwareRoot%',
        '%ShopwareKernelClass%',
    ],
    [
        str_replace($kernel->getProjectDir(), '', $kernel->getCacheDir()),
        $projectRoot . (is_dir($projectRoot . '/platform') ? '/platform' : ''),
        str_replace('\\', '_', get_class($kernel)),
    ],
    $phpStanConfigDist
);

file_put_contents(__DIR__ . '/../phpstan.neon', $phpStanConfig);
