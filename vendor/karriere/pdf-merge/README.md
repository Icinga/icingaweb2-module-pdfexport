<a href="https://www.karriere.at/" target="_blank"><img width="200" src="https://raw.githubusercontent.com/karriereat/.github/main/profile/logo.svg"></a>
<span>&nbsp;&nbsp;&nbsp;</span>
![](https://github.com/karriereat/pdf-merge/workflows/CI/badge.svg)
[![Packagist Downloads](https://img.shields.io/packagist/dt/karriere/pdf-merge.svg?style=flat-square)](https://packagist.org/packages/karriere/pdf-merge)

# Pdf Merge Solution for PHP

This package is a wrapper for the `TCPDF` class that provides an elegant API for merging PDF files.

## Installation

You can install the package via composer:

```bash
composer require karriere/pdf-merge
```

## Usage

```php
use Karriere\PdfMerge\PdfMerge;

$pdfMerge = new PdfMerge();

$pdfMerge->add('/path/to/file1.pdf');
$pdfMerge->add('/path/to/file2.pdf');

$pdfMerge->merge('/path/to/output.pdf');
```

Please note, that the `merge()`-method will throw a `NoFilesDefinedException` if no files where added.

### Check for file existence
You can check if a file was already added for merging by calling:

```php
$pdfMerge->contains('/path/to/file.pdf');
```

### Configuring header and footer
You can also configure the header of footer of all pages like this:

```php
use Karriere\PdfMerge\PdfMerge;

$pdfMerge = new PdfMerge(
        new HeaderConfig(
            imagePath: 'header_logo.png',
            logoWidthMM: 20,
            title: 'Header',
            text: 'This is a header text',
            textColor: new RGB(200, 200, 200),
            lineColor: new RGB(0, 0, 255),
        ),
        new FooterConfig(
            textColor: new RGB(100, 100, 100),
            lineColor: new RGB(255, 0, 0),
            margin: 20,
        ),
    );
```

All config properties have default values, so you don't have to pass them all.

## License

Apache License 2.0 Please see [LICENSE](LICENSE) for more information.
