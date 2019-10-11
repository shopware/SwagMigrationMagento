<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local;

use Shopware\Core\Framework\Struct\Struct;

class StatementStruct extends Struct
{
    /**
     * @var string
     */
    private $selectStatement;

    /**
     * @var string
     */
    private $joinStatement;

    public function __construct(string $selectStatement, string $joinStatement)
    {
        $this->selectStatement = $selectStatement;
        $this->joinStatement = $joinStatement;
    }

    public function getSelectStatement(): string
    {
        return $this->selectStatement;
    }

    public function getJoinStatement(): string
    {
        return $this->joinStatement;
    }
}
