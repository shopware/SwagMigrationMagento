<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Premapping;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Profile\Magento\DataSelection\NewsletterRecipientDataSelection;
use Swag\MigrationMagento\Profile\Magento\MagentoProfileInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;

abstract class NewsletterRecipientStatusReader extends AbstractPremappingReader
{
    protected const MAPPING_NAME = 'newsletter_status';

    public static function getMappingName(): string
    {
        return self::MAPPING_NAME;
    }

    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof MagentoProfileInterface
            && \in_array(NewsletterRecipientDataSelection::IDENTIFIER, $entityGroupNames, true);
    }

    public function getPremapping(Context $context, MigrationContextInterface $migrationContext): PremappingStruct
    {
        $mapping = $this->getMapping($migrationContext);
        $choices = $this->getChoices();

        return new PremappingStruct(self::getMappingName(), $mapping, $choices);
    }

    /**
     * @return PremappingEntityStruct[]
     */
    protected function getMapping(MigrationContextInterface $migrationContext): array
    {
        $mapping = [];
        $connection = $migrationContext->getConnection();
        $choices = [
            '1' => 'Subscribed',
            '2' => 'Not active',
            '3' => 'Unsubscribed',
            '4' => 'Unconfirmed',
            'default_newsletter_recipient_status' => 'Standard newsletter status',
        ];

        if ($connection === null) {
            return $mapping;
        }

        $connectionPremapping = $connection->getPremapping();
        if ($connectionPremapping === null) {
            foreach ($choices as $key => $choice) {
                $mapping[] = new PremappingEntityStruct((string) $key, $choice, '');
            }

            return $mapping;
        }

        foreach ($connectionPremapping as $premapping) {
            if ($premapping['entity'] !== self::MAPPING_NAME) {
                continue;
            }

            foreach ($premapping['mapping'] as $premapping) {
                $mapping[] = new PremappingEntityStruct($premapping['sourceId'], $premapping['description'], $premapping['destinationUuid']);
                unset($choices[$premapping['sourceId']]);
            }
        }

        foreach ($choices as $key => $choice) {
            $mapping[] = new PremappingEntityStruct((string) $key, $choice, '');
        }
        \usort($mapping, function (PremappingEntityStruct $item1, PremappingEntityStruct $item2) {
            return \strcmp($item1->getDescription(), $item2->getDescription());
        });

        return $mapping;
    }

    /**
     * @return PremappingChoiceStruct[]
     */
    protected function getChoices(): array
    {
        $choices = [];
        $choices[] = new PremappingChoiceStruct('direct', 'Direct');
        $choices[] = new PremappingChoiceStruct('notSet', 'Not set');
        $choices[] = new PremappingChoiceStruct('optIn', 'OptIn');
        $choices[] = new PremappingChoiceStruct('optOut', 'OptOut');

        return $choices;
    }
}
