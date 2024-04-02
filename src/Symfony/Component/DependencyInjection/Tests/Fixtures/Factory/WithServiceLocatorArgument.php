<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Attribute\Factory;
use Symfony\Component\DependencyInjection\Reference;

#[Factory(method: 'create', arguments: [new ServiceLocatorArgument([new Reference('other_service')])])]
class WithServiceLocatorArgument extends AbstractFactoryInstantiatedService
{
}
