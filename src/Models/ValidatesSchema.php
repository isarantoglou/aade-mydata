<?php

namespace Firebed\AadeMyData\Models;

interface ValidatesSchema
{
    /**
     * @return list<array{field: string|null, message: string|null}> The XSD validation errors
     */
    public function validate(): array;
}