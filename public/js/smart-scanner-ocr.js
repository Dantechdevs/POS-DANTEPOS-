/* Free pack-photo reader for the Smart Scanner.
   OCR runs in the browser (Tesseract.js). Nothing is auto-filled: you tap the lines you want. */
(function ($) {
    'use strict';

    if (!/\/products\/smart-scanner\/?$/.test(location.pathname)) { return; }

    var TESS_URL = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js';
    var MAX_SIDE = 1600;
    var worker = null, loading = null;

    function labelInput(text) {
        var $l = $('label').filter(function () {
            return $.trim($(this).text()).toLowerCase().indexOf(text) === 0;
        }).first();
        if (!$l.length) { return $(); }
        var id = $l.attr('for');
        if (id && $('#' + id).length) { return $('#' + id); }
        return $l.parent().find('input,select').first();
    }

    function msg(text, type) {
        $('#ssx-msg').attr('class', 'text-' + type).text(text);
    }

    function busy(on) { $('#ssx-btn').prop('disabled', on); }

    function clean(t) {
        return $.trim(String(t || '').replace(/[^\p{L}\p{N}&'.,%+\- ]+/gu, ' ').replace(/\s+/g, ' '));
    }

    function ensureUi() {
        var $name = labelInput('product name');
        if (!$name.length || $('#ssx-wrap').length) { return; }
        $name.after(
            '<div id="ssx-wrap" style="margin-top:8px">' +
            '<button type="button" class="btn btn-default btn-sm" id="ssx-btn"><i class="fa fa-camera"></i> Read from photo (free)</button> ' +
            '<input type="file" id="ssx-file" accept="image/*" capture="environment" style="display:none">' +
            '<div id="ssx-msg" style="margin-top:6px"></div>' +
            '<div id="ssx-chips" style="margin-top:6px"></div></div>'
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
                URL.revokeObjectURL(url);
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
            s.src = TESS_URL;
            s.onload = resolve;
            s.onerror = function () { loading = null; reject(new Error('load')); };
            document.head.appendChild(s);
        });
        return loading;
    }

    function getWorker() {
        if (worker) { return Promise.resolve(worker); }
        return loadTess().then(function () { return window.Tesseract.createWorker('eng'); })
            .then(function (w) { worker = w; return w; });
    }

    function addToName(text) {
        var $name = labelInput('product name');
        $name.val($.trim($name.val() + ' ' + text)).trigger('input').trigger('change').focus();
    }

    function chip(text, cls) {
        return $('<button type="button" class="btn btn-xs ' + cls + '" style="margin:0 4px 4px 0"></button>')
            .text(text)
            .on('click', function () { addToName(text); });
    }

    function show(d) {
        var raw = [];
        if (d.lines && d.lines.length) {
            d.lines.forEach(function (l) {
                raw.push({ t: clean(l.text), h: l.bbox ? l.bbox.y1 - l.bbox.y0 : 0, c: l.confidence });
            });
        } else {
            String(d.text || '').split(/\r?\n/).forEach(function (t) { raw.push({ t: clean(t), h: 0, c: 100 }); });
        }

        var seen = {}, lines = raw.filter(function (l) {
            if (l.t.length < 3 || l.c < 45 || !/[A-Za-z]{2}/.test(l.t)) { return false; }
            var k = l.t.toLowerCase();
            if (seen[k]) { return false; }
            seen[k] = true;
            return true;
        }).sort(function (a, b) { return b.h - a.h; }).slice(0, 12);

        var sizes = [], m, re = /\b\d+(?:[.,]\d+)?\s?(?:ml|cl|ltr|l|mg|gm|g|kg|oz)\b/gi;
        while ((m = re.exec(String(d.text || '')))) {
            var s = m[0].replace(/\s+/g, ' ').toLowerCase();
            if (sizes.indexOf(s) < 0) { sizes.push(s); }
        }

        var $box = $('#ssx-chips').empty();
        if (!lines.length && !sizes.length) {
            msg('No readable text found. Photograph the front label closer, in good light.', 'danger');
            return;
        }
        msg('Tap the lines that make up the product name, in order. Then check the name. Nothing is guessed: this is only text read from your photo.', 'info');
        lines.forEach(function (l) { $box.append(chip(l.t, 'btn-default')); });
        if (sizes.length) {
            $box.append('<div style="margin-top:4px"><small>Sizes seen:</small></div>');
            sizes.forEach(function (s) { $box.append(chip(s, 'btn-info')); });
        }
    }

    function run(file) {
        $('#ssx-chips').empty();
        msg('Reading the photo. The first time can take a minute while the reader downloads.', 'info');
        busy(true);
        toCanvas(file)
            .then(function (c) { return getWorker().then(function (w) { return w.recognize(c); }); })
            .then(function (r) { show(r.data || {}); })
            .catch(function () { msg('Could not read the photo. Check the internet connection, or type the name.', 'danger'); })
            .then(function () { busy(false); });
    }

    $(document).on('click', '#ssx-btn', function () { $('#ssx-file').trigger('click'); });
    $(document).on('change', '#ssx-file', function () {
        if (this.files && this.files[0]) { run(this.files[0]); this.value = ''; }
    });

    setInterval(ensureUi, 700);
    $(ensureUi);
})(jQuery);
