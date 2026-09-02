// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

new Promise((fulfill, reject) => {
    if (document.documentElement.dataset.layoutReady === 'yes') {
        fulfill(null);
        return;
    }

    const onLayoutReady = e => {
        clearTimeout(timeoutId);
        fulfill(e.detail);
    };

    const timeoutId = setTimeout(() => {
        document.removeEventListener('layout-ready', onLayoutReady);
        reject('fail');
    }, 10000);

    document.addEventListener('layout-ready', onLayoutReady, {
        once: true
    });
})
