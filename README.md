# Laravel 5 - Symfony Form integration
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/9a5bdc90-58b2-428c-9294-746ddbe03026/big.png)](https://insight.sensiolabs.com/projects/9a5bdc90-58b2-428c-9294-746ddbe03026)

This integration based on Silex implementation of Symfony Form.
This package also works out of the box with [Twig](https://github.com/rcrowe/TwigBridge) &
[Doctrine](http://www.laraveldoctrine.org/)


## Documentation
For detailed documentation, please check this (WIP)

###Installation

``` json
{
    "require": {
        "hnd/sf-form": "dev-master"
    }
}
```

Run `composer update`

Publish configuration with `php artisan vendor:publish --tag="config"`

Then add Service provider to `config/app.php`

``` php
    'providers' => [
        // ...
        HND\SymfonyForm\ServiceProvider::class
    ]
```

And Facade (also in `config/app.php`)

``` php
    'aliases' => [
        // ...
        'FormFactory'   => \HND\SymfonyForm\Facades\FormFactory::class,
    ]

```

### Quick start
