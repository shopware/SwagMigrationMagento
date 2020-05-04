<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Profile\Magento19\PasswordEncoder;

use PHPUnit\Framework\TestCase;
use Swag\MigrationMagento\Profile\Magento19\PasswordEncoder\Magento19Sha512Encoder;

class Magento19Sha512EncoderTest extends TestCase
{
    public function testGetDisplayName(): void
    {
        $encoder = new Magento19Sha512Encoder();
        static::assertSame('SHA-512', $encoder->getDisplayName());
        static::assertSame('Magento19Sha512', $encoder->getName());
    }

    public function testIsPasswordValidWithValidPassword(): void
    {
        $hash = '5a0eac58608cff7946e3decb578ebb45e089879768cd16d546b075a1111dac0eaeb8afe38ebbe5dfb122bcf9bb5e97406520949e41e3ca3d7a93748f24b426ad:FlGQ3hYQ0DCjo2jo2AVAP9TdI4ZlldDK';
        $encoder = new Magento19Sha512Encoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithValidPasswordAndWithoutSalt(): void
    {
        $hash = '546956258556a691daa1b719c319ef47a80105883fb7f18f98227a8f14ee6944491f9e3ca8d8790269baa86ce6f0fa584597d5aa13ac2d301ca05bb220afc761';
        $encoder = new Magento19Sha512Encoder();
        static::assertTrue($encoder->isPasswordValid('shopware', $hash));
    }

    public function testIsPasswordValidWithInvalidPassword(): void
    {
        $hash = '5a0eac58608cff7946e3decb578ebb45e089879768cd16d546b075a1111dac0eaeb8afe38ebbe5dfb122bcf9bb5e97406520949e41e3ca3d7a93748f24b426ad:FlGQ3hYQ0DCjo2jo2AVAP9TdI4ZlldDK';
        $encoder = new Magento19Sha512Encoder();
        static::assertFalse($encoder->isPasswordValid('shopware123', $hash));
    }
}
