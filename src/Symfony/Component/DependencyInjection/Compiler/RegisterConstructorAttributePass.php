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
use Symfony\Component\DependencyInjection\Attribute\Constructor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\AutoconfigureFailedException;

/**
 * Reads #[Constructor] attributes on definitions that are autoconfigured
 * and don't have the "container.ignore_attributes" tag.
 *
 * @author Maelan Le Borgne <maelan.leborgne@gmail.com>
 */
final class RegisterConstructorAttributePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($this->accept($definition)) {
                $this->processDefinition($container, $definition);
            }
        }
    }

    private function accept(Definition $definition): bool
    {
        return $definition->isAutoconfigured() && !$definition->hasTag('container.ignore_attributes');
    }

    private function processDefinition(ContainerBuilder $container, Definition $definition): void
    {
        if (null === $class = $container->getReflectionClass($definition->getClass(), false)) {
            return;
        }

        if (null === $constructorMethod = $this->getConstructorNameForClass($class)) {
            return;
        }

        foreach ($class->getAttributes(Autoconfigure::class, \ReflectionAttribute::IS_INSTANCEOF) as $autoconfigure) {
            if ($autoconfigure->newInstance()->constructor) {
                throw new AutoconfigureFailedException($class->name, sprintf('Using both attributes #[Constructor] and #[Autoconfigure(constructor:...)] is not allowed in %s.', $class->name));
            }
        }

        $definition->setFactory([$class->name, $constructorMethod]);
    }

    private function getConstructorNameForClass(\ReflectionClass $class): ?string
    {
        $constructorMethods = array_filter(
            $class->getMethods(\ReflectionMethod::IS_STATIC),
            fn ($method): bool => 0 < count($method->getAttributes(Constructor::class, \ReflectionAttribute::IS_INSTANCEOF))
        );
        if (1 < count($constructorMethods)) {
            throw new AutoconfigureFailedException($class->name, sprintf('Multiple #[Constructor] attributes are not allowed on the same service in %s.', $class->name));
        }
        if (0 === count($constructorMethods)) {
            return null;
        }

        return array_values($constructorMethods)[0]->name;
    }
}
