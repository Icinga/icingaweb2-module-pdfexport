# Upgrade Guide

## v2 to v3
The only breaking chance in this release is the way a header and/or footer configuration is done.
In version 2 this was done with simple arrays, in v3 dedicated classes were introduced to make the
configuration type safe and bullet proof.

### Header/Footer Configuration
```php
// Before
use Karriere\PdfMerge\PdfMerge;

new PdfMerge([
    'ln' => 'header_logo.png',
    'lw' => 20,
    'ht' => 'title',
    'hs' => 'more text',
    'tc' => [255, 255, 255],
    'lc' => [0, 0, 0].
],
[
    'tc' => [255, 255, 255],
    'lc' => [0, 0, 0].
]);

// After
use Karriere\PdfMerge\Config\FooterConfig;
use Karriere\PdfMerge\Config\HeaderConfig;
use Karriere\PdfMerge\Config\RGB;
use Karriere\PdfMerge\PdfMerge;

new PdfMerge(
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

The above example shows the full feature set of header/footer configuration, but when using named arguments you can
choose a subset of configuration values as well because all other values have defaults in place:

```php
use Karriere\PdfMerge\Config\FooterConfig;
use Karriere\PdfMerge\Config\HeaderConfig;
use Karriere\PdfMerge\Config\RGB;
use Karriere\PdfMerge\PdfMerge;

new PdfMerge(
    new HeaderConfig(
        imagePath: 'header_logo.png',
        text: 'This is a header text',
        textColor: new RGB(200, 200, 200),
    ),
    new FooterConfig(
        lineColor: new RGB(255, 0, 0),
    ),
);
```

