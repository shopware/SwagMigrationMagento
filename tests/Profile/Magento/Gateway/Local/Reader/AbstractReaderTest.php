<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\Gateway\Local\Reader;

use PHPUnit\Framework\TestCase;
use Swag\MigrationMagento\Profile\Magento\Gateway\Connection\ConnectionFactory;

class AbstractReaderTest extends TestCase
{
    /**
     * @var DummyReader
     */
    private $dummyReader;

    protected function setUp(): void
    {
        $this->dummyReader = new DummyReader(new ConnectionFactory());
    }

    public function testUtf8size(): void
    {
        $array = [
            'bool' => true,
            'int' => 1,
            'float' => 1.0,
            'string' => 'Normal string',
            'utf16-string' => \mb_convert_encoding('SFÖSÖFJOÜÜRER', 'UTF-16', 'UTF-8'),
            [
                'bool' => true,
                'int' => 1,
                'float' => 1.0,
                'string' => 'Normal string',
                'utf16-string' => \mb_convert_encoding('SFÖSÖFJOÜÜRER', 'UTF-16', 'UTF-8'),
            ],
        ];

        $newArray = $this->dummyReader->testUtf8ize($array);
        $json = \json_encode($newArray);

        static::assertIsString($json);
        static::assertIsBool($newArray['bool']);
        static::assertIsInt($newArray['int']);
        static::assertIsFloat($newArray['float']);
        static::assertIsString($newArray['string']);
        static::assertIsString($newArray['utf16-string']);

        static::assertIsBool($newArray[0]['bool']);
        static::assertIsInt($newArray[0]['int']);
        static::assertIsFloat($newArray[0]['float']);
        static::assertIsString($newArray[0]['string']);
        static::assertIsString($newArray[0]['utf16-string']);
    }
}
