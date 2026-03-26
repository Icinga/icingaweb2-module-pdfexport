# Installation <a id="installation"></a>

## Requirements <a id="installation-requirements"></a>

* PHP ≥ 8.2
* Icinga Web 2 ≥ 2.12.5
* Icinga Web 2 libraries:
  * [Icinga PHP Library (ipl)](https://github.com/Icinga/icinga-php-library) ≥ 1.0.0
  * [Icinga PHP Thirdparty](https://github.com/Icinga/icinga-php-thirdparty) ≥ 1.0.0

## Module Installation <a id="installation-module"></a>

1. Install it [like any other module](https://icinga.com/docs/icinga-web-2/latest/doc/08-Modules/#installation).
Use `pdfexport` as name.

2. You will need to install and configure at leaast one PDF export backend.
See more about it in the [Configuration](03-Configuration.md) section.

This concludes the installation. PDF exports now use Google Chrome/Chromium for rendering.
