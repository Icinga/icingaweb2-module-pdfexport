# Installation

## Requirements

- PHP ≥ 8.2
- Icinga Web 2 ≥ 2.12.5
- Icinga Web 2 libraries:
  - [Icinga PHP Library (ipl)](https://github.com/Icinga/icinga-php-library) ≥ 1.0.0
  - [Icinga PHP Thirdparty](https://github.com/Icinga/icinga-php-thirdparty) ≥ 1.0.0
- A supported PDF export backend using Google Chrome, Chromium, or Firefox.
  Firefox requires a WebDriver server and has
  [rendering limitations](03-Configuration.md#using-a-firefox-webdriver-server).

## Module installation

1. Install the module [like any other module][module-installation].
   Use `pdfexport` as its name.
2. Install a supported browser from your distribution's packages or the browser
   vendor. Install a matching ChromeDriver or geckodriver when using WebDriver.
   The browser must be installed on the machine that runs the backend.
3. Follow the [configuration instructions](03-Configuration.md) to set up the
   backend and its connection details.

[module-installation]: https://icinga.com/docs/icinga-web-2/latest/doc/08-Modules/#installation
