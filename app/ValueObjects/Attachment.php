<?php

namespace App\ValueObjects;

use ArrayAccess;
use Illuminate\Http\UploadedFile;

class Attachment implements ArrayAccess
{
    public function __construct(
        public ?UploadedFile $file = null,
        public ?string $url = null,
        public ?string $uuid = null,
        public int $order = 1,
    ) {
    }

    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            return;
        }

        $this->$offset = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        if (is_null($offset)) {
            return;
        }

        unset($this->$offset);
    }
}