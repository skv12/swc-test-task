<?php

namespace App\Dto;

readonly class LoginDto
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }
}