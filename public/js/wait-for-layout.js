// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

new Promise((fulfill, reject) => {
    let timeoutId = setTimeout(() => reject('fail'), 10000);

    if (document.documentElement.dataset.layoutReady === 'yes') {
        clearTimeout(timeoutId);
        fulfill(null);
        return;
    }

    document.addEventListener('layout-ready', e => {
        clearTimeout(timeoutId);
        fulfill(e.detail);
    }, {
        once: true
    });
})
