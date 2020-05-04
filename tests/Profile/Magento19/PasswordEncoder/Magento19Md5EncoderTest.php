<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\PasswordEncoder;

use PHPUnit\Framework\TestCase;
use Swag\MigrationMagento\Profile\Magento19\PasswordEncoder\Magento19Md5Encoder;

class Magento19Md5EncoderTest extends TestCase
{
    public function testGetDisplayName(): void
    {
        $encoder = new Magento19Md5Encoder();
        static::assertSame('MD5', $encoder->getDisplayName());
        static::assertSame('Magento19Md5', $encoder->getName());
    }

    public function testIsPasswordValidWithValidPassword(): void
    {
        $hash = '50d76f38cd475aa3210cebf77db3d3c0:SALT';
        $encoder = new Magento19Md5Encoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithValidPasswordAndWithoutSalt(): void
    {
        $hash = 'a256a310bc1e5db755fd392c524028a8';
        $encoder = new Magento19Md5Encoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithInvalidPassword(): void
    {
        $hash = '50d76f38cd475aa3210cebf77db3d3c0:SALT';
        $encoder = new Magento19Md5Encoder();
        static::assertFalse($encoder->isPasswordValid('shopware123', $hash));
    }
}
