<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Attribute\Factory;
use Symfony\Component\DependencyInjection\Reference;

#[Factory(method: 'create', arguments: [new ServiceClosureArgument(new Reference('other_service'))])]
class WithServiceClosureArgument extends AbstractFactoryInstantiatedService
{
}
