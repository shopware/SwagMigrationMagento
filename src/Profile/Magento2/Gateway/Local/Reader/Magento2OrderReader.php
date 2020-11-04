<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\OrderReader;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

abstract class Magento2OrderReader extends OrderReader
{
    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}sales_order;
SQL;
        $total = (int) $this->connection->executeQuery($sql)->fetchColumn();

        return new TotalStruct(DefaultEntities::ORDER, $total);
    }

    /**
     * @psalm-suppress PossiblyInvalidArgument
     */
    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $ids = $this->fetchIdentifiers($this->tablePrefix . 'sales_order', 'entity_id', $migrationContext->getOffset(), $migrationContext->getLimit());
        $fetchedOrders = $this->mapData($this->fetchOrders($ids), [], ['identifier', 'customerSalutation']);
        $fetchedDetails = $this->mapData($this->fetchDetails($ids), [], ['items']);
        $fetchedDeliveries = $this->fetchShipments($ids);

        foreach ($fetchedOrders as &$order) {
            if (isset($order['identifier'])) {
                $orderIdentifier = $order['identifier'];

                if (isset($fetchedDetails[$orderIdentifier])) {
                    $order['items'] = $fetchedDetails[$orderIdentifier];
                }

                if (isset($fetchedDeliveries[$orderIdentifier])) {
                    $order['shipments'] = $fetchedDeliveries[$orderIdentifier];
                }
            }
        }

        $fetchedOrders = $this->utf8ize($fetchedOrders);

        return $this->cleanupResultSet($fetchedOrders);
    }

    protected function fetchOrders(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'sales_order', 'orders');
        $query->addSelect('orders.entity_id as identifier');
        $query->addSelect('orders.customer_gender as customerSalutation');
        $this->addTableSelection($query, $this->tablePrefix . 'sales_order', 'orders');

        $query->leftJoin('orders', $this->tablePrefix . 'sales_order_payment', 'orders_payment', 'orders.entity_id = orders_payment.parent_id');
        $this->addTableSelection($query, $this->tablePrefix . 'sales_order_payment', 'orders_payment');

        $query->leftJoin(
            'orders',
            $this->tablePrefix . 'sales_order_address',
            'billingAddress',
            'billingAddress.parent_id = orders.entity_id AND billingAddress.address_type = \'billing\''
        );
        $this->addTableSelection($query, $this->tablePrefix . 'sales_order_address', 'billingAddress');

        $query->leftJoin(
            'orders',
            $this->tablePrefix . 'sales_order_address',
            'shippingAddress',
            'shippingAddress.parent_id = orders.entity_id AND shippingAddress.address_type = \'shipping\''
        );
        $this->addTableSelection($query, $this->tablePrefix . 'sales_order_address', 'shippingAddress');

        $query->where('orders.entity_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function fetchDetails(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'sales_order_item', 'items');
        $query->addSelect('items.order_id as identifier');
        $this->addTableSelection($query, $this->tablePrefix . 'sales_order_item', 'items');

        $query->leftJoin('items', $this->tablePrefix . 'sales_order_item', 'parentItem', 'parentItem.item_id = items.parent_item_id');
        $this->addTableSelection($query, $this->tablePrefix . 'sales_order_item', 'parentItem');

        $query->where('items.order_id IN (:ids)');
        $query->andWhere('items.product_type != \'configurable\'');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchShipments(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'sales_shipment', 'shipment');
        $query->addSelect('shipment.order_id as identifier');
        $this->addTableSelection($query, $this->tablePrefix . 'sales_shipment', 'shipment');

        $query->where('shipment.order_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        $shipments = $this->mapData($query->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC), [], ['shipment']);

        $shipmentIds = [];
        foreach ($shipments as $shipment) {
            foreach ($shipment as $value) {
                $shipmentIds[] = $value['entity_id'];
            }
        }

        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'sales_shipment_item', 'item');
        $query->addSelect('item.parent_id as identifier');
        $this->addTableSelection($query, $this->tablePrefix . 'sales_flat_shipment_item', 'item');

        $query->where('item.parent_id in (:ids)');
        $query->setParameter('ids', $shipmentIds, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        $shipmentItems = $this->mapData($query->fetchAll(\PDO::FETCH_GROUP), [], ['item']);

        foreach ($shipments as &$shipment) {
            foreach ($shipment as &$value) {
                $shipmentId = $value['entity_id'];

                if (isset($shipmentItems[$shipmentId])) {
                    $value['items'] = $shipmentItems[$shipmentId];
                }
            }
        }

        return $shipments;
    }
}
