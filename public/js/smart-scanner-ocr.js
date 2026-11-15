/* Free pack-photo reader for the Smart Scanner (browser OCR, nothing auto-filled). */
(function ($) {
    'use strict';
    if (!/\/products\/smart-scanner\/?$/.test(location.pathname)) { return; }

    var TESS_URL = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js';
    var MAX_SIDE = 1600, worker = null, loading = null, history = [], onProgress = null;

    var CSS = '' +
    '#ssx-wrap{margin-top:10px}' +
    '#ssx-btn{border-radius:8px;font-weight:600;padding:7px 14px}' +
    '#ssx-panel{display:none;margin-top:10px;padding:14px;border:1px solid #e3e8ee;border-radius:12px;background:#f8fafc}' +
    '#ssx-top{display:flex;gap:12px;align-items:center}' +
    '#ssx-thumb{width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #dde3ea;background:#fff}' +
    '#ssx-status{flex:1;font-size:14px;color:#334155}' +
    '#ssx-bar{height:6px;border-radius:3px;background:#e2e8f0;margin-top:8px;overflow:hidden}' +
    '#ssx-bar i{display:block;height:100%;width:0;background:#22c55e;transition:width .2s}' +
    '.ssx-h{margin:12px 0 6px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#64748b}' +
    '.ssx-chip{display:inline-block;margin:0 6px 6px 0;padding:6px 12px;border-radius:999px;border:1px solid #cbd5e1;background:#fff;font-size:14px;cursor:pointer}' +
    '.ssx-chip:hover{border-color:#22c55e;background:#f0fdf4}' +
    '.ssx-size{border-color:#93c5fd;background:#eff6ff}' +
    '.ssx-chip.used{opacity:.4;text-decoration:line-through}' +
    '#ssx-tools{margin-top:10px}#ssx-tools .btn{margin-right:6px}';

    function labelInput(text) {
        var $l = $('label').filter(function () {
            return $.trim($(this).text()).toLowerCase().indexOf(text) === 0;
        }).first();
        if (!$l.length) { return $(); }
        var id = $l.attr('for');
        if (id && $('#' + id).length) { return $('#' + id); }
        return $l.parent().find('input,select').first();
    }

    function status(t) { $('#ssx-status').text(t); }
    function bar(p) { $('#ssx-bar i').css('width', Math.round(p * 100) + '%'); }
    function clean(t) {
        return $.trim(String(t || '').replace(/[^\p{L}\p{N}&'.,%+\- ]+/gu, ' ').replace(/\s+/g, ' '));
    }

    function ensureUi() {
        var $name = labelInput('product name');
        if (!$name.length || $('#ssx-wrap').length) { return; }
        if (!$('#ssx-css').length) { $('head').append('<style id="ssx-css">' + CSS + '</style>'); }
        $name.after(
            '<div id="ssx-wrap">' +
            '<button type="button" class="btn btn-primary btn-sm" id="ssx-btn"><i class="fa fa-camera"></i> Read name from a photo (free)</button>' +
            '<input type="file" id="ssx-file" accept="image/*" capture="environment" style="display:none">' +
            '<div id="ssx-panel">' +
            '<div id="ssx-top"><img id="ssx-thumb" alt=""><div style="flex:1"><div id="ssx-status"></div><div id="ssx-bar"><i></i></div></div></div>' +
            '<div id="ssx-lines"></div><div id="ssx-sizes"></div>' +
            '<div id="ssx-tools" style="display:none">' +
            '<button type="button" class="btn btn-default btn-xs" id="ssx-undo">Undo last</button>' +
            '<button type="button" class="btn btn-default btn-xs" id="ssx-clear">Clear name</button>' +
            '<button type="button" class="btn btn-link btn-xs" id="ssx-close">Close</button></div>' +
            '</div></div>'
        );
    }

    function toCanvas(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file), img = new Image();
            img.onload = function () {
                var s = Math.min(1, MAX_SIDE / Math.max(img.width, img.height));
                var c = document.createElement('canvas');
                c.width = Math.max(1, Math.round(img.width * s));
                c.height = Math.max(1, Math.round(img.height * s));
                var g = c.getContext('2d');
                g.filter = 'grayscale(1) contrast(1.3)';
                g.drawImage(img, 0, 0, c.width, c.height);
                $('#ssx-thumb').attr('src', url);
                resolve(c);
            };
            img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('bad image')); };
            img.src = url;
        });
    }

    function loadTess() {
        if (window.Tesseract) { return Promise.resolve(); }
        if (loading) { return loading; }
        loading = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = TESS_URL; s.onload = resolve;
            s.onerror = function () { loading = null; reject(new Error('load')); };
            document.head.appendChild(s);
        });
        return loading;
    }

    function getWorker() {
        if (worker) { return Promise.resolve(worker); }
        return loadTess().then(function () {
            return window.Tesseract.createWorker('eng', 1, {
                logger: function (m) { if (onProgress) { onProgress(m); } }
            });
        }).then(function (w) { worker = w; return w; });
    }

    function addToName($chip, text) {
        var $n = labelInput('product name');
        history.push($n.val());
        $n.val($.trim($n.val() + ' ' + text)).trigger('input').trigger('change');
        $chip.addClass('used');
        $('#ssx-tools').show();
    }

    function chip(text, extra) {
        var $c = $('<span class="ssx-chip ' + (extra || '') + '"></span>').text(text);
        $c.on('click', function () { addToName($c, text); });
        return $c;
    }

    function show(d) {
        var raw = [];
        if (d.lines && d.lines.length) {
            d.lines.forEach(function (l) { raw.push({ t: clean(l.text), h: l.bbox ? l.bbox.y1 - l.bbox.y0 : 0, c: l.confidence }); });
        } else {
            String(d.text || '').split(/\r?\n/).forEach(function (t) { raw.push({ t: clean(t), h: 0, c: 100 }); });
        }
        var seen = {}, lines = raw.filter(function (l) {
            if (l.t.length < 3 || l.c < 45 || !/[A-Za-z]{2}/.test(l.t)) { return false; }
            var k = l.t.toLowerCase(); if (seen[k]) { return false; } seen[k] = 1; return true;
        }).sort(function (a, b) { return b.h - a.h; }).slice(0, 12);

        var sizes = [], m, re = /\b\d+(?:[.,]\d+)?\s?(?:ml|cl|ltr|l|mg|gm|g|kg|oz)\b/gi;
        while ((m = re.exec(String(d.text || '')))) {
            var s = m[0].replace(/\s+/g, ' ').toLowerCase();
            if (sizes.indexOf(s) < 0) { sizes.push(s); }
        }

        $('#ssx-lines, #ssx-sizes').empty();
        bar(1);
        if (!lines.length && !sizes.length) {
            status('No readable text. Hold the pack straight on, closer, in good light, then try again.');
            return;
        }
        status('Tap the parts of the name in order. This is only text read from your photo, so check it.');
        if (lines.length) {
            $('#ssx-lines').append('<div class="ssx-h">Text found</div>');
            lines.forEach(function (l) { $('#ssx-lines').append(chip(l.t)); });
        }
        if (sizes.length) {
            $('#ssx-sizes').append('<div class="ssx-h">Sizes found</div>');
            sizes.forEach(function (s) { $('#ssx-sizes').append(chip(s, 'ssx-size')); });
        }
        $('#ssx-tools').show();
    }

    function run(file) {
        history = [];
        $('#ssx-panel').show(); $('#ssx-lines, #ssx-sizes').empty(); $('#ssx-tools').hide();
        status('Preparing the photo...'); bar(0.05);
        $('#ssx-btn').prop('disabled', true);
        onProgress = function (m) {
            if (m.status === 'recognizing text') { status('Reading the text...'); bar(0.3 + 0.7 * m.progress); }
            else { status('Loading the reader (first time only, about 10 MB)...'); }
        };
        toCanvas(file)
            .then(function (c) { return getWorker().then(function (w) { return w.recognize(c); }); })
            .then(function (r) { show(r.data || {}); })
            .catch(function () { status('Could not read the photo. Check the internet connection, or type the name.'); bar(0); })
            .then(function () { $('#ssx-btn').prop('disabled', false); });
    }

    $(document).on('click', '#ssx-btn', function () { $('#ssx-file').trigger('click'); });
    $(document).on('change', '#ssx-file', function () {
        if (this.files && this.files[0]) { run(this.files[0]); this.value = ''; }
    });
    $(document).on('click', '#ssx-undo', function () {
        if (!history.length) { return; }
        labelInput('product name').val(history.pop()).trigger('input').trigger('change');
    });
    $(document).on('click', '#ssx-clear', function () {
        history = []; $('.ssx-chip').removeClass('used');
        labelInput('product name').val('').trigger('input').trigger('change').focus();
    });
    $(document).on('click', '#ssx-close', function () { $('#ssx-panel').hide(); });

    setInterval(ensureUi, 700);
    $(ensureUi);
})(jQuery);
