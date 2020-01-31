<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ManufacturerDataSet;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class ManufacturerConverter extends MagentoConverter
{
    /**
     * @var string
     */
    protected $runId;

    /**
     * @var string
     */
    protected $oldIdentifier;

    /**
     * @var string
     */
    protected $connectionId;

    /**
     * @var Context
     */
    protected $context;

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Magento19Profile::PROFILE_NAME
            && $migrationContext->getDataSet()::getEntity() === ManufacturerDataSet::getEntity();
    }

    public function getSourceIdentifier(array $data): string
    {
        return $data['option_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $this->runId = $migrationContext->getRunUuid();
        $this->migrationContext = $migrationContext;
        $this->oldIdentifier = $data['option_id'];
        $this->connectionId = $migrationContext->getConnection()->getId();
        $this->context = $context;

        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PRODUCT_MANUFACTURER,
            $this->oldIdentifier,
            $this->context,
            $this->checksum
        );
        $converted['id'] = $this->mainMapping['entityUuid'];
        unset($data['option_id']);

        $this->convertValue($converted, 'name', $data, 'value');

        if (isset($data['translations'])) {
            $converted['translations'] = $this->getTranslations(
                $data['translations'],
                [
                    'name' => 'name',
                ],
                $context
            );

            if (isset($converted['translations'])) {
                foreach ($converted['translations'] as &$translation) {
                    $translation['manufacturerId'] = $converted['id'];
                }
                unset($translation);
            }
        }
        unset($data['translations']);

        if (empty($data)) {
            $data = null;
        }

        return new ConvertStruct($converted, $data, $this->mainMapping['id']);
    }
}
