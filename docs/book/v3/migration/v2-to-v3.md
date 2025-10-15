# Migration from Version 2 to 3

`laminas-inputfilter` version 3 has a significant number of changes that may affect your application.
This document details those changes, and provides suggestions on how to update your application to work with version 3.

## New Features

### Support for Service Manager v4, Filter v3 and Validator v3

Version 4 of Service Manager brought breaking changes to "Plugin Managers" affecting a number of Laminas components.
These changes necessitated new major versions of libraries that implemented Plugin Managers including 2 critical dependencies here, namely [`laminas-filter`](https://docs.laminas.dev/laminas-filter/) and [`laminas-validator`](https://docs.laminas.dev/laminas-validator/).

This was a good opportunity to address a number of quality improvements across these components meaning more backwards incompatible changes.

These have been addressed in the v3 release allowing upgrade of all these linked dependencies in your applications.

## General Changes

### Native Parameter and Return Types

Native types have been added throughout the codebase;
this means that if you have custom code that extends classes shipped in `laminas-inputfilter` or implements shipped interfaces, it is very likely that you will receive errors for incompatible method signatures.

You will need to address these on a case by case basis by inspecting the inherited class/interface and adjusting overridden method signatures to match.

### Final by Default

A number of classes now have the [`final` keyword](https://www.php.net/manual/language.oop5.final.php).

As a general rule, we are endeavouring to finalise all classes that have not been explicitly designed for inheritance.
This helps reduce our maintenance burden by using encapsulation to allow internal changes or improvements without breaking BC.
It also encourages better design in end-user projects.

### Removal of "Getters" and "Setters"

Most shipped classes have fewer methods.
Whilst we will list removed methods in this guide, not all of those removals will be exhaustively documented.
Generally speaking 'setters' and 'getters' have been removed in favour of constructor injection.

So, where previously a class may have had a method such as `setPluginManager()`, that dependency will now be required in the constructor and the method will be removed along with its companion `getPluginManager()`

## Signature and Behaviour Changes

List detailed changes to classes here.

## Removed Features

List removals here.
