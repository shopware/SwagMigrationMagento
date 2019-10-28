<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Migration\Mapping\Registry\LanguageRegistry;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\LanguageDataSet;
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
        /*
         * Set main data
         */
        $this->generateChecksum($data);
        $this->runId = $migrationContext->getRunUuid();
        $this->migrationContext = $migrationContext;
        $this->oldIdentifier = $data['locale'];
        $this->connectionId = $migrationContext->getConnection()->getId();
        $this->context = $context;

        $languageUuid = $this->mappingService->getLanguageUuid(
            $this->connectionId,
            $data['locale'],
            $this->context,
            true
        );

        if ($languageUuid !== null) {
            return new ConvertStruct([], $data);
        }

        $languageData = LanguageRegistry::get($this->oldIdentifier);

        if ($languageData === null) {
            return new ConvertStruct([], $data);
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
            return new ConvertStruct([], $data);
        }

        $languageMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::LANGUAGE,
            $this->oldIdentifier,
            $this->context
        );
        $this->mappingIds[] = $languageMapping['id'];

        $converted['id'] = $languageMapping['entityUuid'];
        $converted['name'] = $languageData['name'];
        $converted['localeId'] = $localeUuid;
        $converted['translationCodeId'] = $localeUuid;

        return new ConvertStruct($converted, $data);
    }
}
