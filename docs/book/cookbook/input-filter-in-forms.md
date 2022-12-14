# Using Input Filters in Forms of laminas-form

The following examples shows _two_ potential use cases of laminas-inputfilter within [laminas-form](https://docs.laminas.dev/laminas-form/).

## Define the Input Filter in a Form

An input filter can be [defined directly in a form class](https://docs.laminas.dev/laminas-form/v3/quick-start/#hinting-to-the-input-filter) itself, using `Laminas\InputFilter\InputFilterProviderInterface`.
This interface provides one method (`getInputFilterSpecification()`) that is used by a form to create an input filter.

[Create a form as a separate class](https://docs.laminas.dev/laminas-form/v3/quick-start/#factory-backed-form-extension), define the [`init` method](https://docs.laminas.dev/laminas-form/v3/advanced/#initialization), implement the interface `Laminas\InputFilter\InputFilterProviderInterface`, and define its inputs via a configuration array; as an example, consider the following definition in a file found at `module/Album/src/Form/AlbumForm.php`:

<!-- markdownlint-disable MD033 -->
<pre class="language-php" data-line="7,10,26-48"><code>
namespace Album\Form;

use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\Form\Element\Text;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\StringLength;

final class AlbumForm extends Form implements InputFilterProviderInterface
{
    public function init(): void
    {
        // Add form elements
        $this->add([
            'name'    => 'title',
            'type'    => Text::class,
            'options' => [
                'label' => 'Title',
            ],
        ]);

        // …
    }

    public function getInputFilterSpecification(): array
    {
        return [
            // Add inputs
            [
                'name'    => 'title',
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                    [
                        'name'    => StringLength::class,
                        'options' => [
                            'min' => 1,
                            'max' => 100,
                        ],
                    ],
                ],
            ],
            // …
        ];
    }
}
</code></pre>
<!-- markdownlint-enable MD033 -->

## Adding an Input Filter Defined as a Separate Class to a Form

### Create Input Filter

[Create an input filter as a separate class](../intro.md), e.g. `module/Album/src/InputFilter/AlbumInputFilter.php`:

```php
namespace Album\InputFilter;

use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\Validator\StringLength;
use Laminas\InputFilter\InputFilter;

final class AlbumInputFilter extends InputFilter
{
    public function init(): void
    {
        // Add inputs
        $this->add(
            [
                'name'    => 'title',
                'filters' => [
                    ['name' => StripTags::class],
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                    [
                        'name'    => StringLength::class,
                        'options' => [
                            'min' => 1,
                            'max' => 100,
                        ],
                    ],
                ],
            ]
        );

        // …
    }
}
```

### Create Form and Add Input Filter

An input filter can be added directly in a form class itself, using the class name of the input filter or whatever name the input filter is registered under.

Create a form as a separate class, define its `init` method, and set the input filter via the `setInputFilterByName()` method of `Laminas\Form\Form`, e.g. `module/Album/src/Form/AlbumForm.php`:

<!-- markdownlint-disable MD033 -->
<pre class="language-php" data-line="11-12"><code>
namespace Album\Form;

use Album\InputFilter\AlbumInputFilter;
use Laminas\Form\Element\Text;
use Laminas\Form\Form;

final class AlbumForm extends Form
{
    public function init(): void
    {
        // Set the input filter
        $this->setInputFilterByName(AlbumInputFilter::class);

        // Add form elements
        $this->add([
            'name'    => 'title',
            'type'    => Text::class,
            'options' => [
                'label' => 'Title',
            ],
        ]);

        // …
    }
}
</code></pre>
<!-- markdownlint-enable MD033 -->
