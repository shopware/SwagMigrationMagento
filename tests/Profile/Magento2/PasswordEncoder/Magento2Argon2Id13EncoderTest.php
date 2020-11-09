<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\PasswordEncoder;

use PHPUnit\Framework\TestCase;
use Swag\MigrationMagento\Profile\Magento2\PasswordEncoder\Magento2Argon2Id13Encoder;

class Magento2Argon2Id13EncoderTest extends TestCase
{
    public function testIsPasswordValidWithInvalidVersion(): void
    {
        $hash = '0031eacf0a058199396f3ecd3a68ea620bb335a3bbf22e5f34547e27affa7249:HOiS51whl8VzfvZvGfw5AKa8gn7c3bEZ:3';
        $encoder = new Magento2Argon2Id13Encoder();
        static::assertFalse($encoder->isPasswordValid('shopware', $hash));
    }
}
