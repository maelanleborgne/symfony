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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\AutoconfigureFailedException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * Register factories for services tagged with "container.factory".
 *
 * @author Maelan Le Borgne <maelan.leborgne@gmail.com>
 */
final class RegisterFactoryPass implements CompilerPassInterface
{
    private static \Closure $registerForAutoconfiguration;

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('container.factory') as $id => $attributes) {
            $definition = $container->getDefinition($id);
            if (!$definition->hasTag('container.ignore_attributes')) {
                $this->processDefinitionOption1($container, $definition, $attributes[0]);
            }
        }
    }

    // Option 1 : Register the factory directly on the definition.
    // May require more work to maintain because it duplicates part of the factory setting done in the file loaders
    private function processDefinitionOption1(ContainerBuilder $container, Definition $definition, array $attributes): void
    {
        $class = $container->getReflectionClass($definition->getClass(), false);
        if (null === $class) {
            return;
        }
        // Prevent conflict with #[Autoconfigure(constructor: ...)]
        $autoconfigureWithConstructor = array_filter(
            $class->getAttributes(Autoconfigure::class, \ReflectionAttribute::IS_INSTANCEOF),
            static fn(\ReflectionAttribute $attribute) => null !== $attribute->newInstance()->constructor
        );
        if (0 < \count($autoconfigureWithConstructor)) {
            throw new AutoconfigureFailedException($class->name, sprintf('Using both attributes #[Factory] and #[Autoconfigure(constructor: ...)] on is not allowed in %s.', $class->getName()));
        }

        $factory = $this->resolveFactoryForOption1($attributes, $class);
        if (\is_array($factory)) {
            if (null === $factory[0] && !is_callable([$class->name, $factory[1]]) || is_string($factory[0]) && !is_callable($factory)) {
                throw new \InvalidArgumentException(sprintf('Invalid factory method "%s" on service "%s": method does not exist or is not static.', $factory[1], $definition->getClass()));
            }
        }

        $definition->setFactory($factory)
            ->setArguments($attributes['arguments'] ?? []);
    }

    private function resolveFactoryForOption1(array $attributes, \ReflectionClass $class): array|string
    {
        if (is_string($attributes['expression'] ?? null)) {
            if (!class_exists(Expression::class)) {
                throw new \LogicException(sprintf('The "container.factory" tag on %s cannot declare an "expression" when the ExpressionLanguage component is not available.', $class->name));
            }

            return '@=' . $attributes['expression'];
        }

        $serviceReference = is_string($attributes['service'] ?? null) ? new Reference($attributes['service']) : null;

        return [$attributes['class'] ?? $serviceReference, $attributes['method'] ?? '__invoke'];
    }

    // Option 2 : Rely on YamlFileLoader to register the factory like in RegisterAutoconfigureAttributesPass.
    // Requires to add 'factory' and 'arguments' to the YamlFileLoader::INSTANCEOF_KEYWORDS const.
    private function processDefinitionOption2(ContainerBuilder $container, Definition $definition, array $attributes): void
    {
        $class = $container->getReflectionClass($definition->getClass(), false);
        if (null === $class) {
            return;
        }
        // Prevent conflict with #[Autoconfigure(constructor: ...)]
        $autoconfigureWithConstructor = array_filter(
            $class->getAttributes(Autoconfigure::class, \ReflectionAttribute::IS_INSTANCEOF),
            static fn(\ReflectionAttribute $attribute) => null !== $attribute->newInstance()->constructor
        );
        if (0 < \count($autoconfigureWithConstructor)) {
            throw new AutoconfigureFailedException($class->name, sprintf('Using both attributes #[Factory] and #[Autoconfigure(constructor: ...)] on is not allowed in %s.', $class->getName()));
        }

        $factory = $this->resolveFactoryForOption2($attributes, $class);

        if (isset(self::$registerForAutoconfiguration)) {
            (self::$registerForAutoconfiguration)($container, $class, $factory, $attributes['arguments'] ?? []);

            return;
        }

        $parseDefinitions = new \ReflectionMethod(YamlFileLoader::class, 'parseDefinitions');
        $yamlLoader = $parseDefinitions->getDeclaringClass()->newInstanceWithoutConstructor();

        self::$registerForAutoconfiguration = static function (ContainerBuilder $container, \ReflectionClass $class, array $factory, array $arguments) use ($parseDefinitions, $yamlLoader) {
            $config = ['factory' => $factory, 'arguments' => $arguments];

            $parseDefinitions->invoke(
                $yamlLoader,
                [
                    'services' => [
                        '_instanceof' => [
                            $class->name => [$container->getDefinition($class->name)] + $config,
                        ],
                    ],
                ],
                $class->getFileName(),
                false
            );
        };

        (self::$registerForAutoconfiguration)($container, $class, $factory, $attributes['arguments'] ?? []);
    }

    private function resolveFactoryForOption2(array $attributes, \ReflectionClass $class): array|string
    {
        if (is_string($attributes['expression'] ?? null)) {
            if (!class_exists(Expression::class)) {
                throw new \LogicException(sprintf('The "container.factory" tag on %s cannot declare an "expression" when the ExpressionLanguage component is not available.', $class->name));
            }

            return '@=' . $attributes['expression'];
        }

        $method = $attributes['method'] ?? '__invoke';
        $service = is_string($attributes['service'] ?? null) ? '@' . $attributes['service'] : null;

        return [$attributes['class'] ?? $service, $method];
    }
}
