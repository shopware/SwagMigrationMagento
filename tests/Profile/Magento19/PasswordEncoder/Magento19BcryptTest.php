<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Profile\Magento19\PasswordEncoder;

use PHPUnit\Framework\TestCase;
use Swag\MigrationMagento\Profile\Magento19\PasswordEncoder\Magento19BcryptEncoder;

class Magento19BcryptTest extends TestCase
{
    public function testGetDisplayName(): void
    {
        $encoder = new Magento19BcryptEncoder();
        static::assertSame('Bcrypt', $encoder->getDisplayName());
        static::assertSame('Magento19Bcrypt', $encoder->getName());
    }

    public function testIsPasswordValidWithValidPassword(): void
    {
        $hash = '$2y$10$/z/DjUsGyFtAbsaL5SXVX.7IQOFkqYXL5p0iv.tzzx4rEfXaBVPmi';
        $encoder = new Magento19BcryptEncoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithInvalidPassword(): void
    {
        $hash = '$2y$10$/z/DjUsGyFtAbsaL5SXVX.7IQOFkqYXL5p0iv.tzzx4rEfXaBVPmi';
        $encoder = new Magento19BcryptEncoder();
        static::assertFalse($encoder->isPasswordValid('shopware123', $hash));
    }
}
