<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Attribute\Factory;

#[Factory(method: 'create')]
#[Factory(service: 'factory_service', method: 'create')]
class MultipleAttributeClass extends AbstractFactoryInstantiatedService
{
}
