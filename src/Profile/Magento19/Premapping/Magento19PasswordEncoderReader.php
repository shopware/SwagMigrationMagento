<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento19\Premapping;

use Shopware\Core\Checkout\Customer\Password\LegacyEncoder\LegacyEncoderInterface;
use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use Swag\MigrationMagento\Profile\Magento19\PasswordEncoder\Magento19EncoderInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;

class Magento19PasswordEncoderReader extends AbstractPremappingReader
{
    private const MAPPING_NAME = 'password_encoder';

    /**
     * @var LegacyEncoderInterface[]
     */
    private $legacyEncoders;

    /**
     * @param LegacyEncoderInterface[] $legacyEncoders
     */
    public function __construct(iterable $legacyEncoders)
    {
        $this->legacyEncoders = $legacyEncoders;
    }

    public static function getMappingName(): string
    {
        return self::MAPPING_NAME;
    }

    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile;
    }

    public function getPremapping(Context $context, MigrationContextInterface $migrationContext): PremappingStruct
    {
        $this->fillConnectionPremappingDictionary($migrationContext);
        $mapping = $this->getMapping();
        $choices = $this->getChoices();

        return new PremappingStruct(self::getMappingName(), $mapping, $choices);
    }

    /**
     * @return PremappingEntityStruct[]
     */
    protected function getMapping(): array
    {
        $uuid = '';
        if (isset($this->connectionPremappingDictionary['default_password_encoder'])) {
            $uuid = $this->connectionPremappingDictionary['default_password_encoder']['destinationUuid'];
        }

        $entityData = [];
        $entityData[] = new PremappingEntityStruct('default_password_encoder', 'Password encoder', $uuid);

        return $entityData;
    }

    /**
     * @return PremappingChoiceStruct[]
     */
    protected function getChoices(): array
    {
        $choices = [];
        foreach ($this->legacyEncoders as $encoder) {
            if ($encoder instanceof Magento19EncoderInterface) {
                $choices[] = new PremappingChoiceStruct($encoder->getName(), $encoder->getDisplayName());
            }
        }

        return $choices;
    }
}
