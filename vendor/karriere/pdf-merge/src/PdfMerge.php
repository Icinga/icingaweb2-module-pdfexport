<?php

namespace Karriere\PdfMerge;

use Karriere\PdfMerge\Config\FooterConfig;
use Karriere\PdfMerge\Config\HeaderConfig;
use Karriere\PdfMerge\Exceptions\FileNotFoundException;
use Karriere\PdfMerge\Exceptions\NoFilesDefinedException;
use TCPDI;

class PdfMerge
{
    /**
     * @var array<int, string>
     */
    private array $files = [];
    private TCPDI $pdf;

    /**
     * Passed parameters overrides settings for header and footer by calling tcpdf.php methods:
     * setHeaderData($ln='', $lw=0, $ht='', $hs='', $tc=array(0,0,0), $lc=array(0,0,0))
     * setFooterData($tc=array(0,0,0), $lc=array(0,0,0))
     * For more info about tcpdf, please read https://tcpdf.org/docs/
     */
    public function __construct(?HeaderConfig $headerConfig = null, ?FooterConfig $footerConfig = null)
    {
        $this->pdf = new TCPDI();
        $this->configureHeaderAndFooter($headerConfig, $footerConfig);
    }

    public function getPdf(): TCPDI
    {
        return $this->pdf;
    }

    /**
     * Adds a file to merge
     *
     * @throws FileNotFoundException
     */
    public function add(string $file): void
    {
        if (!file_exists($file)) {
            throw new FileNotFoundException($file);
        }

        $this->files[] = $file;
    }

    /**
     * Checks if the given file is already registered for merging
     */
    public function contains(string $file): bool
    {
        return in_array($file, $this->files);
    }

    /**
     * Resets the stored files
     */
    public function reset(): void
    {
        $this->files = [];
    }

    /**
     * Generates a merged PDF file from the already stored PDF files
     *
     * @throws NoFilesDefinedException
     */
    public function merge(string $outputFilename, string $destination = 'F'): string
    {
        if (count($this->files) === 0) {
            throw new NoFilesDefinedException();
        }

        foreach ($this->files as $file) {
            $pageCount = $this->pdf->setSourceFile($file);

            for ($i = 1; $i <= $pageCount; $i++) {
                $pageId = $this->pdf->ImportPage($i);
                $size = $this->pdf->getTemplateSize($pageId);

                $this->pdf->AddPage('', $size);
                $this->pdf->useTemplate($pageId, null, null, 0, 0, true);
            }
        }

        return $this->pdf->Output($outputFilename, $destination);
    }

    /**
     * @return array<int, string>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    private function configureHeaderAndFooter(?HeaderConfig $headerConfig, ?FooterConfig $footerConfig): void
    {
        if ($headerConfig) {
            $this->pdf->setHeaderData(
                $headerConfig->imagePath(),
                $headerConfig->logoWidthMM(),
                $headerConfig->title(),
                $headerConfig->text(),
                $headerConfig->textColor()->toArray(),
                $headerConfig->lineColor()->toArray(),
            );
        } else {
            $this->pdf->setPrintHeader(false);
        }

        if ($footerConfig) {
            $this->pdf->setFooterData($footerConfig->textColor()->toArray(), $footerConfig->lineColor()->toArray());
            $this->pdf->setFooterMargin($footerConfig->margin());
        } else {
            $this->pdf->setPrintFooter(false);
        }
    }
}
