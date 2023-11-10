<?php

/**
 * @package Slingshot
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs;

use Closure;
use DecodeLabs\Pandora\Container as PandoraContainer;
use Psr\Container\ContainerInterface as Container;
use ReflectionClass;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

class Slingshot
{
    public const MAX_RECURSION = 1000;

    protected static int $stack = 0;

    protected ?Container $container = null;

    /**
     * @var array<string, mixed>
     */
    protected array $parameters = [];

    /**
     * @var array<class-string<object>, object>
     */
    protected array $types = [];

    /**
     * Init with container
     *
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        ?Container $container = null,
        array $parameters = []
    ) {
        if (
            $container === null &&
            class_exists(Genesis::class) &&
            isset(Genesis::$container)
        ) {
            $container = Genesis::$container;
        }

        $this->container = $container;
        $this->parameters = $parameters;
    }


    /**
     * Set container
     *
     * @return $this
     */
    public function setContainer(
        ?Container $container
    ): static {
        $this->container = $container;
        return $this;
    }

    /**
     * Get container
     */
    public function getContainer(): ?Container
    {
        return $this->container;
    }

    /**
     * Set parameters
     *
     * @param array<string, mixed> $parameters
     * @return $this
     */
    public function setParameters(
        array $parameters
    ): static {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Add parameters
     *
     * @param array<string, mixed> $parameters
     * @return $this
     */
    public function addParameters(
        array $parameters
    ): static {
        $this->parameters = array_merge(
            $this->parameters,
            $parameters
        );

        return $this;
    }

    /**
     * Has parameters
     */
    public function hasParameters(): bool
    {
        return !empty($this->parameters);
    }

    /**
     * Get parameters
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }



    /**
     * Set parameter
     *
     * @return $this
     */
    public function setParameter(
        string $name,
        mixed $value
    ): static {
        $this->parameters[$name] = $value;
        return $this;
    }

    /**
     * Get parameter
     */
    public function getParameter(
        string $name
    ): mixed {
        return $this->parameters[$name] ?? null;
    }

    /**
     * Has parameter
     */
    public function hasParameter(
        string $name
    ): bool {
        return array_key_exists($name, $this->parameters);
    }

    /**
     * Remove parameter
     *
     * @return $this
     */
    public function removeParameter(
        string $name
    ): static {
        unset($this->parameters[$name]);
        return $this;
    }



    /**
     * Set types
     *
     * @template T of object
     * @param array<int|class-string<T>, T> $types
     * @return $this
     */
    public function setTypes(
        array $types
    ): static {
        $this->types = [];
        $this->addTypes($types);
        return $this;
    }

    /**
     * Add types
     *
     * @template T of object
     * @param array<int|class-string<T>, T> $types
     * @return $this
     */
    public function addTypes(
        array $types
    ): static {
        foreach ($types as $key => $type) {
            if (is_int($key)) {
                $key = get_class($type);
            }

            $this->types[$key] = $type;
        }

        return $this;
    }

    /**
     * Has types
     */
    public function hasTypes(): bool
    {
        return !empty($this->types);
    }

    /**
     * Get types
     *
     * @return array<class-string<object>, object>
     */
    public function getTypes(): array
    {
        return $this->types;
    }


    /**
     * Add type
     *
     * @template T of object
     * @param T $type
     * @param class-string<T> $interface
     */
    public function addType(
        object $type,
        ?string $interface = null
    ): static {
        if ($interface !== null) {
            $this->types[$interface] = $type;
        }

        $this->types[get_class($type)] = $type;
        return $this;
    }

    /**
     * Has type
     *
     * @param class-string<object> $type
     */
    public function hasType(
        string $type
    ): bool {
        return isset($this->types[$type]);
    }

    /**
     * Get type
     *
     * @template T of object
     * @param class-string<T> $type
     * @return T|null
     */
    public function getType(
        string $type
    ): ?object {
        /** @var T|null $output */
        $output = $this->types[$type] ?? null;
        return $output;
    }




    /**
     * Invoke method
     *
     * @template T
     * @param callable():T $function
     * @param array<string, mixed> $parameters
     * @return T
     */
    public function invoke(
        callable $function,
        array $parameters = []
    ): mixed {
        if (++self::$stack > self::MAX_RECURSION) {
            throw Exceptional::Runtime(
                'Maximum recursion depth reached'
            );
        }

        if (!$function instanceof Closure) {
            $function = Closure::fromCallable($function);
        }

        $ref = new ReflectionFunction($function);
        $args = [];
        $parameters = array_merge($this->parameters, $parameters);

        foreach ($ref->getParameters() as $param) {
            $type = $param->getType();
            $name = $param->getName();
            $value = null;

            if (
                $type instanceof ReflectionNamedType &&
                !$type->isBuiltin()
            ) {
                /** @var class-string<object> $typeName */
                $typeName = $type->getName();
            } else {
                $typeName = null;
            }

            if (
                $type instanceof ReflectionUnionType ||
                $type instanceof ReflectionIntersectionType
            ) {
                self::$stack--;
                throw Exceptional::Implementation(
                    'Union and intersection types are not supported'
                );
            }


            // Parameter value
            if (
                array_key_exists($name, $parameters) &&
                $this->checkType($parameters[$name], $type)
            ) {
                $args[$name] = $parameters[$param->getName()];
                continue;
            }


            // Type
            if (
                $typeName !== null &&
                isset($this->types[$typeName])
            ) {
                $args[$name] = $this->types[$typeName];
                continue;
            }


            // Container value
            if (
                $type instanceof ReflectionNamedType &&
                !$type->isBuiltin() &&
                $this->container
            ) {
                if ($this->container instanceof PandoraContainer) {
                    $value = $this->container->tryGet($type->getName());
                } elseif ($this->container->has($type->getName())) {
                    $value = $this->container->get($type->getName());
                }
            }

            if ($value !== null) {
                $args[$name] = $value;
                continue;
            }


            // Archetype
            if (
                $type instanceof ReflectionNamedType &&
                !$type->isBuiltin() &&
                $typeName !== null &&
                $class = Archetype::tryResolve(
                    $typeName,
                    [$name, null]
                )
            ) {
                try {
                    $args[$name] = $this->newInstance($class);
                } catch (Throwable $e) {
                    self::$stack--;
                    throw $e;
                }
                continue;
            }


            // Default value
            if ($param->isDefaultValueAvailable()) {
                $args[$name] = $param->getDefaultValue();
                continue;
            }


            // Null
            if (
                $value === null &&
                $type !== null &&
                $type->allowsNull()
            ) {
                $args[$name] = null;
                continue;
            }


            // New instance
            if (
                $type instanceof ReflectionNamedType &&
                !$type->isBuiltin() &&
                $typeName !== null
            ) {
                try {
                    $args[$name] = $this->newInstance($typeName);
                } catch (Throwable $e) {
                    self::$stack--;
                    throw $e;
                }

                continue;
            }

            self::$stack--;

            if (isset($parameters[$name])) {
                throw Exceptional::Definition(
                    'Parameter $' . $name . ' is not type compatible'
                );
            }

            throw Exceptional::Definition(
                'Unable to resolve constructor parameter $' . $param->getName()
            );
        }

        return $function(...$args);
    }


    /**
     * Check type of value
     */
    protected function checkType(
        mixed $value,
        ?ReflectionType $type
    ): bool {
        if (
            $type === null ||
            !$type instanceof ReflectionNamedType
        ) {
            return true;
        }

        if ($value === null) {
            return $type->allowsNull();
        }

        if (!$type->isBuiltin()) {
            return $value instanceof ($type->getName());
        }

        switch ($type->getName()) {
            case 'int':
                return is_int($value);

            case 'float':
                return is_float($value);

            case 'string':
                return is_string($value);

            case 'bool':
                return is_bool($value);

            case 'array':
                return is_array($value);

            case 'object':
                return is_object($value);

            case 'iterable':
                return is_iterable($value);

            case 'callable':
                return is_callable($value);

            case 'resource':
                return is_resource($value);
        }

        return false;
    }


    /**
     * Create new instance
     *
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $parameters
     * @return T
     */
    public function newInstance(
        string $class,
        array $parameters = []
    ): object {
        if (!class_exists($class)) {
            throw Exceptional::Logic(
                'Class ' . $class . ' does not exist'
            );
        }

        $ref = new ReflectionClass($class);

        if (!$ref->isInstantiable()) {
            throw Exceptional::Logic(
                'Class ' . $class . ' is not instantiable'
            );
        }

        $output = $ref->newInstanceWithoutConstructor();

        if ($ref->hasMethod('__construct')) {
            $constructor = $ref->getMethod('__construct');

            if (!$constructor->isPublic()) {
                throw Exceptional::Logic(
                    'Class ' . $class . ' has a non-public constructor'
                );
            }

            if (!$closure = $constructor->getClosure($output)) {
                throw Exceptional::Logic(
                    'Unable to get closure for constructor of ' . $class
                );
            }

            $this->invoke(
                $closure,
                $parameters
            );
        }

        return $output;
    }
}
