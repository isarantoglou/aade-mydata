<?php

namespace Firebed\AadeMyData\Factories;

use Faker\Generator;
use Firebed\AadeMyData\Models\Type;

/**
 * @template TModel of Type
 */
abstract class Factory
{
    private static Generator $faker;

    /** @var class-string<TModel> */
    protected string $modelName;

    /** @var array<string, mixed> */
    protected array $state = [];

    /** @var list<string> */
    protected array $except = [];
    protected int $count = 1;

    /**
     * @template T of Type
     * @param class-string<T> $modelName
     * @param int $count
     * @return Factory<T>
     */
    public static function factoryForModel(string $modelName, int $count = 1): Factory
    {
        $factoryModelName = substr($modelName, strrpos($modelName, '\\') + 1);

        /** @var class-string<Factory<T>> $factoryName */
        $factoryName = __NAMESPACE__ . '\\' . $factoryModelName . 'Factory';

        $factory = self::newFactory($factoryName);
        $factory->modelName = $modelName;
        $factory->count = $count;
        return $factory;
    }

    /**
     * @template TFactory of Factory
     * @param class-string<TFactory> $factoryName
     * @return TFactory
     */
    public static function newFactory($factoryName): Factory
    {
        return new $factoryName();
    }

    /**
     * @param array<string, mixed> $attributes
     * @return TModel|list<TModel> A single model when count is 1, otherwise a list
     */
    public function make(array $attributes = [])
    {
        $instances = [];
        for ($i = 0; $i < $this->count; $i++) {
            $attributes = array_merge($this->definition(), $attributes, $this->state);

            if (! empty($this->except)) {
                $attributes = array_diff_key($attributes, array_flip($this->except));
            }

            $instance = new $this->modelName();

            foreach ($attributes as $key => $value) {
                if ($value instanceof Factory) {
                    $value = $value->make();
                }

                $instance->set($key, $value);
            }

            $instances[] = $instance;
        }

        return $this->count === 1 ? $instances[0] : $instances;
    }

    /**
     * @param list<string> $fields
     * @return $this
     */
    public function except(array $fields): self
    {
        $this->except = $fields;

        return $this;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return $this
     */
    public function state(array $attributes): self
    {
        $this->state = array_merge($attributes);

        return $this;
    }

    public static function fake(): Generator
    {
        return self::$faker ??= \Faker\Factory::create();
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function definition(): array;
}

if (! function_exists('fake')) {
    function fake(): Generator
    {
        return Factory::fake();
    }
}