<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Attribute\Factory;

#[Factory(method: 'create', arguments: [new TaggedIteratorArgument('custom_tag')])]
class WithTaggedIteratorArgument extends AbstractFactoryInstantiatedService
{
}
