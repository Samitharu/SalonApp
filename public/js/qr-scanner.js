/*
 * Thin wrapper around html5-qrcode used by the stylist portal and cashier.
 *   var s = QrScanner.create('elementId', { onCode, onState });
 *   s.start(); s.pause(); s.resume(); s.stop(); s.scanFile(file)
 * onState(state, message): 'starting' | 'live' | 'error' | 'stopped'
 */
(function () {
    'use strict';

    function extractRef(text) {
        if (!text) return null;
        var t = String(text).trim();
        if (/^[A-Z0-9]{5,12}$/i.test(t)) return t.toUpperCase();
        // Older QR codes contained "Ref: XXXXXXXX" among other lines.
        var m = t.replace(/\\n/g, '\n').match(/Ref:\s*([A-Z0-9]{5,12})/i);
        if (m) return m[1].toUpperCase();
        var line = t.match(/^([A-Z0-9]{5,12})$/im);
        return line ? line[1].toUpperCase() : null;
    }

    function create(elementId, opts) {
        opts = opts || {};
        var scanner = null;
        var running = false;
        var busy = false;
        var state = function (s, msg) { if (opts.onState) opts.onState(s, msg); };

        function ensure() {
            if (!window.Html5Qrcode) throw new Error('Scanner library failed to load.');
            if (!scanner) scanner = new Html5Qrcode(elementId, { verbose: false });
            return scanner;
        }

        function handle(text) {
            if (busy) return;
            var ref = extractRef(text);
            if (!ref) { state('live', 'QR code not recognised. Try again or type the reference.'); return; }
            busy = true;
            pause();
            if (navigator.vibrate) navigator.vibrate(80);
            Promise.resolve(opts.onCode && opts.onCode(ref, text)).finally(function () { busy = false; });
        }

        function start() {
            if (running) return Promise.resolve();
            var s;
            try { s = ensure(); } catch (e) { state('error', e.message); return Promise.resolve(); }
            state('starting', 'Starting camera…');
            var config = { fps: 12, qrbox: { width: 220, height: 220 }, aspectRatio: 1.333, experimentalFeatures: { useBarCodeDetectorIfSupported: true } };
            return s.start({ facingMode: 'environment' }, config, handle, function () {})
                .then(function () { running = true; state('live', 'Point the camera at the QR code.'); })
                .catch(function (err) {
                    var msg = String(err && err.message || err || '');
                    if (/NotAllowed|Permission/i.test(msg)) msg = 'Camera access was blocked. Allow camera access in your browser settings, or type the reference.';
                    else if (/NotFound|no camera|Requested device not found/i.test(msg)) msg = 'No camera found on this device. Type the reference instead.';
                    else if (location.protocol !== 'https:' && location.hostname !== 'localhost') msg = 'The camera needs a secure (https) connection. Type the reference instead.';
                    else msg = 'Camera unavailable. Type the reference instead.';
                    state('error', msg);
                });
        }

        function pause() { try { if (running) scanner.pause(true); } catch (e) {} }
        function resume() { try { if (running) { scanner.resume(); state('live', 'Point the camera at the QR code.'); } } catch (e) {} }
        function stop() {
            if (!running || !scanner) return Promise.resolve();
            running = false;
            return scanner.stop().catch(function () {}).then(function () { state('stopped'); });
        }
        function scanFile(file) {
            var s;
            try { s = ensure(); } catch (e) { return Promise.reject(e); }
            var wasRunning = running;
            var p = wasRunning ? stop() : Promise.resolve();
            return p.then(function () { return s.scanFile(file, false); }).then(function (text) {
                var ref = extractRef(text);
                if (!ref) throw new Error('QR code not recognised.');
                return ref;
            });
        }

        return { start: start, pause: pause, resume: resume, stop: stop, scanFile: scanFile, isRunning: function () { return running; } };
    }

    window.QrScanner = { create: create, extractRef: extractRef };
})();
