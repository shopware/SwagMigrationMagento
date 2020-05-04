<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento19\PasswordEncoder;

class Magento19BcryptEncoder extends Magento19Encoder
{
    public function getName(): string
    {
        return 'Magento19Bcrypt';
    }

    public function getDisplayName(): string
    {
        return 'Bcrypt';
    }

    public function isPasswordValid(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
