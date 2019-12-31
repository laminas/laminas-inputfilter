# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 2.7.6 - 2017-12-04

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#156](https://github.com/zendframework/zend-inputfilter/pull/156) fixes an
  issue introduced in 2.7.5 whereby the filter and validator chains composed in
  inputs pulled from the `InputFilterPluginManager` were not receiving the
  default filter and validator plugin manager instances. A solution was created
  that preserves the original behavior as well as the bugfix that created the
  regression.

## 2.7.5 - 2017-11-07

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#151](https://github.com/zendframework/zend-inputfilter/pull/151) fixes an
  issue in `Factory::createInput()` introduced in
  [zendframework/zend-inputfilter#2](https://github.com/zendframework/zend-inputfilter/pull/2) whereby an
  input pulled from the input filter manager would be injected with the default
  filter and validator chains, overwriting any chains that were set during
  instantiation and/or `init()`. They are now never overwritten.

- [zendframework/zend-inputfilter#149](https://github.com/zendframework/zend-inputfilter/pull/149) fixes an
  issue with how error messages for collection input field items were reported;
  previously, as soon as one item in the collection failed, the same validation
  message was propagated to all other items. This is now resolved.

- [zendframework/zend-inputfilter#131](https://github.com/zendframework/zend-inputfilter/pull/131) fixes a
  regression introduced in version 2.2.6 within
  `BaseInputFilter::setValidatorGroup()` whereby it began emitting exceptions if
  a given input was not an input filter. This raises issues when mixing input
  filters and inputs in the same validator group specification, as you will
  generally provide the input names as keys instead of values. The patch provide
  ensures both styles work going forwards.

## 2.7.4 - 2017-05-18

### Added

- Nothing.

### Changes

- [zendframework/zend-inputfilter#122](https://github.com/zendframework/zend-inputfilter/pull/122) maps the
  `Laminas\InputFilter\InputFilterPluginManager` service to
  `Laminas\InputFilter\InputFilterPluginManagerFactory`, and adds an alias from
  `InputFitlerPluginManager` to the fully qualified class name. This change
  allows you to request the service using either the original short name, or the
  fully qualified class name.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#137](https://github.com/zendframework/zend-inputfilter/pull/137) fixes how the
  `InputFilterPluginManagerFactory` factory initializes the plugin manager
  instance, ensuring it is injecting the relevant configuration from the
  `config` service and thus seeding it with configured input filter services.
  This means that the `input_filters` configuration will now be honored in
  non-laminas-mvc contexts.

## 2.7.3 - 2016-08-18

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#115](https://github.com/zendframework/zend-inputfilter/pull/115) fixes
  retrieval of unknown fields when using a `CollectionInputFilter`. Previously,
  it returned all fields in the collection, not just the unknown fields, which
  was a different behavior from all other input filters. Now it will return only
  the unknown fields for each collection.
- [zendframework/zend-inputfilter#108](https://github.com/zendframework/zend-inputfilter/pull/108) fixes
  the `InputFilterPluginManager::populateFactory()` method to restore behavior
  from prior to the 2.7 series; specifically, previously it would inject itself
  as the plugin manager to input filter factories when under laminas-servicemanager
  v2; it now will do so again.
- [zendframework/zend-inputfilter#116](https://github.com/zendframework/zend-inputfilter/pull/116) fixes the
  behavior of `CollectionInputFilter::setData()`. Prior to this release, it
  would validate whether the data represented a collection (i.e., it was an
  array or traversable) and whether individual items in the collection were data
  sets (i.e., arrays or traversables) only during `isValid()` and/or
  `getUnknown()` calls, raising exceptions during runtime. These should have
  been considered invalid arguments when the data was provided; they now are. As
  such, `setData()` now raises `Laminas\InputFilter\Exception\InvalidArgumentException`
  for invalid data, ensuring that `isValid()` and `getUnknown()` only ever
  operate on usable collections and collection sets.
- [zendframework/zend-inputfilter#118](https://github.com/zendframework/zend-inputfilter/pull/118) fixes
  aggregation of error messages when validating collections to ensure only the
  error messages specific to a given datum are presented.

## 2.7.2 - 2016-06-11

### Added

- [zendframework/zend-inputfilter#105](https://github.com/zendframework/zend-inputfilter/pull/105) adds and
  publishes the documentation to https://docs.laminas.dev/laminas-inputfilter

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#110](https://github.com/zendframework/zend-inputfilter/pull/110) fixes an
  issue with `InputFilterAbstractServiceFactory` whereby it was not working when
  the provided container is not a plugin manager, but rather the application
  container.

## 2.7.1 - 2016-04-18

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#104](https://github.com/zendframework/zend-inputfilter/pull/104) fixes the
  `Module::init()` method to properly receive a `ModuleManager` instance, and
  not expect a `ModuleEvent`.

## 2.7.0 - 2016-04-07

### Added

- [zendframework/zend-inputfilter#3](https://github.com/zendframework/zend-inputfilter/pull/3) updates the
  `InputFilterAbstractServiceFactory` to inject the created input filter factory
  with the `InputFilterManager` service, ensuring that the generated factory can
  pull named input filters and inputs from the container as needed.
- [zendframework/zend-inputfilter#100](https://github.com/zendframework/zend-inputfilter/pull/100) adds a
  number of classes, in order to better allow usage as a standalone component:
  - `InputFilterPluginManagerFactory`, ported from laminas-mvc, allows creating and
    returning an `InputFilterPluginManager`.
  - `ConfigProvider` maps the `InputFilterManager` service to the above factory,
    and enables the `InputFilterAbstractServiceFactory`.
  - `Module` does the same as `ConfigProvider`, within a laminas-mvc context, and
    also registers a specification with the laminas-modulemanager `ServiceListener`
    to allow modules to configure the input filter plugin manager.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 2.6.1 - 2016-04-07

### Added

- [zendframework/zend-inputfilter#68](https://github.com/zendframework/zend-inputfilter/pull/68) adds support
  for using *either* named keys *or* a `name` element in input filter specs
  parsed by the `InputFilterAbstractServiceFactory`.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#67](https://github.com/zendframework/zend-inputfilter/pull/67) and
  [zendframework/zend-inputfilter#73](https://github.com/zendframework/zend-inputfilter/pull/73) fix
  localization of the `NotEmpty` validation error message (created for any
  required input for which a value was not provided).

## 2.6.0 - 2016-02-18

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#86](https://github.com/zendframework/zend-inputfilter/pull/86),
  [zendframework/zend-inputfilter#95](https://github.com/zendframework/zend-inputfilter/pull/95), and
  [zendframework/zend-inputfilter#96](https://github.com/zendframework/zend-inputfilter/pull/96) update the
  component to be forwards-compatible with laminas-servicemanager v3.
- [zendframework/zend-inputfilter#72](https://github.com/zendframework/zend-inputfilter/pull/72) `ArrayInput`
  value is properly reset after `BaseInputFilter::setData()`

## 2.5.5 - 2015-09-03

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#22](https://github.com/zendframework/zend-inputfilter/pull/22) adds tests to
  verify two conditions around inputs with fallback values:
  - If the input was not in the data set, it should not be represented in either
    the list of valid *or* invalid inputs.
  - If the input *was* in the data set, but empty, it should be represented in
    the list of valid inputs.
- [zendframework/zend-inputfilter#31](https://github.com/zendframework/zend-inputfilter/pull/31) updates the
  `InputFilterInterface::add()` docblock to match existing, shipped implementations.
- [zendframework/zend-inputfilter#25](https://github.com/zendframework/zend-inputfilter/pull/25) updates the
  input filter to prevent validation of missing optional fields (a BC break
  since 2.3.9). This change likely requires changes to your inputs as follows:

  ```php
  $input = new Input();
  $input->setAllowEmpty(true);         // Disable BC Break logic related to treat `null` values as valid empty value instead *not set*.
  $input->setContinueIfEmpty(true);    // Disable BC Break logic related to treat `null` values as valid empty value instead *not set*.
  $input->getValidatorChain()->attach(
      new Laminas\Validator\NotEmpty(),
      true                             // break chain on failure

  );
  ```

  ```php
  $inputSpecification = [
    'allow_empty'       => true,
    'continue_if_empty' => true,
    'validators' => [
      [
        'break_chain_on_failure' => true,
        'name'                   => 'Laminas\\Validator\\NotEmpty',
      ],
    ],
  ];
  ```
- [Numerous fixes](https://github.com/laminas/laminas-inputfilter/milestones/2.4.8)
  aimed at bringing the functionality back to the pre-2.4 code, and improving
  quality overall of the component via increased testing and test coverage.

## 2.5.4 - 2015-08-11

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#15](https://github.com/zendframework/zend-inputfilter/pull/15) ensures that
  `ArrayAccess` data provided to an input filter using `setData()` can be
  validated, a scenario that broke with [zendframework/zend-inputfilter#7](https://github.com/zendframework/zend-inputfilter/pull/7).

## 2.5.3 - 2015-08-03

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#10](https://github.com/zendframework/zend-inputfilter/pull/10) fixes an
  issue with with the combination of `required`, `allow_empty`, and presence of
  a fallback value on an input introduced in 2.4.5. Prior to the fix, the
  fallback value was no longer considered when the value was required but no
  value was provided; it now is.

## 2.5.2 - 2015-07-28

### Added

- [zendframework/zend-inputfilter#2](https://github.com/zendframework/zend-inputfilter/pull/2) adds support
  in `Laminas\InputFilter\Factory` for using the composed `InputFilterManager` to
  retrieve an input of a given `type` based on configuration; only if the type
  is not available in the factory will it attempt to directly instantiate it.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#7](https://github.com/zendframework/zend-inputfilter/pull/7) fixes an issue
  with the combination of `required` and `allow_empty`, now properly
  invalidating a data set if the `required` input is missing entirely
  (previously, it would consider the data set valid, and auto-initialize the
  missing input to `null`).

## 2.4.8 - TBD

### Added

- Nothing.

### Deprecated

- [zendframework/zend-inputfilter#26](https://github.com/zendframework/zend-inputfilter/pull/26) Deprecate magic logic for auto attach a NonEmpty
 validator with breakChainOnFailure = true. Instead append NonEmpty validator when desired.

  ```php
  $input = new Laminas\InputFilter\Input();
  $input->setContinueIfEmpty(true);
  $input->setAllowEmpty(true);
  $input->getValidatorChain()->attach(new Laminas\Validator\NotEmpty(), /* break chain on failure */ true);
  ```

### Removed

- Nothing.

### Fixed

- Nothing.

## 2.4.7 - 2015-08-11

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#15](https://github.com/zendframework/zend-inputfilter/pull/15) ensures that
  `ArrayAccess` data provided to an input filter using `setData()` can be
  validated, a scenario that broke with [zendframework/zend-inputfilter#7](https://github.com/zendframework/zend-inputfilter/pull/7).

## 2.4.6 - 2015-08-03

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#10](https://github.com/zendframework/zend-inputfilter/pull/10) fixes an
  issue with with the combination of `required`, `allow_empty`, and presence of
  a fallback value on an input introduced in 2.4.5. Prior to the fix, the
  fallback value was no longer considered when the value was required but no
  value was provided; it now is.

## 2.4.5 - 2015-07-28

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zendframework/zend-inputfilter#7](https://github.com/zendframework/zend-inputfilter/pull/7) fixes an issue
  with the combination of `required` and `allow_empty`, now properly
  invalidating a data set if the `required` input is missing entirely
  (previously, it would consider the data set valid, and auto-initialize the
  missing input to `null`).
