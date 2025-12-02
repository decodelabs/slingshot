# Slingshot — Package Specification

> **Cluster:** `runtime`
> **Language:** `php`
> **Milestone:** `m1`
> **Repo:** `https://github.com/decodelabs/slingshot`
> **Role:** Dependency injection invoker

## Overview

### Purpose

Slingshot provides a simple interface for invoking methods on objects with dependency injection. It unifies dependency resolution from multiple sources (containers, type registries, Archetype, and direct instantiation) into a single, consistent API.

Key features:
- **Unified invocation**: Invoke functions and methods with automatic dependency injection
- **Multiple resolution sources**: Resolve dependencies from containers, type registries, Archetype, or direct instantiation
- **Type-aware**: Automatic type matching and validation
- **Recursive resolution**: Automatically resolves constructor dependencies recursively
- **Named instances**: Support for named instance resolution via Archetype
- **Service integration**: Special handling for Kingdom services with lazy loading support

### Non-Goals

- Slingshot does not provide a full dependency injection container (uses PSR-11 containers).
- It does not handle service lifecycle management or scoping.
- It does not provide configuration-based dependency mapping.
- It does not handle circular dependency detection beyond recursion limits.
- It does not provide AOP or method interception capabilities.

## Role in the Ecosystem

### Cluster & Positioning

Slingshot belongs to the **runtime** cluster, focusing on dependency injection and object instantiation. It complements other runtime packages like `kingdom` (service container) and `archetype` (class resolution) by providing a unified invocation layer.

### Usage Contexts

- **Method invocation**: Invoking methods with automatic dependency injection
- **Object instantiation**: Creating objects with constructor dependency injection
- **Service resolution**: Resolving services from containers and type registries
- **Named instance resolution**: Resolving named implementations via Archetype
- **Function invocation**: Invoking functions with dependency injection

## Public Surface

### Key Types

- **`Slingshot`** (class): Main class providing dependency injection invocation capabilities. Manages parameters, type registries, container integration, and Archetype resolution.

### Main Entry Points

**Construction:**
- `new Slingshot(?Container $container = null, array $parameters = [], array $types = [], ?Archetype $archetype = null)` — Constructor
- Container defaults to Kingdom container if available via Monarch
- Archetype defaults to container or Monarch service if available

**Parameter Management:**
- `$slingshot->setParameters(array $parameters): static` — Set all parameters
- `$slingshot->addParameters(array $parameters): static` — Add parameters
- `$slingshot->setParameter(string $name, mixed $value): static` — Set single parameter
- `$slingshot->getParameter(string $name): mixed` — Get parameter
- `$slingshot->hasParameter(string $name): bool` — Check if parameter exists
- `$slingshot->removeParameter(string $name): static` — Remove parameter
- `$slingshot->hasParameters(): bool` — Check if has parameters
- `$slingshot->getParameters(): array` — Get all parameters

**Type Management:**
- `$slingshot->setTypes(array $types): static` — Set all types
- `$slingshot->addTypes(array $types): static` — Add types
- `$slingshot->addType(object $type, ?string $interface = null): static` — Add single type
- `$slingshot->getType(string $type): ?object` — Get type by class name
- `$slingshot->hasType(string $type): bool` — Check if type exists
- `$slingshot->hasTypes(): bool` — Check if has types
- `$slingshot->getTypes(): array` — Get all types

**Invocation:**
- `$slingshot->invoke(callable $function, array $parameters = []): mixed` — Invoke callable with dependency injection
- `$slingshot->newInstance(string $class, array $parameters = []): object` — Create new instance with dependency injection
- `$slingshot->resolveInstance(string $class, array $parameters = []): object` — Resolve instance (container → type → new instance)
- `$slingshot->resolveNamedInstance(string $interface, string $name, array $parameters = []): object` — Resolve named instance (throws if not found)
- `$slingshot->tryResolveNamedInstance(string $interface, string $name, array $parameters = []): ?object` — Resolve named instance (returns null if not found)

**Properties:**
- `$slingshot->container` — PSR-11 container (nullable, public)
- `$slingshot->archetype` — Archetype instance (lazy-loaded, readonly property)

## Dependencies

### Decode Labs

- **`decodelabs/archetype`**: Used for class resolution and named instance resolution.
- **`decodelabs/exceptional`**: Used for exception handling throughout the package.
- **`decodelabs/kingdom`**: Used for service container integration (`ContainerAdapter`, `Service`, `PureService`, `EagreService`).
- **`decodelabs/monarch`**: Used for service location (Kingdom container and Archetype).

### External

- **PHP**: See `composer.json` for supported PHP versions.
- **`psr/container`**: PSR-11 container interfaces.

## Behaviour & Contracts

### Invariants

- Parameter names are normalized (non-alphanumeric characters converted to camelCase).
- Type registry uses class names as keys (with optional interface mapping).
- Recursion depth is limited to prevent infinite loops (default: 1000).
- Type checking validates values against parameter types before assignment.
- Objects added as parameters are automatically added to type registry.

### Input & Output Contracts

**Parameter Resolution Order:**
1. Direct parameter match (by name)
2. Type registry match (by class name or interface)
3. Self reference (`Slingshot` instance)
4. Container lookup (if `ContainerAdapter` or PSR-11 container)
5. Service resolution (if `Service` interface, with lazy loading)
6. Nullable types (if allowed and no default)
7. Archetype resolution (for interfaces/abstract classes)
8. Default parameter values
9. Null (if type allows)
10. Direct instantiation (if class is instantiable)

**Type Checking:**
- Built-in types: `int`, `float`, `string`, `bool`, `array`, `object`, `iterable`, `callable`, `resource`, `mixed`
- Class types: Uses `instanceof` check
- Union types: Checks all types in union
- Intersection types: Not supported (throws exception)
- Nullable types: Allows `null` if type allows

**Parameter Normalization:**
- Parameter names are normalized: non-alphanumeric → camelCase
- Integer keys are converted to parameter names from reflection
- Variadic parameters consume remaining parameters

**Service Resolution:**
- `Service` types are resolved via `provideService()` method
- `PureService` types use `providePureService()` static method
- `EagreService` types are eagerly initialized
- Lazy proxies are created for non-eager services when using `ContainerAdapter`

**Named Instance Resolution:**
- Named instances are resolved via Archetype
- Name matching uses class short name comparison (case-insensitive)
- Falls back to type registry and parameter array if Archetype resolution fails

**Recursion Protection:**
- Recursion depth tracked via static counter
- Maximum recursion depth: 1000
- Throws `Runtime` exception if limit exceeded

## Error Handling

- **Maximum recursion**: `invoke()` throws `Runtime` exception if recursion depth exceeds limit.
- **Intersection types**: Throws `Implementation` exception for intersection type parameters.
- **Type mismatch**: Throws `Definition` exception if parameter value doesn't match expected type.
- **Unresolvable parameter**: Throws `Definition` exception if parameter cannot be resolved.
- **Non-instantiable class**: Throws `Logic` exception if class cannot be instantiated.
- **Non-public constructor**: Throws `Logic` exception if constructor is not public.
- **Class not found**: Throws `Logic` exception if class doesn't exist (unless resolved via Archetype).
- **Named instance not found**: `resolveNamedInstance()` throws `NotFound` exception if instance cannot be resolved.

## Configuration & Extensibility

### Custom Parameter Sources

Add parameters directly or via type registry:

```php
$slingshot = new Slingshot();
$slingshot->setParameter('myParam', 'value');
$slingshot->addType($myObject);
```

### Custom Type Resolution

Register types for automatic resolution:

```php
$slingshot = new Slingshot();
$slingshot->addType($myService, MyInterface::class);
$slingshot->addType($myService); // Also registered by class name
```

### Custom Container Integration

Use any PSR-11 container:

```php
$slingshot = new Slingshot($myContainer);
```

Or use Kingdom `ContainerAdapter` for enhanced features:

```php
use DecodeLabs\Kingdom\ContainerAdapter;

$slingshot = new Slingshot($containerAdapter);
// Enables lazy service loading and enhanced resolution
```

### Custom Archetype Integration

Provide custom Archetype instance:

```php
use DecodeLabs\Archetype;

$archetype = new Archetype();
$slingshot = new Slingshot(archetype: $archetype);
```

## Interactions with Other Packages

- **Kingdom**: Used for service container integration. `ContainerAdapter` enables lazy service loading and enhanced resolution. Services implementing `Service`, `PureService`, or `EagreService` are automatically resolved.
- **Archetype**: Used for class resolution and named instance resolution. Interfaces and abstract classes can be resolved to concrete implementations.
- **Monarch**: Used for service location (Kingdom container and Archetype) if not provided explicitly.
- **Exceptional**: Used for all exception handling.

## Usage Examples

### Basic Function Invocation

```php
use DecodeLabs\Slingshot;

$slingshot = new Slingshot(
    container: $container,
    parameters: [
        'param1' => 'hello'
    ]
);

function test(
    string $param1,
    string $param2
) {
    return $param1 . ' ' . $param2;
}

$output = $slingshot->invoke(test(...), [
    'param2' => 'world'
]); // 'hello world'
```

### Object Instantiation

```php
use DecodeLabs\Slingshot;
use DecodeLabs\Harvest;

class Test {
    public function __construct(
        Harvest $harvest,
        string $param2
    ) {
        // ...
    }
}

$slingshot = new Slingshot($container);
$testObject = $slingshot->newInstance(Test::class, [
    'param2' => 'value'
]);
```

### Type Registry

```php
use DecodeLabs\Slingshot;

$object = new Test(...);
$slingshot = new Slingshot();
$slingshot->addType($object);

$slingshot->invoke(function(Test $test) {
    // $test is automatically resolved from type registry
});
```

### Service Resolution

```php
use DecodeLabs\Slingshot;
use DecodeLabs\Kingdom\ContainerAdapter;

$slingshot = new Slingshot($containerAdapter);

// Service is resolved from container or created via provideService()
$slingshot->invoke(function(MyService $service) {
    // Service automatically resolved
});
```

### Named Instance Resolution

```php
use DecodeLabs\Slingshot;

$slingshot = new Slingshot($container);

// Resolve named implementation via Archetype
$verifier = $slingshot->resolveNamedInstance(
    Verifier::class,
    'Recaptcha',
    ['siteKey' => '...', 'secret' => '...']
);
```

### Parameter Normalization

```php
use DecodeLabs\Slingshot;

$slingshot = new Slingshot();
$slingshot->setParameter('my-param', 'value');

// Parameter name normalized to 'myParam'
$slingshot->invoke(function(string $myParam) {
    // $myParam = 'value'
});
```

### Recursive Resolution

```php
use DecodeLabs\Slingshot;

class ServiceA {
    public function __construct(ServiceB $b) {}
}

class ServiceB {
    public function __construct(ServiceC $c) {}
}

class ServiceC {
    public function __construct() {}
}

$slingshot = new Slingshot($container);
// Automatically resolves ServiceC → ServiceB → ServiceA
$serviceA = $slingshot->newInstance(ServiceA::class);
```

### Union Types

```php
use DecodeLabs\Slingshot;

$slingshot = new Slingshot();
$slingshot->setParameter('value', 'string');

$slingshot->invoke(function(string|int $value) {
    // Union type supported, value checked against all types
});
```

### Nullable Types

```php
use DecodeLabs\Slingshot;

$slingshot = new Slingshot();

// Nullable parameter resolved to null if not provided
$slingshot->invoke(function(?string $optional = null) {
    // $optional = null
});
```

## Implementation Notes (for Contributors)

### Parameter Resolution Algorithm

1. Check direct parameter match by normalized name
2. Check type registry for exact class match
3. Check type registry for instanceof match
4. Check self reference (`Slingshot` instance)
5. Check container (if `ContainerAdapter` or PSR-11)
6. Check service resolution (if `Service` interface)
7. Check nullable types (if allowed)
8. Check Archetype resolution (for interfaces/abstract classes)
9. Check default parameter values
10. Check null (if type allows)
11. Check direct instantiation (if class is instantiable)

### Type Checking

- Built-in types use strict type checking (`is_int()`, `is_string()`, etc.)
- Class types use `instanceof` check
- Union types check all types in union (OR logic)
- Intersection types not supported (throws exception)
- Nullable types allow `null` if type allows

### Parameter Normalization

- Non-alphanumeric characters converted to camelCase
- Example: `my-param` → `myParam`, `my_param` → `myParam`
- Integer keys converted to parameter names from reflection

### Service Resolution

- `Service` types resolved via `provideService()` method
- `PureService` types use static `providePureService()` method
- `EagreService` types eagerly initialized
- Lazy proxies created for non-eager services when using `ContainerAdapter`
- Reflection used to create lazy proxy closures

### Recursion Protection

- Static counter tracks recursion depth
- Incremented on `invoke()` entry
- Decremented on `invoke()` exit (via finally block)
- Maximum depth: 1000 (configurable via constant)

### Named Instance Resolution

- Name matching uses class short name comparison (case-insensitive)
- Archetype resolution uses name as hint
- Falls back to type registry and parameter array
- Throws `NotFound` exception if resolution fails (in `resolveNamedInstance()`)

### Constructor Invocation

- Object created via `newInstanceWithoutConstructor()`
- Constructor closure obtained via reflection
- Constructor invoked via `invoke()` for recursive dependency resolution
- Public constructor required (throws exception if not public)

## Testing & Quality

**Current Status:**
- Code quality: 4.5/5
- README quality: 3/5
- Documentation: 0/5 (no formal docs yet)
- Tests: 0/5 (no test suite yet)

**Testing Considerations:**
- Parameter resolution should be tested for:
  - Direct parameter matching
  - Type registry matching
  - Container resolution
  - Service resolution
  - Archetype resolution
  - Default values
  - Nullable types
  - Direct instantiation

- Type checking should be tested for:
  - Built-in types
  - Class types
  - Union types
  - Nullable types
  - Intersection types (should throw)

- Parameter normalization should be tested for:
  - Various naming formats
  - Integer key conversion
  - Variadic parameter handling

- Service resolution should be tested for:
  - `Service` interface
  - `PureService` interface
  - `EagreService` interface
  - Lazy loading
  - Container adapter integration

- Named instance resolution should be tested for:
  - Archetype resolution
  - Type registry fallback
  - Parameter array fallback
  - Not found handling

- Recursion protection should be tested for:
  - Maximum depth enforcement
  - Stack tracking
  - Exception handling

- Edge cases should be tested for:
  - Circular dependencies (should hit recursion limit)
  - Missing dependencies (should throw)
  - Type mismatches (should throw)
  - Non-instantiable classes (should throw)
  - Non-public constructors (should throw)

## Roadmap & Future Ideas

- **Circular dependency detection**: Better detection and handling of circular dependencies
- **Configuration-based mapping**: Support for configuration-based dependency mapping
- **Service scoping**: Support for service scoping (singleton, transient, etc.)
- **Method interception**: Support for AOP-style method interception
- **Performance optimization**: Caching of reflection data and resolution results
- **Better error messages**: More detailed error messages for resolution failures
- **Dependency graph visualization**: Tools for visualizing dependency graphs

## References

- Package repository: https://github.com/decodelabs/slingshot
- Composer package: https://packagist.org/packages/decodelabs/slingshot
- PSR-11: https://www.php-fig.org/psr/psr-11/ (Container Interface)
- Related packages:
  - `decodelabs/archetype` — Class resolution
  - `decodelabs/kingdom` — Service container
  - `decodelabs/monarch` — Service location
  - `decodelabs/exceptional` — Exception handling

