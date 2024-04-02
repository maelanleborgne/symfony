<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Factory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\AutoconfigureFailedException;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Register factories for services tagged with "container.from_factory_attribute".
 *
 * @author Maelan Le Borgne <maelan.leborgne@gmail.com>
 */
final class RegisterFactoryPass implements CompilerPassInterface
{

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('container.from_factory_attribute') as $id => $tags) {
            $definition = $container->getDefinition($id);
            $class = $container->getReflectionClass($definition->getClass(), false);
            if (null === $class) {
                return;
            }

            // Check there is only one #[Factory] attribute in this class, on the class or on a public static method
            $attributes = $class->getAttributes(Factory::class);
            $target = $class;
            foreach ($class->getMethods() as $method) {
                $methodAttributes = $method->getAttributes(Factory::class);
                if (empty($methodAttributes)) {
                    continue;
                }
                if (!$method->isPublic() || !$method->isStatic()) {
                    throw new LogicException(sprintf('The #[Factory] attribute can only be used on classes and public static methods, but "%s::%s()" is not.', $class->name, $method->name));
                }
                $attributes = array_merge($attributes, $methodAttributes);
                $target = $method;
            }

            if (0 === \count($attributes)) {
                return;
            }
            if (1 < \count($attributes)) {
                throw new LogicException(sprintf('The #[Factory] attribute can be used only once on "%s".', $class->name));
            }

            // Prevent using both #[Factory] and #[Autoconfigure(constructor:...)]
            $autoconfigureWithConstructor = array_filter(
                $class->getAttributes(Autoconfigure::class, \ReflectionAttribute::IS_INSTANCEOF),
                static fn(\ReflectionAttribute $attr) => null !== $attr->newInstance()->constructor
            );
            if (0 !== \count($autoconfigureWithConstructor)) {
                throw new AutoconfigureFailedException($class->name, sprintf('Using both attributes #[Factory] and #[Autoconfigure(constructor: ...)] on is not allowed in %s.', $class->name));
            }

            $attribute = $attributes[0]->newInstance();
            $factory = $attribute->getFactoryForTarget($target);
            $arguments = $attribute->getArguments();
            $definition->setFactory($factory);
            $definition->setArguments($arguments);
        }
    }
}
