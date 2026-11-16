# Configuration

PDF export needs an available browser backend. Open
`Configuration -> Modules -> pdfexport -> Backends` to manage backends.

Select **Create a New Backend**, enter a unique name, and choose its type.
Set **Enabled** and a numeric **Priority**, then enter the settings described
below and save the backend. Select a backend's name in the list to edit or
rename it. Use its delete action and confirm to remove it.

Disabled backends are ignored. Enabled backends are tried in order of their
numeric priority, with lower numbers first. The first backend that connects and
supports PDF generation is used. Give backends different priorities to control
which one is preferred.

## Default and existing configurations

Installations without an explicit backend configuration use a local Chrome
installation at `/usr/bin/google-chrome` (default). Create a backend to use a
different binary, a remote browser, or WebDriver.

Existing `[chrome]` settings remain supported. The module keeps the configured
`binary`, `host`, `port`, and `force_temp_storage` values. A configured host
selects a remote Chrome backend. Otherwise the backend uses local Chrome.
Saving or deleting a backend through the UI makes the backend configuration
explicit. Disabling or deleting all backends then keeps PDF export disabled
until a backend is enabled or created. It does not restore the default browser.

For a manually managed `config.ini`, set `configured = "1"` in `[_global]` to
record an explicit configuration with no backends. The module manages this
setting when you use the UI.

## Using a local Chrome/Chromium

Install a supported Google Chrome or Chromium package with headless support on
the Icinga Web server. Use your distribution's packages or the browser vendor's
installation instructions.

Choose **Headless Chrome (Local)**. Set **Binary** to the executable's absolute
path, such as `/usr/bin/google-chrome` or `/usr/bin/chromium`.
The default path is `/usr/bin/google-chrome`.

The Icinga Web process must be able to execute the browser and write to its
temporary storage directory. The module starts the browser and assigns a local
DevTools port automatically. No fixed listening port needs to be configured.

Enable **Use temp storage** to transfer HTML through a local temporary file
instead of sending it directly to the browser. This option applies to the
selected local backend. The browser must be able to read that file.

## Using a remote Chrome/Chromium

Install a supported Chrome or Chromium browser on the remote machine. Start it
in headless mode with remote debugging enabled on an address reachable from
Icinga Web. Use a dedicated browser profile directory.

Choose **Headless Chrome (Remote)**. Enter the browser's **Host** and DevTools
**Port**. The module uses port `9222` when no port is specified. This is the
browser's debugging endpoint, which is separate from a WebDriver server.

The debugging endpoint permits control of the browser without authentication.
Restrict access to trusted hosts, such as the Icinga Web server, using network
binding and firewall rules.

## Using a Chrome WebDriver server

Install Chrome or Chromium and a compatible ChromeDriver on the machine that
runs the WebDriver server. Follow the
[ChromeDriver setup instructions][chromedriver-setup]
and [version selection guidance][chromedriver-downloads].
Start the server separately so Icinga Web can reach it.

Choose **Chrome WebDriver**. Set **Host** to the server address including the
scheme, such as `http://127.0.0.1`, and set **Port** to its listening port.
The module defaults to `4444`. Standalone ChromeDriver normally listens on
`9515`, so enter `9515` when using that default.

## Using a Firefox WebDriver server

Install Firefox and a compatible geckodriver on the machine that runs the
WebDriver server. Follow the
[geckodriver usage instructions][geckodriver-usage].
Start the server separately so Icinga Web can reach it.

Choose **Firefox WebDriver**. Set **Host** to the server address including the
scheme, such as `http://127.0.0.1`, and set **Port** to its listening port.
The module and standalone geckodriver default to `4444`.

Firefox exports omit cover pages and custom print header and footer templates.
Firefox also does not run the module's Chrome-specific layout scripts, so page
layout can differ from Chrome exports. Use a Chrome backend when these features
are required.

Keep WebDriver servers accessible only to trusted hosts. The browser and driver
must run on the server side. Installing a driver on the Icinga Web host alone
does not start a WebDriver service.

[chromedriver-setup]: https://developer.chrome.com/docs/chromedriver/get-started
[chromedriver-downloads]: https://developer.chrome.com/docs/chromedriver/downloads
[geckodriver-usage]: https://firefox-source-docs.mozilla.org/testing/geckodriver/Usage.html
