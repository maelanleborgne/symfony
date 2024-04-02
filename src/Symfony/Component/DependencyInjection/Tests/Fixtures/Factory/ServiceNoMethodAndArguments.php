<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Attribute\Factory;

#[Factory(service: 'factory_service_id', arguments: ['arg1', '$foo' => 123456, '$targetClass' => self::class])]
class ServiceNoMethodAndArguments extends AbstractFactoryInstantiatedService
{
}
