<?php

namespace App\Dto;

class Source
{
    public function __construct(
        public string $name,
        public string $webUrl,
    ) {
    }
}
