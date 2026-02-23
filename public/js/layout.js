// SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

"use strict";

class Layout
{
    static #plugins = [];

    static registerPlugin(name, plugin) {
        this.#plugins.push([name, plugin]);
    }

    apply() {
        for (let [name, plugin] of Layout.#plugins) {
            try {
                plugin();
            } catch (error) {
                console.error('Layout plugin ' + name + ' failed to run: ' + error);
            }
        }

        this.finish();
    }

    finish() {
        document.documentElement.dataset.layoutReady = 'yes';
        document.dispatchEvent(new CustomEvent('layout-ready', {
            cancelable: false,
            bubbles: false
        }));
    }
}
