# Using Laminas InputFilter with Static Analysis Tools

It can be tedious to assert that the array returned by a given input filter contains the keys and value types that you would expect before using those values in your domain.
If you use static analysis tools such as Psalm or PHPStan, `InputFilterInterface` defines a generic template that can be used to refine the types you receive from the `getValues()` method.

```php
<?php

namespace My;

use Laminas\Filter\ToInt;
use Laminas\Filter\ToNull;
use Laminas\I18n\Validator\IsInt;
use Laminas\InputFilter\InputFilter;use Laminas\Validator\GreaterThan;

/**
 * @psalm-type ValidPayload = array{
 *     anInteger: int<1, max>,
 * }
 * @extends InputFilter<ValidPayload>     
 */
final class SomeInputFilter extends InputFilter
{
    public function init(): void
    {
        $this->add([
            'name' => 'anInteger',
            'required' => true,
            'filters' => [
                ['name' => ToNull::class],
                ['name' => ToInt::class],
            ],
            'validators' => [
                ['name' => NotEmpty::class],
                ['name' => IsInt::class],
                [
                    'name' => GreaterThan::class,
                    'options' => [
                        'min' => 1,
                    ],
                ],
            ],
        ]);
    }
}
```

With the above input filter specification, one can guarantee that, if the input payload is deemed valid, then you will receive an array with the expected shape from `InputFilter::getValues()`, therefore, your static analysis tooling will not complain when you pass that value directly to something that expects a `positive-int`, for example:

```php
/**
 * @param positive-int $value
 * @return positive-int 
 */
function addTo5(int $value): int
{
    return $value + 5;
}

$filter = new SomeInputFilter();
$filter->setData(['anInteger' => '123']);
assert($filter->isValid());

$result = addTo5($filter->getValues()['anInteger']);
```

## Further reading

- [Psalm documentation on array shapes](https://psalm.dev/docs/annotating_code/type_syntax/array_types/#array-shapes)
- [Psalm documentation on type aliases](https://psalm.dev/docs/annotating_code/type_syntax/utility_types/#type-aliases)
- [PHPStan documentation on array shapes](https://phpstan.org/writing-php-code/phpdoc-types#array-shapes)
- [PHPStan documentation on type aliases](https://phpstan.org/writing-php-code/phpdoc-types#local-type-aliases)
