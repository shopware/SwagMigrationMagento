<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Migration\Mapping\Registry\LanguageRegistry;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\LanguageDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Exception\LocaleNotFoundException;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class LanguageConverter extends MagentoConverter
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
            && $migrationContext->getDataSet()::getEntity() === LanguageDataSet::getEntity();
    }

    public function getSourceIdentifier(array $data): string
    {
        return $data['locale'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $this->originalData = $data;
        $this->runId = $migrationContext->getRunUuid();
        $this->migrationContext = $migrationContext;
        $this->oldIdentifier = $data['locale'];
        $this->connectionId = $migrationContext->getConnection()->getId();
        $this->context = $context;

        $languageUuid = $this->mappingService->getLanguageUuid(
            $this->connectionId,
            $this->oldIdentifier,
            $this->context,
            true
        );

        if ($languageUuid !== null) {
            foreach ($data['stores'] as $storeId) {
                $this->mappingService->getOrCreateMapping(
                    $this->connectionId,
                    MagentoDefaultEntities::STORE_LANGUAGE,
                    $storeId,
                    $this->context,
                    null,
                    null,
                    $languageUuid
                );
            }

            return new ConvertStruct(null, $this->originalData);
        }
        $languageData = LanguageRegistry::get($this->oldIdentifier);
        if ($languageData === null) {
            return new ConvertStruct(null, $this->originalData);
        }

        $localeUuid = null;
        try {
            $localeUuid = $this->mappingService->getLocaleUuid(
                $this->connectionId,
                $this->oldIdentifier,
                $this->context
            );
        } catch (LocaleNotFoundException $exception) {
        }

        if ($localeUuid === null) {
            return new ConvertStruct(null, $this->originalData);
        }

        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::LANGUAGE,
            $this->oldIdentifier,
            $this->context,
            $this->checksum
        );
        $languageUuid = $this->mainMapping['entityUuid'];

        foreach ($data['stores'] as $storeId) {
            $languageMapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                MagentoDefaultEntities::STORE_LANGUAGE,
                $storeId,
                $this->context,
                null,
                null,
                $languageUuid
            );
            $this->mappingIds[] = $languageMapping['id'];
        }
        unset($data['stores']);

        $converted['id'] = $languageUuid;
        $converted['name'] = $languageData['name'];
        $converted['localeId'] = $localeUuid;
        $converted['translationCodeId'] = $localeUuid;
        unset($data['locale']);

        if (empty($data)) {
            $data = null;
        }
        $this->updateMainMapping($migrationContext, $context);

        return new ConvertStruct($converted, $data, $this->mainMapping['id']);
    }
}
