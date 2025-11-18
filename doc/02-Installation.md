# Installation <a id="installation"></a>

## Requirements <a id="installation-requirements"></a>

* PHP (>= 8.2)
* Icinga Web 2 (>= 2.12)
* Icinga Web 2 libraries:
  * [Icinga PHP Library (ipl)](https://github.com/Icinga/icinga-php-library) (>= 0.17.1)
  * [Icinga PHP Thirdparty](https://github.com/Icinga/icinga-php-thirdparty) (>= 0.13)

## Google Chrome/Chromium Setup <a id="installation-chrome-setup"></a>

The module needs Google Chrome or Chromium supporting headless mode.

### RHEL/CentOS <a id="installation-chrome-setup-rhel"></a>

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

### Debian/Ubuntu <a id="installation-chrome-setup-rhel"></a>

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

## Module Installation <a id="installation-module"></a>

1. Install it [like any other module](https://icinga.com/docs/icinga-web-2/latest/doc/08-Modules/#installation).
Use `pdfexport` as name.

2. You might need to set the absolute path to the Google Chrome / Chromium
binary, depending on your system. This can be done in
`Configuration -> Modules -> pdfexport -> Chrome`

This concludes the installation. PDF exports now use Google Chrome/Chromium for rendering.

### Using a Remote Chrome/Chromium

As an alternative to a local installation of Chrome/Chromium it is also possible
to launch and utilize a remote instance.

Just install it as described above on a different machine and configure its connection
details in `Configuration -> Modules -> pdfexport -> Chrome`.

To start a remote instance of Chrome/Chromium use the following commandline options:

> google-chrome --remote-debugging-address=0.0.0.0 --remote-debugging-port=9222 --headless --keep-alive-for-test --disable-gpu --disable-dev-shm-usage --no-sandbox --bwsi --no-first-run --user-data-dir=/tmp --homedir=/tmp

Note that the browser does accept any and all connection attempts without any authentication.
Keep that in mind and let it listen on a public IP (or even on 0.0.0.0) only during tests or
with a proper firewall in place.
