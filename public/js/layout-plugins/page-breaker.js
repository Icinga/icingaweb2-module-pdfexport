// SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

"use strict";

(() => {
    Layout.registerPlugin('page-breaker', () => {
        let pageBreaksFor = document.querySelector('[data-pdfexport-page-breaks-at]');
        if (! pageBreaksFor) {
            return;
        }

        let pageBreaksAt = pageBreaksFor.dataset.pdfexportPageBreaksAt;
        if (! pageBreaksAt) {
            return;
        }

        let contentHeight = document.body.dataset.contentHeight;
        let items = Array.from(pageBreaksFor.querySelectorAll(':scope > ' + pageBreaksAt));

        let remainingHeight = contentHeight;
        items.forEach((item, i) => {
            let requiredHeight;
            if (i < items.length - 1) {
                requiredHeight = items[i + 1].getBoundingClientRect().top - item.getBoundingClientRect().top;
            } else {
                requiredHeight = item.parentElement.getBoundingClientRect().bottom - item.getBoundingClientRect().top;
            }

            if (remainingHeight < requiredHeight) {
                if (!! item.previousElementSibling) {
                    item.previousElementSibling.style.pageBreakAfter = 'always';
                    item.previousElementSibling.classList.add('page-break-follows');
                } else {
                    item.style.pageBreakAfter = 'always';
                    item.classList.add('page-break-follows');
                }

                remainingHeight = contentHeight;
            }

            remainingHeight -= requiredHeight;
        });
    });
})();
