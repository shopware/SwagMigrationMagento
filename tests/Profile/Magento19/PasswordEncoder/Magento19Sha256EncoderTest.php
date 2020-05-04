<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\PasswordEncoder;

use PHPUnit\Framework\TestCase;
use Swag\MigrationMagento\Profile\Magento19\PasswordEncoder\Magento19Sha256Encoder;

class Magento19Sha256EncoderTest extends TestCase
{
    public function testGetDisplayName(): void
    {
        $encoder = new Magento19Sha256Encoder();
        static::assertSame('SHA-256', $encoder->getDisplayName());
        static::assertSame('Magento19Sha256', $encoder->getName());
    }

    public function testIsPasswordValidWithValidPassword(): void
    {
        $hash = '119bbb83956d4e609498e1bd70004c015a39a8769b109232358cc5118a495150:SALT';
        $encoder = new Magento19Sha256Encoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithValidPasswordAndWithoutSalt(): void
    {
        $hash = 'bf5a89bd8a6d00f715165dcac2e3a189533e5d908f4532324895c541fd6ba7aa';
        $encoder = new Magento19Sha256Encoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithInvalidPassword(): void
    {
        $hash = '119bbb83956d4e609498e1bd70004c015a39a8769b109232358cc5118a495150:SALT';
        $encoder = new Magento19Sha256Encoder();
        static::assertFalse($encoder->isPasswordValid('shopware123', $hash));
    }
}
