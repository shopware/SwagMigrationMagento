<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\PasswordEncoder;

use Shopware\Core\Checkout\Customer\Password\LegacyEncoder\LegacyEncoderInterface;

class Magento2Argon2Id13Encoder implements LegacyEncoderInterface
{
    public const NAME = 'Magento2Argon2Id13';

    public function getName(): string
    {
        return self::NAME;
    }

    public function isPasswordValid(string $password, string $hash): bool
    {
        if (\mb_strpos($hash, ':') === false) {
            return false;
        }
        [$hash, $salt, $version] = \explode(':', $hash);

        if ($version !== '2' || !\defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13') || !\extension_loaded('sodium')) {
            return false;
        }

        $challengeHash = \bin2hex(
            \sodium_crypto_pwhash(
                \SODIUM_CRYPTO_SIGN_SEEDBYTES,
                $password,
                $salt,
                \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
                (int) $version
            )
        );

        return \hash_equals($hash, $challengeHash);
    }
}
