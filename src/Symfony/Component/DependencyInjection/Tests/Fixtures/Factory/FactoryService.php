<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

class FactoryService
{
    public function __invoke($randomArgName = null, $foo = null, $targetClass = null)
    {
        return $this->createService($targetClass, 'service_invoke', [$randomArgName, $foo]);
    }

    public function create($randomArgName = null, $foo = null, $targetClass = null)
    {
        return $this->createService($targetClass, 'service_create', [$randomArgName, $foo]);
    }

    private function createService(string $targetClass, string $factoryName, array $arguments)
    {
        $instance = new $targetClass();
        $instance->factoryName = $factoryName;
        $instance->args = $arguments;

        return $instance;
    }
}
