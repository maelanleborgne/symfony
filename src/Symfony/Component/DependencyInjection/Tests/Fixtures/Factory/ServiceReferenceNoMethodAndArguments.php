<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Attribute\Factory;
use Symfony\Component\DependencyInjection\Reference;

#[Factory(service: new Reference('factory_service_id'), arguments: ['arg1', '$foo' => 123456, '$targetClass' => self::class])]
class ServiceReferenceNoMethodAndArguments extends AbstractFactoryInstantiatedService
{
}
