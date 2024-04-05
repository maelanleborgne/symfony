<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Attribute\Factory;
use Symfony\Component\ExpressionLanguage\Expression;

#[Factory(
    expression: new Expression('arg(0) + arg(1) > 10 ? service("factory_service_id").create(arg(0), arg(1), arg(2)) : service("factory_service_id").__invoke(arg(0), arg(1), arg(2))'),
    arguments: [3, 8, self::class])
]
class ExpressionAsTypedExpression extends AbstractFactoryInstantiatedService
{
}
