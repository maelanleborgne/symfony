<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

abstract class AbstractFactoryInstantiatedService
{
    public ?string $factoryName = null;
    public array $args = [];

    public static function create($randomArgName = null, $foo = null): self
    {
        $instance = new static();
        $instance->factoryName = 'self_create';
        $instance->args = [$randomArgName, $foo];

        return $instance;
    }

    public function getInstanceValidationKey(): string
    {
        return $this->factoryName.implode('', $this->args);
    }
}
