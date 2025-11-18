# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [3.3.1] - 2025-02-19
### Added
- `@throws` to PHPDoc for better IDE support

## [3.3.0] - 2025-02-19
### Added
- Method `getFiles()`

## [3.2.0] - 2024-12-05
### Added
- Support for PHP 8.4

### Removed
- Support for PHP 8.0

## [3.1.0] - 2024-02-05
### Added
- Support for PHP 8.3

## [3.0.0] - 2023-03-01
### Added
- Support for PHP 8.2

### Changed
- [BREAKING] Header and footer config with dedicated classes instead of arrays
- Linting to `pint`
- Unit tests to `pest`

### Removed
- Support for PHP 7.4

## [2.1.0] - 2022-12-07
### Added
- Support for merging PDFs with mixed orientations (portrait and landscape)

### Changed
- Default header and footer are removed if header/footer-data is empty

## [2.0.0] - 2022-08-10
### Added
- Support for PHP 8.1
- Ability to get `TCPDF`-instance by calling `(new PdfMerge())->getPdf()`

### Changed
- [BREAKING] `PdfMerge::merge()` now returns `string` instead of `bool`
- `PdfMerge::merge()` now accepts `string $destination` as second parameter

### Removed
- Dropped support for PHP < 7.4

## [1.3.0] - 2021-10-20
### Added
- Support for PHP 8

## [1.2.0] - 2021-09-06
### Added
- Ability to set `tcpdf` footer and header data via PdfMerge constructor

## [1.1.1] - 2020-04-03
### Fixed
- removed unused imagick options

## [1.1.0] - 2020-03-30
### Added
- tcpdf dependency
- tcpdi implementation

### Removed
- fpdf and fpdi dependencies

## [1.0.0] - 2020-03-30

### Added
- Pdf merging implementation based on fpdf & fpdi
