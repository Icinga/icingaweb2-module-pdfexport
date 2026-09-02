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
