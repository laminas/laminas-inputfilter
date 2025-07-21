# Usage in a laminas-mvc Application

The following example shows _one_ potential use case of laminas-inputfilter within a laminas-mvc based application.
The example uses a module, a controller and the input filter plugin manager.

The example is based on the [tutorial application](https://docs.laminas.dev/tutorials/getting-started/overview/) which builds an album inventory system.

Before starting, make sure laminas-inputfilter is [installed and configured](../installation.md).

## Create Input Filter

[Create an input filter as separate class](../intro.md) using the `init` method, e.g. `module/Album/src/InputFilter/QueryInputFilter.php`:

```php
namespace Album\InputFilter;

use Laminas\Filter\ToInt;
use Laminas\I18n\Validator\IsInt;
use Laminas\InputFilter\InputFilter;

final class QueryInputFilter extends InputFilter
{
    public function init(): void
    {
        // Page
        $this->add(
            [
                'name'              => 'page',
                'allow_empty'       => true,
                'validators'        => [
                    [
                        'name' => IsInt::class,                        
                    ],                    
                ],
                'filters'           => [
                    [
                        'name' => ToInt::class,
                    ],
                ],
                'fallback_value'    => 1,
            ]
        );
    
        // …
    }
}
```

## Create Controller

[Create a controller class](https://docs.laminas.dev/laminas-mvc/quick-start/#create-a-controller) and inject the input filter plugin manager via the constructor, e.g. `module/Album/Controller/AlbumController.php`:

```php
namespace Album\Controller;

use Album\InputFilter\QueryInputFilter;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\Mvc\Controller\AbstractActionController;

use function assert;

final class AlbumController extends AbstractActionController
{
    public function __construct(
        public readonly InputFilterPluginManager $inputFilterPluginManager
    ) {}
    
    public function indexAction()
    {
        $inputFilter = $this->inputFilterPluginManager->get(QueryInputFilter::class);
        assert($inputFilter instanceof QueryInputFilter);
    
        $inputFilter->setData($this->getRequest()->getQuery());
        $inputFilter->isValid();
        $filteredParams = $inputFilter->getValues();
        
        // …
    }
}
```

> INFO: **Instantiating the Input Filter**
>
> The input filter plugin manager (`Laminas\InputFilter\InputFilterPluginManager`) is used instead of directly instantiating the input filter to ensure to get the filter and validator plugin managers injected.
> This allows usage of any filters and validators registered with their respective plugin managers.
>
> Additionally, the input filter plugin manager calls the `init` method _after_ instantiating the input filter, ensuring all dependencies are fully injected first.

## Register Input Filter and Controller

If no separate factory is required for the input filter, then the input filter plugin manager will be instantiating the input filter class without prior registration. Otherwise, the input filter must be registered.

To [register the controller](https://docs.laminas.dev/laminas-mvc/quick-start/#create-a-route) for the application, extend the configuration of the module.
Add the following lines to the module configuration file, e.g. `module/Album/config/module.config.php`:

<!-- markdownlint-disable MD033 -->
<pre class="language-php" data-line="8-9"><code>
namespace Album;

use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;

return [
    'controllers' => [
        'factories' => [
            // Add this line
            Controller\AlbumController::class => ReflectionBasedAbstractFactory::class,
        ],
    ],
    // …
];
</code></pre>
<!-- markdownlint-enable MD033 -->

The example uses the [reflection factory from laminas-servicemanager](https://docs.laminas.dev/laminas-servicemanager/reflection-abstract-factory/) to resolve the constructor dependencies for the controller class.
