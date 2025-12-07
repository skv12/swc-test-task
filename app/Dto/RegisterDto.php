<?php

namespace App\Dto;

readonly class RegisterDto
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {
    }
}