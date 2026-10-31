<?php

namespace Firebed\AadeMyData\Models;

use BackedEnum;

abstract class Type
{
    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var list<string> */
    protected array $expectedOrder = [];

    /** @var array<string, string> */
    protected array $casts = [];

    /** @var array<string, bool> */
    private static array $enumCache = [];

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setAttributes($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes = []): static
    {
        // TypeArray subclasses define their own constructors; make() is meant for the plain models.
        return new static($attributes); // @phpstan-ignore new.static
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function set($key, $value): static
    {
        $this->attributes[$key] = $this->castValue($key, $value);
        return $this;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected function castValue(string $key, $value)
    {
        if ($value === null) {
            return null;
        }

        // Auto cast 'true' or 'false' values to boolean
        if ($value === 'true' || $value === 'false') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        $cast = $this->getCast($key);
        if ($cast === null) {
            return $value;
        }

        if ($cast === 'float') {
            return filter_var($value, FILTER_VALIDATE_FLOAT);
        }

        if ($cast === 'int') {
            return filter_var($value, FILTER_VALIDATE_INT);
        }

        // Cast value to enum if it is not already an enum
        // If the value doesn't correspond to an enum, it
        // will return null.
        if ($this->isEnum($key) && ! is_object($value)) {
            return $cast::tryFrom($value);
        }

        return $value;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function push($key, $value = null): static
    {
        if ($value === null) {
            return $this;
        }

        $this->attributes[$key][] = $value;
        return $this;
    }

    /**
     * @param string $key
     */
    public function has($key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function sortedAttributes(): array
    {
        if (empty($this->expectedOrder)) {
            return $this->attributes;
        }

        $attributes = [];
        foreach ($this->expectedOrder as $key) {
            if (array_key_exists($key, $this->attributes) && $this->attributes[$key] !== null) {
                $attributes[$key] = $this->attributes[$key];
            }
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return $this
     */
    public function setAttributes(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $set = 'set' . str_replace('_', '', ucwords($key, '_'));

            if (is_object($value) || ! method_exists($this, $set)) {
                $this->attributes[$key] = $value;
                continue;
            }

            $cast = $this->getCast($key);

            if ($cast === null || ! is_subclass_of($cast, Type::class)) {
                $this->set($key, $value);
                continue;
            }

            if (is_subclass_of($cast, TypeArray::class)) {
                $this->$set(array_shift($value));
                continue;
            }

            $this->$set(
                is_array($value) && isset($value[0])
                    ? array_map(fn ($v) => is_object($v) ? $v : new $cast($v), $value)
                    : new $cast($value)
            );
        }

        return $this;
    }

    /**
     * @return string|null The cast type: a class name, 'int' or 'float'
     */
    public function getCast(string $name)
    {
        return $this->casts[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getExpectedOrder(): array
    {
        return $this->expectedOrder;
    }

    public function isEnum(string $name): bool
    {
        $cast = $this->casts[$name] ?? null;

        // Return false if $cast is null or directly return the cached value
        if ($cast === null || isset(self::$enumCache[$cast])) {
            return self::$enumCache[$cast] ?? false;
        }

        // Check if the class exists and implements BackedEnum
        if (! class_exists($cast) || ! is_subclass_of($cast, BackedEnum::class)) {
            return self::$enumCache[$cast] = false;
        }

        // Cache and return the result
        return self::$enumCache[$cast] = true;
    }

    /**
     * Returns an array representation of the object
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [];
        foreach ($this->attributes() as $key => $value) {
            if ($value instanceof Type) {
                $array[$key] = $value->toArray();
            } elseif (is_array($value)) {
                $array[$key] = array_map(fn ($v) => $v instanceof Type ? $v->toArray() : $v, $value);
            } elseif ($this->isEnum($key) && is_object($value)) {
                /** @var BackedEnum $value */
                $array[$key] = $value->value;
            } else {
                $array[$key] = $value;
            }
        }

        return $array;
    }
}
