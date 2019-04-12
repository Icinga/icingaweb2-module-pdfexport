# <a id="Installation"></a>Installation

## Requirements

* Icinga Web 2 (&gt;= 2.6)
* PHP (&gt;= 5.6, preferably 7.x)

## Google Chrome/Chromium Setup

The module needs Google Chrome or Chromium supporting headless mode.
For RHEL based systems you'll find `chromium-headless` in the EPEL repositories.
For debian based systems you may add `https://dl.google.com/linux/chrome/deb/ stable main` to your apt sources.

## Installation

1. Just drop this module to a `pdfexport` subfolder in your Icinga Web 2 module path.

2. Log in with a privileged user in Icinga Web 2 and enable the module in `Configuration -> Modules -> pdfexport`.
Or use the `icingacli` and run `icingacli module enable pdfexport`.

This concludes the installation. PDF exports now use Google Chrome/Chromium for rendering.
