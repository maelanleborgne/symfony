<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Attribute\Factory;

#[Factory(service: 'factory_service_id', method: 'create', arguments: ['$targetClass' => self::class])]
class ServiceAndMethod extends AbstractFactoryInstantiatedService
{
}
