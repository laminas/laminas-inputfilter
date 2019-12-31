# Input filter specifications

`Laminas\InputFilter` allows configuration-driven creation of input filters via
`Laminas\InputFilter\InputFilterAbstractServiceFactory`. This abstract factory is responsible for
creating and returning an appropriate input filter given named configuration under the top-level
configuration key `input_filter_specs`.

It is registered with `Laminas\InputFilter\InputFilterPluginManager`, allowing you to pull the input
filter via that plugin manager. A side effect is that forms pulled from
`Laminas\Form\FormElementManager` can use these named input filters.

## Setup

This functionality is disabled by default.

To enable it, you must add the `Laminas\InputFilter\InputFilterAbstractServiceFactory` abstract factory
to the `Laminas\InputFilter\InputFilterPluginManager` configuration, which is unser the `input_filters`
configuration key.

```php
return array(
    'input_filters' => array(
        'abstract_factories' => array(
            'Laminas\InputFilter\InputFilterAbstractServiceFactory'
        ),
    ),
);
```

## Example

In the following code, we define configuration for an input filter named `foobar`:

```php
return array(
    'input_filter_specs' => array(
        'foobar' => array(
            0 => array(
                'name' => 'name',
                'required' => true,
                'filters' => array(
                    0 => array(
                        'name' => 'Laminas\Filter\StringTrim',
                        'options' => array(),
                    ),
                ),
                'validators' => array(),
                'description' => 'Hello to name',
                'allow_empty' => false,
                'continue_if_empty' => false,
        ),
    ),
);
```

When creating a controller, we might then pull the `InputFilterManager`, and retrieve the `foobar`
input filter we've defined in order to inject it:

```php
use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class MyValidatingControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $controllers)
    {
        // Retrieve the application service manager
        $services = $controllers->getServiceLocator();

        // Retrieve the InputFilterManager
        $filters = $services->get('InputFilterManager');

        // Instantiate the controller and pass it the foobar input filter
        return new MyValidatingController($filters->get('foobar'));
    }
}
```

And you can use it, as you already did with other input filters:

```php
$inputFilter->setData(array(
    'name' => 'test',
));

if (! $inputFilter->isValid()) {
    echo 'Data invalid';
}
```
