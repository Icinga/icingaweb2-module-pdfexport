(function () {
    var fs = require('fs');
    var args = require('system').args;
    var page = require('webpage').create();

    page.content = fs.read(args[1]);

    page.viewportSize = {
        height: 600,
        width: 600
    };

    page.paperSize = {
        format: 'A4',
        margin: '1cm',
        orientation: 'portrait',
        header: {
            height: '1cm',
            contents: phantom.callback(function (pageNum, numPages) {
                return '<h1>PDF Header</h1>';
            })
        },
        footer: {
            height: '1cm',
            contents: phantom.callback(function (pageNum, numPages) {
                return '<div style="text-align: right; font-size: 12px;">' + pageNum + ' / ' + numPages + '</div>';
            })
        }
    };

    page.onLoadFinished = function (status) {
        page.render(args[2]);
        phantom.exit();
    };
})();
