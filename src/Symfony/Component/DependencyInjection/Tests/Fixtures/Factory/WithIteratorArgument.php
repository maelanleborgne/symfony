<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Attribute\Factory;

#[Factory(method: 'create', arguments: [new IteratorArgument(['value 1', 'value 2'])])]
class WithIteratorArgument extends AbstractFactoryInstantiatedService
{
}
