<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class ManufacturerConverter extends MagentoConverter
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
        $this->context = $context;

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PRODUCT_MANUFACTURER,
            $this->oldIdentifier,
            $this->context,
            $this->checksum
        );

        $converted = [];
        $converted['id'] = $this->mainMapping['entityUuid'];
        unset($data['option_id']);

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

        $language = $this->mappingService->getDefaultLanguage($this->context);
        if ($language === null || !isset($converted['translations'][$language->getId()]['name'])) {
            $this->convertValue($converted, 'name', $data, 'value');
        }
        unset($data['value']);

        $resultData = $data;
        if (empty($resultData)) {
            $resultData = null;
        }

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id']);
    }
}
