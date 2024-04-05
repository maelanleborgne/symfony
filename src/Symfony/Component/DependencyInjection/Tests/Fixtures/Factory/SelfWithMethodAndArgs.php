<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Attribute\Factory;

#[Factory(method: 'create', arguments: ['$foo' => 'bar', 123456])]
class SelfWithMethodAndArgs extends AbstractFactoryInstantiatedService
{
}
