<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Swag\MigrationMagento\Profile\Magento\Gateway\Connection\ConnectionFactory;

/**
 * Executes the schema introspection of addTableSelection() against a live database,
 * so a CI run on the lowest supported platform fails when the used DBAL API does not
 * exist in the DBAL version that platform ships.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
class AbstractReaderTableIntrospectionTest extends TestCase
{
    use KernelTestBehaviour;

    public function testAddTableSelectionSelectsEveryColumnOfTheIntrospectedTable(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        $reader = new DummyReader(new ConnectionFactory());
        $reader->setDatabaseConnection($connection);

        $query = $connection->createQueryBuilder();
        $query->from('language', 'lang');
        $reader->testAddTableSelection($query, 'language', 'lang');

        $sql = $query->getSQL();
        static::assertStringContainsString('`lang`.`id` AS `lang.id`', $sql);
        static::assertStringContainsString('`lang`.`name` AS `lang.name`', $sql);

        $rows = $query->executeQuery()->fetchAllAssociative();
        static::assertNotEmpty($rows);
        static::assertArrayHasKey('lang.id', $rows[0]);
        static::assertArrayHasKey('lang.name', $rows[0]);
    }
}
