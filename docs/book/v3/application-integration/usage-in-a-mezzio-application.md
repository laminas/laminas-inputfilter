# Usage in a Mezzio Application

The following example shows _one_ potential use case of laminas-inputfilter within
a Mezzio-based application. The example uses a module, config provider
configuration, laminas-servicemanager as a dependency injection container, the
laminas-inputfilter plugin manager and a request handler.

Before starting, make sure the lamina-inputfilter is [installed and configured](../installation.md).

## Create Input Filter

Create an input filter as separate class, e.g.
`src/Album/InputFilter/QueryInputFilter.php`:

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

## Using Input Filter

### Create Handler

Using the input filter in a request handler, e.g.
`src/Album/Handler/ListHandler.php`:

```php
namespace Album\Handler;

use Album\InputFilter\QueryInputFilter;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\InputFilter\InputFilterInterface;
use Mezzio\Template\TemplateRendererInterface;

final readonly class ListHandler implements RequestHandlerInterface
{
    public function __construct(
        private InputFilterPluginManager $inputFilterPluginManager
        private TemplateRendererInterface $renderer
    ) {}
    
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $inputFilter = $this->inputFilterPluginManager->get(QueryInputFilter::class);
        assert($inputFilter instanceof QueryInputFilter);
    
        $inputFilter->setData($request->getQueryParams());
        $inputFilter->isValid();
        $filteredParams = $inputFilter->getValues();
        
        // …

        return new HtmlResponse($this->renderer->render(
            'album::list',
            []
        ));
    }
}
```

> INFO: **Instantiating the Input Filter**
>
> The input filter plugin manager (`Laminas\InputFilter\InputFilterPluginManager`) is used instead of directly instantiating the input filter to ensure to get the filter and validator plugin managers injected.
> This allows usage of any filters and validators registered with their respective plugin managers.
>
> Additionally, the input filter plugin manager calls the `init` method _after_ instantiating the input filter, ensuring all dependencies are fully injected first.

## Register Request Handler

Extend the configuration provider of the module to register the request handler, e.g. `src/Album/ConfigProvider.php`:

<!-- markdownlint-disable MD033 -->
<pre class="language-php" data-line="3,18"><code>
namespace Album;

use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;

final class ConfigProvider
{
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }
    
    public function getDependencies() : array
    {
        return [
            'factories' => [
                Handler\ListHandler::class => ReflectionBasedAbstractFactory::class,
                // …
            ],
        ];
    }
    
    // …
}
</code></pre>
<!-- markdownlint-enable MD033 -->

The example uses the [reflection factory from laminas-servicemanager](https://docs.laminas.dev/laminas-servicemanager/reflection-abstract-factory/) to resolve the constructor dependencies for the request handler class.
