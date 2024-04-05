<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Attribute\Factory;

#[Factory(
    expression: '@=arg(0) + arg(1) > 10 ? service("factory_service_id").create(arg(0), arg(1), arg(2)) : service("factory_service_id").__invoke(arg(0), arg(1), arg(2))',
    arguments: [2, 4, self::class])
]
class ExpressionAsStringWithAtPrefix extends AbstractFactoryInstantiatedService
{
}
