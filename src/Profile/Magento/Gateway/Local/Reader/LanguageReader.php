<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Magento19LocalGateway;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class LanguageReader extends AbstractReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::LANGUAGE;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        return $this->fetchLocales();
    }

    protected function fetchLocales(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_store', 'store');
        $query->leftJoin(
            'store',
            $this->tablePrefix . 'core_config_data',
            'localeconfig',
            'localeconfig.scope = \'stores\' AND localeconfig.path = \'general/locale/code\' AND store.store_id = localeconfig.scope_id'
        );
        $query->innerJoin(
            'store',
            $this->tablePrefix . 'core_config_data',
            'defaultlocale',
            'defaultlocale.scope = \'default\' AND defaultlocale.path = \'general/locale/code\''
        );
        $query->addSelect('store.store_id');
        $query->addSelect('localeconfig.value as locale');
        $query->addSelect('defaultlocale.value as defaultLocale');

        $configurations = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        $storeConfigs = [];
        foreach ($configurations as $storeConfig) {
            if ($storeConfig['locale'] === null) {
                $storeConfig['locale'] = $storeConfig['defaultLocale'];
            }
            if (isset($storeConfigs[$storeConfig['locale']])) {
                $storeConfigs[$storeConfig['locale']]['stores'][] = $storeConfig['store_id'];

                continue;
            }
            $storeConfigs[$storeConfig['locale']] = [
                'locale' => str_replace('_', '-', $storeConfig['locale']),
                'stores' => [$storeConfig['store_id']],
            ];
        }

        return array_values($storeConfigs);
    }
}
