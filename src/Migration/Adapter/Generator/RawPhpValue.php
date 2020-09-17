<?php

namespace Odan\Migration\Adapter\Generator;

/**
 * A raw PHP value.
 */
final class RawPhpValue
{
    /**
     * @var string
     */
    private $value;

    /**
     * The constructor.
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
