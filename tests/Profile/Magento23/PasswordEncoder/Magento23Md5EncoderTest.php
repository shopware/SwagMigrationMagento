<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\PasswordEncoder;

use PHPUnit\Framework\TestCase;
use Swag\MigrationMagento\Profile\Magento2\PasswordEncoder\Magento2Md5Encoder;

class Magento2Md5EncoderTest extends TestCase
{
    public function testIsPasswordValidWithValidPassword(): void
    {
        $hash = 'e9885e11dfcdd0e93edbc8a48b464229:HOiS51whl8VzfvZvGfw5AKa8gn7c3bEZ:0';
        $encoder = new Magento2Md5Encoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithInvalidPassword(): void
    {
        $hash = 'e9885e11dfcdd0e93edbc8a48b464229:HOiS51whl8VzfvZvGfw5AKa8gn7c3bEZ:0';
        $encoder = new Magento2Md5Encoder();
        static::assertFalse($encoder->isPasswordValid('shopware123', $hash));
    }
}
