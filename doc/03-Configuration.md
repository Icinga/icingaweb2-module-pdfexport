# Configuration <a id="configuration"></a>

The module needs to have at least one backend configured.
All module configuration can be done in `Configuration -> Modules -> pdfexport`.

The priority of the backends is determined by their order in the list.
The first backend (lowest priority number) that is able to handle the request is used.

## Using a Local Chrome/Chromium <a id="configuration-chrome-local"></a>

The module needs Google Chrome or Chromium supporting headless mode.

### RHEL/CentOS <a id="configuration-chrome-setup-rhel"></a>

Add the Chrome repository from Google to yum, next to EPEL.

```
yum -y install epel-release

cat >/etc/yum.repos.d/google-chrome-stable.repo <<EOF
[google-chrome-stable]
name=google-chrome-stable
baseurl=http://dl.google.com/linux/chrome/rpm/stable/\$basearch
enabled=1
gpgcheck=1
gpgkey=https://dl-ssl.google.com/linux/linux_signing_key.pub
EOF

yum makecache
```

Install Chrome and additional dependencies (optional).

```
yum install google-chrome-stable
yum install mesa-libOSMesa mesa-libOSMesa-devel gnu-free-sans-fonts ipa-gothic-fonts ipa-pgothic-fonts
```

### Debian/Ubuntu <a id="configuration-chrome-setup-rhel"></a>

Add the Chrome repository from Google to apt.

```
apt-get -y install apt-transport-https gnupg wget

wget -O - https://dl-ssl.google.com/linux/linux_signing_key.pub | apt-key add -

echo "deb http://dl.google.com/linux/chrome/deb/ stable main" >> /etc/apt/sources.list.d/google.list

apt-get update
```

Install Chrome.

```
apt-get install google-chrome-stable
```

## Using a Remote Chrome/Chromium <a id="configuration-chrome-remote"></a>

As an alternative to a local installation of Chrome/Chromium it is also possible
to launch and use a remote instance.

Install it as described above on a different machine.

To start a remote instance of Chrome/Chromium use the following commandline options:

> google-chrome --remote-debugging-address=0.0.0.0 --remote-debugging-port=9222 --headless --keep-alive-for-test --disable-gpu --disable-dev-shm-usage --no-sandbox --bwsi --no-first-run --user-data-dir=/tmp --homedir=/tmp

Note that the browser does accept any connection attempt without any authentication.
Keep that in mind and let it listen on a public IP (or even on 0.0.0.0) only during tests or
with a proper firewall in place.

Create a new backend in `Configuration -> Modules -> pdfexport -> Backends`,
give it a name and select `Headless Chrome (Remote)` as the backend type and configure its connection details.

## Using a Chrome WebDriver Server <a id="configuration-chrome-webdriver"></a>

Install your preferred Chrome WebDriver/Chromedriver server.
See [Chromedriver Downloads Page](https://developer.chrome.com/docs/chromedriver/downloads)
for instructions to download and install it.

Create a new backend in `Configuration -> Modules -> pdfexport -> Backends`,
give it a name and select `Chrome WebDriver` as the backend type and configure its connection details.

## Using a Firefox WebDriver Server <a id="configuration-firefox-webdriver"></a>

Install your preferred Firefox WebDriver/Geckodriver server.
Check the [Geckodriver Releases Page](https://github.com/mozilla/geckodriver/releases)
for the latest version.

Create a new backend in `Configuration -> Modules -> pdfexport -> Backends`,
give it a name and select `Firefox WebDriver` as the backend type and configure its connection details.
