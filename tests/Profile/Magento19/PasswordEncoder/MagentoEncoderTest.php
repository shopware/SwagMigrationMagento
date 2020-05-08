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
    public function testGetDisplayName(): void
    {
        $encoder = new MagentoEncoder();
        static::assertSame('Magento19', $encoder->getName());
    }

    public function testIsPasswordValidWithValidBcryptPassword(): void
    {
        $hash = '$2y$10$/z/DjUsGyFtAbsaL5SXVX.7IQOFkqYXL5p0iv.tzzx4rEfXaBVPmi';
        $encoder = new MagentoEncoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithInvalidBcryptPassword(): void
    {
        $hash = '$2y$10$/z/DjUsGyFtAbsaL5SXVX.7IQOFkqYXL5p0iv.tzzx4rEfXaBVPmi';
        $encoder = new MagentoEncoder();
        static::assertFalse($encoder->isPasswordValid('shopware123', $hash));
    }

    public function testIsPasswordValidWithValidMd5Password(): void
    {
        $hash = '50d76f38cd475aa3210cebf77db3d3c0:SALT';
        $encoder = new MagentoEncoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithValidMd5PasswordAndWithoutSalt(): void
    {
        $hash = 'a256a310bc1e5db755fd392c524028a8';
        $encoder = new MagentoEncoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithInvalidMd5Password(): void
    {
        $hash = '50d76f38cd475aa3210cebf77db3d3c0:SALT';
        $encoder = new MagentoEncoder();
        static::assertFalse($encoder->isPasswordValid('shopware123', $hash));
    }

    public function testIsPasswordValidWithValidSha256Password(): void
    {
        $hash = '119bbb83956d4e609498e1bd70004c015a39a8769b109232358cc5118a495150:SALT';
        $encoder = new MagentoEncoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithValidSha256PasswordAndWithoutSalt(): void
    {
        $hash = 'bf5a89bd8a6d00f715165dcac2e3a189533e5d908f4532324895c541fd6ba7aa';
        $encoder = new MagentoEncoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithInvalidSha256Password(): void
    {
        $hash = '119bbb83956d4e609498e1bd70004c015a39a8769b109232358cc5118a495150:SALT';
        $encoder = new MagentoEncoder();
        static::assertFalse($encoder->isPasswordValid('shopware123', $hash));
    }

    public function testIsPasswordValidWithValidSha512Password(): void
    {
        $hash = '5a0eac58608cff7946e3decb578ebb45e089879768cd16d546b075a1111dac0eaeb8afe38ebbe5dfb122bcf9bb5e97406520949e41e3ca3d7a93748f24b426ad:FlGQ3hYQ0DCjo2jo2AVAP9TdI4ZlldDK';
        $encoder = new MagentoEncoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithValidSha512PasswordAndWithoutSalt(): void
    {
        $hash = '546956258556a691daa1b719c319ef47a80105883fb7f18f98227a8f14ee6944491f9e3ca8d8790269baa86ce6f0fa584597d5aa13ac2d301ca05bb220afc761';
        $encoder = new MagentoEncoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithInvalidSha512Password(): void
    {
        $hash = '5a0eac58608cff7946e3decb578ebb45e089879768cd16d546b075a1111dac0eaeb8afe38ebbe5dfb122bcf9bb5e97406520949e41e3ca3d7a93748f24b426ad:FlGQ3hYQ0DCjo2jo2AVAP9TdI4ZlldDK';
        $encoder = new MagentoEncoder();
        static::assertFalse($encoder->isPasswordValid('shopware123', $hash));
    }
}
