<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Attribute\Factory;

#[Factory(service: '@factory_service_id', method: 'create', arguments: ['arg1', '$foo' => 123456, '$targetClass' => self::class])]
class ServiceWithAtPrefixAndMethodAndArguments extends AbstractFactoryInstantiatedService
{
}
