<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\Converter;

use Shopware\Core\Framework\Log\Package;
use Swag\MigrationMagento\Profile\Magento\Converter\OrderConverter;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use SwagMigrationAssistant\Exception\MigrationException;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;

#[Package('fundamentals@after-sales')]
abstract class Magento2OrderConverter extends OrderConverter
{
    /**
     * @var array<mixed>
     */
    protected array $billingAddress = [];

    protected function convertOrderCustomer(array &$converted, array &$data): bool
    {
        $guestOrder = false;
        if (isset($data['orders']['customer_id']) && $data['orders']['customer_is_guest'] === '0') {
            $customerMapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CUSTOMER,
                $data['orders']['customer_id'],
                $this->context
            );

            if ($customerMapping === null) {
                throw MigrationException::associationEntityRequiredMissing(
                    DefaultEntities::ORDER,
                    DefaultEntities::CUSTOMER
                );
            }
            $converted['orderCustomer'] = [
                'customerId' => $customerMapping['entityId'],
            ];
            $this->mappingIds[] = $customerMapping['id'];
            unset($customerMapping);

            $this->convertValue($converted['orderCustomer'], 'email', $data['orders'], 'customer_email');
            $this->convertValue($converted['orderCustomer'], 'firstName', $data['orders'], 'customer_firstname');
            $this->convertValue($converted['orderCustomer'], 'lastName', $data['orders'], 'customer_lastname');
        } else {
            $guestOrder = true;
            $converted['orderCustomer']['customer']['guest'] = true;
        }

        /*
         * Set salutation
         */
        if (isset($data['orders']['customer_salutation'])) {
            $salutationUuid = $this->getSalutation($data['orders']['customer_salutation'], $this->migrationContext);

            if ($salutationUuid === null) {
                return false;
            }

            $this->salutationUuid = $salutationUuid;
        } else {
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::SALUTATION,
                'default_salutation',
                $this->context
            );

            if ($mapping !== null && isset($mapping['entityId'])) {
                $this->mappingIds[] = $mapping['id'];
                $this->salutationUuid = $mapping['entityId'];
                $converted['orderCustomer']['salutationId'] = $this->salutationUuid;
            }
        }

        if ($guestOrder === true) {
            $converted['orderCustomer']['salutationId'] = $this->salutationUuid;
            $converted['orderCustomer']['customer']['salutationId'] = $this->salutationUuid;

            $customerGroupMapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CUSTOMER_GROUP,
                $data['orders']['customer_group_id'],
                $this->context
            );

            if ($customerGroupMapping !== null) {
                $this->mappingIds[] = $customerGroupMapping['id'];
                $converted['orderCustomer']['customer']['groupId'] = $customerGroupMapping['entityId'];
            }

            $languageMapping = $this->mappingService->getMapping(
                $this->connectionId,
                MagentoDefaultEntities::STORE_LANGUAGE,
                $data['orders']['store_id'],
                $this->context
            );

            if ($languageMapping !== null) {
                $this->mappingIds[] = $languageMapping['id'];
                $converted['orderCustomer']['customer']['languageId'] = $languageMapping['entityId'];
            }

            $paymentMethodUuid = $this->getPaymentMethod($data);

            if ($paymentMethodUuid !== null) {
                $converted['orderCustomer']['customer']['defaultPaymentMethodId'] = $paymentMethodUuid;
            }

            $converted['orderCustomer']['customer']['salesChannelId'] = $converted['salesChannelId'];
            $converted['orderCustomer']['customer']['customerNumber'] = $this->numberRangeValueGenerator->getValue('customer', $this->context, null);

            $billingAddress = $this->getAddress($data['billingAddress'], DefaultEntities::CUSTOMER_ADDRESS);

            if (empty($billingAddress)) {
                return false;
            }

            $converted['orderCustomer']['customer']['addresses'][] = $billingAddress;
            $converted['orderCustomer']['customer']['defaultBillingAddressId'] = $billingAddress['id'];

            $shippingAddress = $this->getAddress($data['shippingAddress'], DefaultEntities::CUSTOMER_ADDRESS);

            if (empty($shippingAddress)) {
                $shippingAddress = $billingAddress;
            }

            $converted['orderCustomer']['customer']['defaultShippingAddressId'] = $shippingAddress['id'];

            $this->convertValue($converted['orderCustomer']['customer'], 'email', $data['orders'], 'customer_email', self::TYPE_STRING, false);
            $this->convertValue($converted['orderCustomer']['customer'], 'firstName', $billingAddress, 'firstName', self::TYPE_STRING, false);
            $this->convertValue($converted['orderCustomer']['customer'], 'lastName', $billingAddress, 'lastName', self::TYPE_STRING, false);

            $this->convertValue($converted['orderCustomer'], 'email', $data['orders'], 'customer_email', self::TYPE_STRING);
            $this->convertValue($converted['orderCustomer'], 'firstName', $billingAddress, 'firstName', self::TYPE_STRING, false);
            $this->convertValue($converted['orderCustomer'], 'lastName', $billingAddress, 'lastName', self::TYPE_STRING, false);
        }
        unset($data['customerSalutation']);

        return true;
    }
}
