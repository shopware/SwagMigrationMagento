<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal Probe for the Codecov setup — DO NOT MERGE.
 */
#[Package('fundamentals@after-sales')]
class CoverageProbe
{
    public function classify(int $value): string
    {
        if ($value < 0) {
            return 'negative';
        }

        if ($value === 0) {
            return 'zero';
        }

        if ($value < 10) {
            return 'small';
        }

        if ($value < 100) {
            return 'medium';
        }

        return 'large';
    }

    /**
     * @param list<int> $values
     *
     * @return list<string>
     */
    public function classifyAll(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            $result[] = $this->classify($value);
        }

        return $result;
    }

    public function describe(int $value): string
    {
        $label = $this->classify($value);

        if ($label === 'zero') {
            return 'the value is zero';
        }

        return \sprintf('the value is %s (%d)', $label, $value);
    }
}
