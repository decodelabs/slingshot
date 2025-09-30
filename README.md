# Slingshot

[![PHP from Packagist](https://img.shields.io/packagist/php-v/decodelabs/slingshot?style=flat)](https://packagist.org/packages/decodelabs/slingshot)
[![Latest Version](https://img.shields.io/packagist/v/decodelabs/slingshot.svg?style=flat)](https://packagist.org/packages/decodelabs/slingshot)
[![Total Downloads](https://img.shields.io/packagist/dt/decodelabs/slingshot.svg?style=flat)](https://packagist.org/packages/decodelabs/slingshot)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/decodelabs/slingshot/integrate.yml?branch=develop)](https://github.com/decodelabs/slingshot/actions/workflows/integrate.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-44CC11.svg?longCache=true&style=flat)](https://github.com/phpstan/phpstan)
[![License](https://img.shields.io/packagist/l/decodelabs/slingshot?style=flat)](https://packagist.org/packages/decodelabs/slingshot)

### Unified dependency injection invoker

Slingshot provides a simple interface for invoking methods on objects with dependency injection.

---

## Installation

This package requires PHP 8.4 or higher.

Install via Composer:

```bash
composer require decodelabs/slingshot
```

## Usage

Use a Slingshot instance to invoke a function with dependency injection:

```php
use DecodeLabs\Slingshot;

$slingshot = new Slingshot(
    container: $container, // Psr\Container\ContainerInterface
    parameters: [
        'param1' => 'hello'
    ]
);

function test(
    string $param1,
    string $param2
) {
    return $param1 . ' '. $param2;
}

$output = $slingshot->invoke(test(...), [
    'param2' => 'world'
]); // hello world
```

Or instantiate an object with dependency injection:

```php
use DecodeLabs\Harvest;

class Test {
    public function __construct(
        // Fetch the Harvest service (example) from container
        Harvest $param1,
        string $param2
    ) {
        // ...
    }
}

$testObect = $slingshot->instantiate(Test::class, [
    'param2' => 'value'
]);
```

Objects can be added to Slingshot by type for reference matching:

```php
$object = new Test(...);
$slingshot->addType($object);

$slingshot->invoke(function(Test $test) {
    // ...
});
```

## Licensing

Slingshot is licensed under the MIT License. See [LICENSE](./LICENSE) for the full license text.
