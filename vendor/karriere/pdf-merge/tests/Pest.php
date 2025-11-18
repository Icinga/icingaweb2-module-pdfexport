<?php

expect()->extend('toEqualPDF', function (string $expected) {
    if (filesize($expected) !== filesize($this->value)) {
        throw new Exception('The file size of the PDF does not equal the file size from the expected output.');
    }

    $pdf = new TCPDI();

    $expectedPageCount = $pdf->setSourceFile($expected);
    $actualPageCount = $pdf->setSourceFile($this->value);

    if ($expectedPageCount !== $actualPageCount) {
        throw new Exception('The page count of the PDF does not equal the page count from the expected output.');
    }

    return $this;
});

expect()->extend('toBeEmptyString', function () {
    return expect($this->value)->toEqual('');
});
