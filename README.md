# Slingshot

[![PHP from Packagist](https://img.shields.io/packagist/php-v/decodelabs/slingshot?style=flat)](https://packagist.org/packages/decodelabs/slingshot)
[![Latest Version](https://img.shields.io/packagist/v/decodelabs/slingshot.svg?style=flat)](https://packagist.org/packages/decodelabs/slingshot)
[![Total Downloads](https://img.shields.io/packagist/dt/decodelabs/slingshot.svg?style=flat)](https://packagist.org/packages/decodelabs/slingshot)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/decodelabs/slingshot/integrate.yml?branch=develop)](https://github.com/decodelabs/slingshot/actions/workflows/integrate.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-44CC11.svg?longCache=true&style=flat)](https://github.com/phpstan/phpstan)
[![License](https://img.shields.io/packagist/l/decodelabs/slingshot?style=flat)](https://packagist.org/packages/decodelabs/slingshot)

### Unified dependency injection invoker

Slingshot provides a simple interface for invoking methods on objects with dependency injection.

_Get news and updates on the [DecodeLabs blog](https://blog.decodelabs.com)._

---

## Installation

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
use DecodeLabs\Harvest\Context as HarvestContext;

class Test {
    public function __construct(
        // Fetch or create a Harvest Context (example) from container
        HarvestContext $param1,
        string $param2
    ) {
        // ...
    }
}

$testObect = $slingshot->instantiate(Test::class, [
    'param2' => 'value'
]);
```

## Licensing

Slingshot is licensed under the MIT License. See [LICENSE](./LICENSE) for the full license text.
