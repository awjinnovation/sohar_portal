/*
 * All tickets on a page at once (resources/views/partials/ticket-export.blade.php):
 * one PDF with a page per ticket (download, or share to WhatsApp / mail where
 * the browser shares files) and all tickets as PNG images. Each ticket card is
 * captured with html2canvas, so the PDF looks like the page (Arabic included)
 * and embeds no font. booking-web/src/pdf.ts is the same PDF writer.
 *
 * Markup: <div data-ticket-export data-cards="<selector>" data-file="<name>"
 * data-title="…" data-preparing="…"> with buttons data-export="pdf|share|images"
 * and an element [data-export-error]. Inside a card, [data-html2canvas-ignore]
 * leaves an element out (its own save button).
 */
(function () {
    'use strict';

    var HTML2CANVAS = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
    var SCALE = 3;

    function loadHtml2canvas() {
        if (window.html2canvas) return Promise.resolve(window.html2canvas);
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = HTML2CANVAS;
            s.onload = function () { window.html2canvas ? resolve(window.html2canvas) : reject(new Error('html2canvas')); };
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    // --- PDF 1.4: one JPEG per page, the page the size of the image -------------
    function pdfText(s) {
        var hex = 'FEFF';
        for (var i = 0; i < s.length; i++) hex += ('0000' + s.charCodeAt(i).toString(16)).slice(-4).toUpperCase();
        return '<' + hex + '>';
    }

    function num(n) { return String(Math.round(n * 100) / 100); }

    function jpegPagesToPdf(pages, title) {
        var enc = new TextEncoder();
        var chunks = [], offsets = [], length = 0;
        function push(data) {
            var bytes = typeof data === 'string' ? enc.encode(data) : data;
            chunks.push(bytes);
            length += bytes.length;
        }
        function obj(id, body) {
            offsets[id] = length;
            push(id + ' 0 obj\n');
            body();
            push('\nendobj\n');
        }
        function pageId(i) { return 4 + i * 3; }
        var size = 4 + pages.length * 3;

        push('%PDF-1.4\n%âãÏÓ\n');
        obj(1, function () { push('<< /Type /Catalog /Pages 2 0 R >>'); });
        obj(2, function () {
            push('<< /Type /Pages /Count ' + pages.length + ' /Kids [' + pages.map(function (_, i) { return pageId(i) + ' 0 R'; }).join(' ') + '] >>');
        });
        obj(3, function () { push('<< /Title ' + pdfText(title || '') + ' /Producer (Mawsim) >>'); });
        pages.forEach(function (p, i) {
            var id = pageId(i);
            obj(id, function () {
                push('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + num(p.width) + ' ' + num(p.height) + '] ' +
                    '/Resources << /XObject << /Im' + i + ' ' + (id + 1) + ' 0 R >> /ProcSet [/PDF /ImageC] >> /Contents ' + (id + 2) + ' 0 R >>');
            });
            obj(id + 1, function () {
                push('<< /Type /XObject /Subtype /Image /Width ' + p.pixelWidth + ' /Height ' + p.pixelHeight +
                    ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + p.jpeg.length + ' >>\nstream\n');
                push(p.jpeg);
                push('\nendstream');
            });
            var content = 'q ' + num(p.width) + ' 0 0 ' + num(p.height) + ' 0 0 cm /Im' + i + ' Do Q';
            obj(id + 2, function () { push('<< /Length ' + content.length + ' >>\nstream\n' + content + '\nendstream'); });
        });

        var xref = length;
        push('xref\n0 ' + size + '\n0000000000 65535 f \n');
        for (var id = 1; id < size; id++) push(('0000000000' + offsets[id]).slice(-10) + ' 00000 n \n');
        push('trailer\n<< /Size ' + size + ' /Root 1 0 R /Info 3 0 R >>\nstartxref\n' + xref + '\n%%EOF\n');
        return new Blob(chunks, { type: 'application/pdf' });
    }

    // --- device helpers -----------------------------------------------------
    function toBlob(canvas, type, quality) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (b) { b ? resolve(b) : reject(new Error('toBlob')); }, type, quality);
        });
    }

    function download(blob, name) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = name;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
    }

    function canShare(files) { return !!(navigator.canShare && navigator.canShare({ files: files })); }

    /** The share sheet (WhatsApp, Save Image, Files…); false when the browser cannot share files. */
    function share(files, title) {
        if (!canShare(files)) return Promise.resolve(false);
        return navigator.share({ files: files, title: title }).then(function () { return true; }, function (e) {
            if (e && e.name === 'AbortError') return true;
            throw e;
        });
    }

    var touch = function () { return !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches); };
    var wait = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };

    function capture(h2c, card) {
        return h2c(card, { scale: SCALE, backgroundColor: '#ffffff', logging: false, useCORS: true });
    }

    // --- the bar --------------------------------------------------------------
    function init(bar) {
        var cards = function () { return Array.prototype.slice.call(document.querySelectorAll(bar.getAttribute('data-cards'))); };
        var base = bar.getAttribute('data-file') || 'tickets';
        var title = bar.getAttribute('data-title') || document.title;
        var error = bar.querySelector('[data-export-error]');
        var buttons = Array.prototype.slice.call(bar.querySelectorAll('[data-export]'));
        var shareBtn = bar.querySelector('[data-export="share"]');
        if (shareBtn && canShare([new File([new Uint8Array(1)], 'x.pdf', { type: 'application/pdf' })])) shareBtn.hidden = false;

        function pdf(h2c) {
            var pages = [];
            return cards().reduce(function (chain, card) {
                return chain.then(function () { return capture(h2c, card); }).then(function (canvas) {
                    return toBlob(canvas, 'image/jpeg', 0.92).then(function (b) { return b.arrayBuffer(); }).then(function (buf) {
                        pages.push({ jpeg: new Uint8Array(buf), pixelWidth: canvas.width, pixelHeight: canvas.height, width: canvas.width / SCALE, height: canvas.height / SCALE });
                    });
                });
            }, Promise.resolve()).then(function () { return jpegPagesToPdf(pages, title); });
        }

        function images(h2c) {
            var files = [];
            return cards().reduce(function (chain, card, i) {
                return chain.then(function () { return capture(h2c, card); }).then(function (canvas) {
                    return toBlob(canvas, 'image/png').then(function (b) {
                        files.push(new File([b], base.replace(/_tickets$/, '') + '_ticket_' + (i + 1) + '.png', { type: 'image/png' }));
                    });
                });
            }, Promise.resolve()).then(function () {
                return (touch() ? share(files, title) : Promise.resolve(false)).then(function (shared) {
                    if (shared) return;
                    return files.reduce(function (chain, f, i) {
                        // Browsers drop rapid back-to-back downloads; space them out.
                        return chain.then(function () { return i ? wait(400) : null; }).then(function () { download(f, f.name); });
                    }, Promise.resolve());
                });
            });
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = btn.getAttribute('data-export');
                var label = btn.querySelector('span');
                var text = label ? label.textContent : '';
                buttons.forEach(function (b) { b.disabled = true; });
                if (label) label.textContent = bar.getAttribute('data-preparing') || text;
                if (error) error.hidden = true;
                loadHtml2canvas().then(function (h2c) {
                    if (action === 'images') return images(h2c);
                    return pdf(h2c).then(function (file) {
                        var name = base + '.pdf';
                        if (action !== 'share') return download(file, name);
                        return share([new File([file], name, { type: 'application/pdf' })], title).then(function (shared) {
                            if (!shared) download(file, name);
                        });
                    });
                }).catch(function () {
                    if (error) error.hidden = false;
                }).then(function () {
                    buttons.forEach(function (b) { b.disabled = false; });
                    if (label) label.textContent = text;
                });
            });
        });
    }

    document.querySelectorAll('[data-ticket-export]').forEach(init);
})();
