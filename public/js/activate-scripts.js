// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

function activateScripts(node) {
    if (node.tagName === 'SCRIPT') {
        node.parentNode.replaceChild(cloneScript(node), node);
    } else {
        var i = -1, children = node.childNodes;
        while (++i < children.length) {
            activateScripts(children[i]);
        }
    }

    return node;
}

function cloneScript(node) {
    var script  = document.createElement('script');
    script.text = node.innerHTML;

    var i = -1, attrs = node.attributes, attr;
    while (++i < attrs.length) {
        script.setAttribute((attr = attrs[i]).name, attr.value);
    }
    return script;
}

activateScripts(document.documentElement);
