# Troubleshooting <a id="troubleshooting"></a>

## PDF Export <a id="troubleshooting-pdf-export"></a>

If the PDF export fails, ensure that Chrome headless works fine.
You can test that on the CLI like this:

```
google-chrome --version
```

If you have a local installation, you could also try to force temporary local
storage. (Available in the module's configuration) This will store the content
to print on disk, instead of transferring it directly to the browser. Note that
for this to work, the browser needs to be able to access the temporary files of
the webserver's process user.

## systemd: Chrome renderer crashes under Apache on Ubuntu/Debian <a id="troubleshooting-memdenywriteexec"></a>

Applies to: local Chrome backend when Apache2 is managed by systemd with security hardening.

**Symptom:** PDF export fails. The Icinga Web log shows `Inspector.targetCrashed()` shortly after
`Target.createTarget`. Running the same operation via `sudo -u www-data php` on the CLI succeeds.

**Cause:** Ubuntu 26.04 and some other distributions ship an Apache2 systemd unit with
`MemoryDenyWriteExecute=yes`. This blocks Chrome's V8 JIT compiler from creating writable+executable
memory pages. All processes started by Apache inherit this restriction via the systemd cgroup.

**Confirm it:**

```
systemctl show apache2 | grep MemoryDenyWriteExecute
```

If the output is `MemoryDenyWriteExecute=yes`, this is the cause.

**Fix:** Create a systemd drop-in to override the setting:

```
sudo mkdir -p /etc/systemd/system/apache2.service.d/
sudo tee /etc/systemd/system/apache2.service.d/pdfexport.conf << 'EOF'
[Service]
MemoryDenyWriteExecute=no
EOF
sudo systemctl daemon-reload
sudo systemctl restart apache2
```

## SELinux: Chrome renderer crashes on RHEL/Fedora <a id="troubleshooting-selinux"></a>

Applies to: local Chrome backend on systems with SELinux in enforcing mode.

**Symptom:** PDF export times out. The Icinga Web log shows `Inspector.targetCrashed()` events
shortly after `Target.createTarget`, followed by a `ConnectionTimeoutException`.

**Cause:** Chrome's JavaScript engine (V8) requires executable memory for JIT compilation
(`execmem`). SELinux denies this for the `httpd_t` context (used by PHP-FPM) by default.

**Confirm it:**

```
sudo ausearch -m avc | grep execmem
```

A line like `denied { execmem } for ... comm="chromium-browse" scontext=...httpd_t` confirms
the cause.

**Fix:**

```
sudo setsebool -P httpd_execmem on
```

The `-P` flag makes the change persistent across reboots.
