<?php

namespace Odan\Migration\Adapter\Generator;

/**
 * Class RawPhpValue.
 */
final class RawPhpValue
{
    /**
     * @var string
     */
    private $value;

    /**
     * Constructor.
     *
     * @param string $value
     */
    public function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * To php value.
     *
     * @return string
     */
    public function toPHP(): string
    {
        return $this->value;
    }
}
