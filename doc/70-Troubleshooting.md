# Troubleshooting <a id="troubleshooting"></a>

## PDF Export <a id="troubleshooting-pdf-export"></a>

### Check the Google Chrome version

If the PDF export fails, ensure that Chrome headless works fine.
You can test that on the CLI like this:

```
google-chrome --version
```

Ensure you are on a recent version - this module expects the version
to be 59 or higher.

### proc_open() needs to be allowed in PHP

If you are still getting an error message such as:

```
Can't export: Icinga\Module\Pdfexport\ProvidedHook\Pdfexport does not support exporting PDFs
```

check that `proc_open` is not listed in `disable_functions` in your
php.ini configuration. This function is called in order to generate
the PDF.
