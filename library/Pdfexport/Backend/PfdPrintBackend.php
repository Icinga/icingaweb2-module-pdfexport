<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Pdfexport\Backend;

use Icinga\Module\Pdfexport\PrintableHtmlDocument;

/**
 * PDF renderer with explicit capability checks and resource cleanup
 */
interface PfdPrintBackend
{
    /**
     * Render a document as a PDF
     *
     * A backend can render successive documents until it is closed.
     *
     * @param PrintableHtmlDocument $document HTML content and print settings
     *
     * @return string PDF contents
     *
     * @throws \Exception If rendering fails
     */
    public function toPdf(PrintableHtmlDocument $document): string;

    /**
     * Check whether the connected browser can print PDFs
     *
     * The check may contact the browser or print a probe page. Expected browser
     * and connection failures return false.
     *
     * @return bool
     */
    public function isSupported(): bool;

    /**
     * Check whether the generated PDFs support merging with a cover page
     *
     * @return bool
     */
    public function supportsCoverPage(): bool;

    /**
     * Release browser connections and resources owned by the backend
     *
     * Repeated calls are safe. Reuse after closing depends on the backend.
     * Destruction attempts cleanup without propagating failures.
     *
     * @return void
     *
     * @throws \Exception If explicit cleanup fails
     */
    public function close(): void;
}
