# icingaweb2-module-pdfexport

Future place for the PDF export functionality for Icinga Web 2.

Please run `composer install` to install `phantomjs` to the `bin` directory. In order to generate a PDF from HTML just
execute `phantomjs generate-pdf.js /path/to/in.html /path/to/out.pdf`.

Since phantomjs [suspended development](https://github.com/ariya/phantomjs/issues/15344), we may use chromium headless for rendering PDFs.
