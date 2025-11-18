<?php

use Karriere\PdfMerge\Config\FooterConfig;
use Karriere\PdfMerge\Config\HeaderConfig;
use Karriere\PdfMerge\Config\RGB;
use Karriere\PdfMerge\Exceptions\FileNotFoundException;
use Karriere\PdfMerge\Exceptions\NoFilesDefinedException;
use Karriere\PdfMerge\PdfMerge;

beforeEach(function () {
    $this->pdfMerge = new PdfMerge();
    $this->dummyFile = __DIR__ . '/files/dummy.pdf';
    $this->outputFile = __DIR__ . '/output.pdf';
});

it('returns PDF instance', function () {
    expect($this->pdfMerge)->getPdf()->toBeInstanceOf(TCPDI::class);
});

it('throws exception when trying to add not existing page', function () {
    ($this->pdfMerge)->add('/foo.pdf');
})->throws(FileNotFoundException::class);

it('checks if file was already added', function () {
    expect($this->pdfMerge)->contains($this->dummyFile)->toBeFalse();
    $this->pdfMerge->add($this->dummyFile);
    expect($this->pdfMerge)->contains($this->dummyFile)->toBeTrue();
});

it('resets files to merge', function () {
    $this->pdfMerge->add($this->dummyFile);
    $this->pdfMerge->reset();

    expect($this->pdfMerge)->contains($this->dummyFile)->toBeFalse();
});

it('generates merged file', function () {
    $this->pdfMerge->add($this->dummyFile);
    $this->pdfMerge->add($this->dummyFile);

    expect($this->pdfMerge)->merge($this->outputFile)->toBeEmptyString();
    expect($this->outputFile)->toEqualPDF(__DIR__ . '/files/expected/output.pdf');
});

it('merges portrait and landscape files', function () {
    $this->pdfMerge->add($this->dummyFile);
    $this->pdfMerge->add(__DIR__ . '/files/dummy_landscape.pdf');

    expect($this->pdfMerge)->merge($this->outputFile)->toBeEmptyString();
    expect($this->outputFile)->toEqualPDF(__DIR__ . '/files/expected/output_mixed_orientation.pdf');
});

it('adds header to merged PDF', function () {
    copy(__DIR__ . '/files/header_logo.jpg', K_PATH_IMAGES . 'header_logo.png');

    $pdfMerge = new PdfMerge(new HeaderConfig(imagePath: 'header_logo.png', logoWidthMM: 20, title: 'Test'));

    $pdfMerge->add($this->dummyFile);
    $pdfMerge->add($this->dummyFile);

    expect($pdfMerge)->merge($this->outputFile)->toBeEmptyString();
    expect($this->outputFile)->toEqualPDF(__DIR__ . '/files/expected/output_with_header.pdf');
});

it('adds full header and full footer to merged PDF', function () {
    copy(__DIR__ . '/files/header_logo.jpg', K_PATH_IMAGES . 'header_logo.png');

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

    $pdfMerge->add($this->dummyFile);
    $pdfMerge->add($this->dummyFile);

    expect($pdfMerge)->merge($this->outputFile)->toBeEmptyString();
    expect($this->outputFile)->toEqualPDF(__DIR__ . '/files/expected/output_with_header_and_footer.pdf');
});

it('throws exception when no files were added', function () {
    $this->pdfMerge->merge('/foo.pdf');
})->throws(NoFilesDefinedException::class);
