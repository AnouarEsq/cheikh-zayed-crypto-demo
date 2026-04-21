<?php

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Encrypted
{
    public function __construct(public string $algorithm = 'aes')
    {
    }
}
