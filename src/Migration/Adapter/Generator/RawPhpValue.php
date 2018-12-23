<?php

namespace Odan\Migration\Adapter\Generator;

/**
 * Class RawPhpValue.
 */
class RawPhpValue
{
    /**
     * @var string
     */
    protected $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function toPHP(): string
    {
        return $this->value;
    }
}
