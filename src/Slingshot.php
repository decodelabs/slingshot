<?php

/**
 * @package Slingshot
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs;

use Closure;
use DecodeLabs\Kingdom\ContainerAdapter;
use DecodeLabs\Kingdom\EagreService;
use DecodeLabs\Kingdom\PureService;
use DecodeLabs\Kingdom\Service;
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
    protected const MaxRecursion = 1000;

    protected static int $stack = 0;

    /**
     * @var array<string,mixed>
     */
    protected array $parameters = [];

    /**
     * @var array<class-string<object>,object>
     */
    protected array $types = [];

    public ?Container $container = null;

    public Archetype $archetype {
        get {
            // @phpstan-ignore-next-line
            return $this->archetype ??=
                $this->container instanceof ContainerAdapter
                    ? $this->container->get(Archetype::class)
                    : Monarch::getService(Archetype::class);
        }
    }

    /**
     * @template T of object
     * @param array<string,mixed> $parameters
     * @param array<int|class-string<T>,T> $types
     */
    public function __construct(
        ?Container $container = null,
        array $parameters = [],
        array $types = [],
        ?Archetype $archetype = null
    ) {
        if (
            $container === null &&
            Monarch::hasKingdom()
        ) {
            $container = Monarch::getKingdom()->container;

            if (!$container instanceof Container) {
                $container = $container->getPsrContainer();
            }
        }

        $this->container = $container;
        $this->parameters = $parameters;

        if (!empty($types)) {
            $this->setTypes($types);
        }

        if ($archetype !== null) {
            $this->archetype = $archetype;
        }
    }


    /**
     * @param array<string,mixed> $parameters
     * @return $this
     */
    public function setParameters(
        array $parameters
    ): static {
        $this->parameters = [];
        $this->addParameters($parameters);
        return $this;
    }

    /**
     * @param array<string,mixed> $parameters
     * @return $this
     */
    public function addParameters(
        array $parameters
    ): static {
        foreach ($parameters as $key => $value) {
            $this->setParameter($key, $value);
        }

        return $this;
    }

    public function hasParameters(): bool
    {
        return !empty($this->parameters);
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }



    /**
     * @return $this
     */
    public function setParameter(
        string $name,
        mixed $value
    ): static {
        $name = $this->normalizeParameterName($name);
        $this->parameters[$name] = $value;

        // Add as type
        if (
            is_object($value) &&
            !$this->hasType(get_class($value))
        ) {
            $this->addType($value);
        }

        return $this;
    }

    public function getParameter(
        string $name
    ): mixed {
        $name = $this->normalizeParameterName($name);
        return $this->parameters[$name] ?? null;
    }

    public function hasParameter(
        string $name
    ): bool {
        $name = $this->normalizeParameterName($name);
        return array_key_exists($name, $this->parameters);
    }

    /**
     * @return $this
     */
    public function removeParameter(
        string $name
    ): static {
        $name = $this->normalizeParameterName($name);
        unset($this->parameters[$name]);
        return $this;
    }

    protected function normalizeParameterName(
        string $name
    ): string {
        $name = (string)preg_replace('/[^a-zA-Z0-9_]/', '-', $name);
        $parts = explode('-', $name);
        $parts = array_map('ucfirst', $parts);
        return lcfirst(implode('', $parts));
    }

    /**
     * @param array<string,mixed> $parameters
     * @return array<string,mixed>
     */
    protected function normalizeParameters(
        array $parameters
    ): array {
        $params = $parameters;
        $parameters = $this->parameters;

        foreach ($params as $key => $value) {
            $key = $this->normalizeParameterName((string)$key);
            $parameters[$key] = $value;
        }

        return $parameters;
    }



    /**
     * @template T of object
     * @param array<int|class-string<T>,T> $types
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
     * @template T of object
     * @param array<int|class-string<T>,T> $types
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

    public function hasTypes(): bool
    {
        return !empty($this->types);
    }

    /**
     * @return array<class-string<object>, object>
     */
    public function getTypes(): array
    {
        return $this->types;
    }


    /**
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
     * @param class-string<object> $type
     */
    public function hasType(
        string $type
    ): bool {
        return isset($this->types[$type]);
    }

    /**
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
     * @template T
     * @param callable():T $function
     * @param array<string,mixed> $parameters
     * @return T
     */
    public function invoke(
        callable $function,
        array $parameters = []
    ): mixed {
        if (++self::$stack > self::MaxRecursion) {
            throw Exceptional::Runtime(
                message: 'Maximum recursion depth reached'
            );
        }

        if (!$function instanceof Closure) {
            $function = Closure::fromCallable($function);
        }

        $ref = new ReflectionFunction($function);
        $args = [];

        $parameters = $this->normalizeParameters($parameters);
        $variadicParams = $parameters;

        foreach ($ref->getParameters() as $i => $param) {
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


            if ($type instanceof ReflectionIntersectionType) {
                self::$stack--;
                throw Exceptional::Implementation(
                    message: 'Intersection types are not supported - param: ' . $name
                );
            }

            if ($param->isVariadic()) {
                $args += $variadicParams;
                break;
            }


            // Parameter value
            if (
                array_key_exists($name, $parameters) &&
                $this->checkType($parameters[$name], $type)
            ) {
                $args[$name] = $parameters[$param->getName()];
                unset($variadicParams[$name]);
                continue;
            }


            // Type
            if ($typeName !== null) {
                if (isset($this->types[$typeName])) {
                    $args[$name] = $this->types[$typeName];
                    continue;
                }

                foreach ($this->types as $value) {
                    if (is_a($value, $typeName)) {
                        $args[$name] = $value;
                        continue 2;
                    }
                }
            }



            // Self
            if ($typeName === self::class) {
                $args[$name] = $this;
                continue;
            }


            if ($typeName !== null) {
                // Container
                if ($this->container instanceof ContainerAdapter) {
                    if ($this->container->has($typeName)) {
                        $args[$name] = $this->container->get($typeName);
                        continue;
                    }

                    if (is_a($typeName, Service::class, true)) {
                        $ref = new ReflectionClass($typeName);
                        $container = $this->container;

                        $args[$name] = $ref->newLazyProxy(
                            function () use ($typeName, $container) {
                                if (is_a($typeName, PureService::class, true)) {
                                    return $typeName::providePureService();
                                }

                                return $typeName::provideService($container);
                            }
                        );

                        if (is_a($typeName, EagreService::class, true)) {
                            $ref->initializeLazyObject($args[$name]);
                        }

                        continue;
                    }

                    if (null !== ($value = $this->container->tryGet($typeName))) {
                        $args[$name] = $value;
                        continue;
                    }
                } elseif (
                    $this->container?->has($typeName) &&
                    null !== ($value = $this->container->get($typeName)) &&
                    $this->checkType($value, $type)
                ) {
                    $args[$name] = $value;
                    continue;
                }


                // Nullable
                if (
                    $type !== null &&
                    $type->allowsNull() &&
                    !$param->isDefaultValueAvailable()
                ) {
                    $args[$name] = null;
                    continue;
                }



                // Archetype
                if ($class = $this->archetype->tryResolve(
                    $typeName,
                    [$name, null]
                )) {
                    try {
                        $args[$name] = $this->newInstance($class);
                    } catch (Throwable $e) {
                        self::$stack--;
                        throw $e;
                    }
                    continue;
                }
            }


            // Default value
            if ($param->isDefaultValueAvailable()) {
                $args[$name] = $param->getDefaultValue();
                continue;
            }


            // Null
            if (
                $type !== null &&
                $type->allowsNull()
            ) {
                $args[$name] = null;
                continue;
            }


            // New instance
            if (
                $typeName !== null &&
                (new ReflectionClass($typeName))->isInstantiable()
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
                    message: 'Parameter $' . $name . ' is not type compatible'
                );
            }

            throw Exceptional::Definition(
                message: 'Unable to resolve constructor parameter ' . (string)$type . ' $' . $param->getName(),
                data: $function
            );
        }

        return $function(...$args);
    }


    protected function checkType(
        mixed $value,
        ?ReflectionType $type
    ): bool {
        if (
            $type === null ||
            $type instanceof ReflectionIntersectionType
        ) {
            return true;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $innerType) {
                if ($this->checkType($value, $innerType)) {
                    return true;
                }
            }

            return false;
        }

        if (!$type instanceof ReflectionNamedType) {
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

            case 'mixed':
                return true;
        }

        return false;
    }


    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string,mixed> $parameters
     * @return T
     */
    public function resolveInstance(
        string $class,
        array $parameters = []
    ): object {
        if ($this->container?->has($class)) {
            $output = $this->container->get($class);

            if ($output instanceof $class) {
                return $output;
            }
        }

        if (
            is_a($class, Service::class, true) &&
            $this->container instanceof ContainerAdapter
        ) {
            if ($this->container->has($class)) {
                // @phpstan-ignore-next-line
                return $this->container->get($class);
            }

            return $class::provideService($this->container);
        }

        if ($output = $this->getType($class)) {
            return $output;
        }

        foreach ($this->normalizeParameters($parameters) as $parameter) {
            if ($parameter instanceof $class) {
                return $parameter;
            }
        }

        return $this->newInstance($class, $parameters);
    }

    /**
     * @template T of object
     * @param class-string<T> $interface
     * @param array<string,mixed> $parameters
     * @return T
     */
    public function resolveNamedInstance(
        string $interface,
        string $name,
        array $parameters = []
    ): object {
        $output = $this->tryResolveNamedInstance($interface, $name, $parameters);

        if ($output === null) {
            throw Exceptional::Runtime(
                message: 'Unable to resolve named instance ' . $name . ' for interface ' . $interface
            );
        }

        return $output;
    }

    /**
     * @template T of object
     * @param class-string<T> $interface
     * @param array<string,mixed> $parameters
     * @return T
     */
    public function tryResolveNamedInstance(
        string $interface,
        string $name,
        array $parameters = []
    ): ?object {
        if (
            ($output = $this->getType($interface)) &&
            new ReflectionClass($output)->getShortName() === ucfirst($name)
        ) {
            return $output;
        }

        foreach ($this->normalizeParameters($parameters) as $parameter) {
            if (
                $parameter instanceof $interface &&
                new ReflectionClass($parameter)->getShortName() === ucfirst($name)
            ) {
                return $parameter;
            }
        }

        $class = $this->archetype->tryResolve($interface, $name);

        if ($class === null) {
            return null;
        }

        return $this->resolveInstance($class, $parameters);
    }


    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string,mixed> $parameters
     * @return T
     */
    public function newInstance(
        string $class,
        array $parameters = []
    ): object {
        if (!class_exists($class)) {
            if (!interface_exists($class)) {
                throw Exceptional::Logic(
                    message: 'Class ' . $class . ' does not exist'
                );
            }

            $class = $this->archetype->resolve($class);
        }

        $ref = new ReflectionClass($class);

        if (!$ref->isInstantiable()) {
            throw Exceptional::Logic(
                message: 'Class ' . $class . ' is not instantiable'
            );
        }

        $output = $ref->newInstanceWithoutConstructor();

        if ($ref->hasMethod('__construct')) {
            $constructor = $ref->getMethod('__construct');

            if (!$constructor->isPublic()) {
                throw Exceptional::Logic(
                    message: 'Class ' . $class . ' has a non-public constructor'
                );
            }

            // Docs mismatch
            // @phpstan-ignore-next-line
            if (!$closure = $constructor->getClosure($output)) {
                throw Exceptional::Logic(
                    message: 'Unable to get closure for constructor of ' . $class
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
