# Installation <a id="installation"></a>

## Requirements <a id="installation-requirements"></a>

* Icinga Web 2 (&gt;= 2.6)
* PHP (&gt;= 5.6, preferably 7.x)
* Icinga Web 2 modules:
  * [reactbundle](https://github.com/Icinga/icingaweb2-module-reactbundle) (>= 0.4)
  * [Icinga PHP Library (ipl)](https://github.com/Icinga/icingaweb2-module-ipl) (>= 0.2.1)

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

1. Just drop this module to a `pdfexport` subfolder in your Icinga Web 2 module path.

2. Log in with a privileged user in Icinga Web 2 and enable the module in `Configuration -> Modules -> pdfexport`.
Or use the `icingacli` and run `icingacli module enable pdfexport`.

3. You might need to set the absolute path to the Google Chrome / Chromium
binary, depending on your system. This can be done in
`Configuration -> Modules -> pdfexport -> Binary`

This concludes the installation. PDF exports now use Google Chrome/Chromium for rendering.
