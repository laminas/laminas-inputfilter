<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\Preset;

return Architecture::define()
    ->withPresets(Preset::PSR4(), Preset::CODEQUALITY())
    ->layer('Exception', 'src/Exception')
    ->layer('Contract', [
        'src/EmptyContextInterface.php',
        'src/InputFilterInterface.php',
        'src/InputFilterProviderInterface.php',
        'src/InputInterface.php',
        'src/InputProviderInterface.php',
        'src/ReplaceableInputInterface.php',
        'src/UnfilteredDataInterface.php',
        'src/UnknownInputsCapableInterface.php',
    ])
    ->layer('Aware', [
        'src/InputFilterAwareInterface.php',
        'src/InputFilterAwareTrait.php',
    ])
    ->layer('Input', [
        'src/ArrayInput.php',
        'src/Input.php',
    ])
    ->layer('FileInput', [
        'src/FileInput.php',
        'src/FileInput',
    ])
    ->layer('InputFilter', [
        'src/BaseInputFilter.php',
        'src/CollectionInputFilter.php',
        'src/Factory.php',
        'src/InputFilter.php',
        'src/InputFilterPluginManager.php',
        'src/OptionalInputFilter.php',
    ])
    ->layer('Integration', [
        'src/ConfigProvider.php',
        'src/InputFilterAbstractServiceFactory.php',
        'src/InputFilterPluginManagerFactory.php',
        'src/Module.php',
    ])
    ->ruleset([
        'Exception'   => [],
        'Contract'    => [],
        'Aware'       => ['Contract'],
        'Input'       => ['Contract'],
        'FileInput'   => ['+Input'],
        'InputFilter' => ['Exception', '+Input'],
        'Integration' => ['InputFilter'],
    ]);
