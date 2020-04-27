<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\PasswordEncoder;

use PHPUnit\Framework\TestCase;
use Swag\MigrationMagento\Profile\Magento19\PasswordEncoder\MagentoEncoder;

class MagentoEncoderTest extends TestCase
{
    public function testIsPasswordValidWithValidPassword(): void
    {
        $hash = '50d76f38cd475aa3210cebf77db3d3c0:SALT';
        $encoder = new MagentoEncoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithInvalidPassword(): void
    {
        $hash = '50d76f38cd475aa3210cebf77db3d3c0:SALT';
        $encoder = new MagentoEncoder();
        static::assertFalse($encoder->isPasswordValid('shopware123', $hash));
    }
}
