/* Icinga PDF Export | (c) 2021 Icinga GmbH | GPLv2 */

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
                if (!! item.previousSibling) {
                    item.previousSibling.style.pageBreakAfter = 'always';
                    item.previousSibling.classList.add('page-break-follows');
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
