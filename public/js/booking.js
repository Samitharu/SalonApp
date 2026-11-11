/* Booking wizard: service → date & time → details → confirmation. */
(function () {
    'use strict';

    var cfgEl = document.getElementById('booking-config');
    if (!cfgEl) return;

    var cfg = JSON.parse(cfgEl.textContent);
    var esc = UI.escapeHtml;
    var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

    var state = {
        step: 1,
        serviceId: null,
        staffId: '',          // '' = any stylist
        date: null,
        slot: null,           // { start, end, stylists }
        slots: [],
        slotsRequest: 0
    };

    var mobileNext = $('#mobileNext');
    var slotArea = $('#slotArea');
    var editing = cfg.mode === 'edit' && cfg.current;

    // ── Helpers ────────────────────────────────────────────────────────
    function service() { return state.serviceId ? cfg.services[state.serviceId] : null; }

    function prettyDate(iso, long) {
        var p = iso.split('-');
        var d = new Date(+p[0], +p[1] - 1, +p[2]);
        return d.toLocaleDateString(undefined, long
            ? { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }
            : { weekday: 'short', day: 'numeric', month: 'short' });
    }

    // Offers go by the visit date. Until one is picked, today's price shows.
    function offerFor(svc, date) {
        var best = null;
        (svc.offers || []).forEach(function (o) {
            if (date >= o.from && date <= o.to && (!best || o.amount > best.amount)) best = o;
        });
        return best;
    }

    function priced() {
        var svc = service();
        if (!svc) return null;
        // Changing only the time or stylist keeps the price the booking already has.
        if (editing && String(state.serviceId) === String(cfg.current.serviceId) && (!state.date || state.date === cfg.current.date)) {
            return { price: cfg.current.price, was: cfg.current.offer ? svc.price : null, offerName: cfg.current.offer };
        }
        var offer = offerFor(svc, state.date || cfg.today);
        return offer ? { price: offer.price, was: svc.price, offerName: offer.name, offer: offer } : { price: svc.price, was: null, offerName: null };
    }

    function stylistName() {
        if (state.staffId && cfg.staff[state.staffId]) return cfg.staff[state.staffId].name;
        if (state.slot && state.slot.stylists && state.slot.stylists.length === 1) return state.slot.stylists[0].name;
        return 'Any stylist';
    }

    function canAdvance(step) {
        if (step === 1) return !!state.serviceId;
        if (step === 2) return !!(state.date && state.slot);
        return true;
    }

    // ── Step navigation ────────────────────────────────────────────────
    function goTo(step) {
        state.step = step;
        $$('[data-step]').forEach(function (p) { p.hidden = +p.dataset.step !== step; });
        $$('[data-step-ind]').forEach(function (li) {
            var n = +li.dataset.stepInd;
            li.classList.toggle('is-current', n === step);
            li.classList.toggle('is-done', n < step);
            if (n === step) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
        });
        var heading = $('[data-step="' + step + '"] h1, [data-step="' + step + '"] h2');
        if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus({ preventScroll: true }); }
        window.scrollTo({ top: 0 });

        if (step === 2 && !state.date) {
            var currentPill = editing ? $('.date-pill[data-date="' + cfg.current.date + '"]:not(:disabled)') : null;
            if (currentPill) selectDate(cfg.current.date); else autoPickDate();
        }
        if (step === 3 && editing) fillReview();
        if (step === 3) setTimeout(function () { var n = $('#bkName'); if (n && !n.value) n.focus(); }, 50);
        refresh();
    }

    document.addEventListener('click', function (e) {
        var next = e.target.closest('[data-next]');
        if (next && !next.disabled) { goTo(+next.dataset.next); return; }
        var back = e.target.closest('[data-back]');
        if (back) goTo(+back.dataset.back);
    });

    mobileNext.addEventListener('click', function () {
        if (state.step < 3) { if (canAdvance(state.step)) goTo(state.step + 1); }
        else $('#detailsForm').requestSubmit();
    });

    // Desktop: Continue sits in the summary card, under the total.
    var summaryNext = $('#summaryNext');
    if (summaryNext) {
        summaryNext.addEventListener('click', function () {
            if (state.step < 3 && canAdvance(state.step)) goTo(state.step + 1);
        });
    }

    // ── Step 1: service ────────────────────────────────────────────────
    function selectService(id) {
        id = String(id);
        if (!cfg.services[id]) return;
        state.serviceId = id;
        $$('[data-service]').forEach(function (b) { b.setAttribute('aria-checked', String(b.dataset.service === id)); });
        resetSlot();
        if (state.date) loadSlots();
        refresh();
    }

    $$('[data-service]').forEach(function (btn) {
        btn.addEventListener('click', function () { selectService(btn.dataset.service); });
    });

    // Arrow-key navigation inside radio groups.
    $$('[role="radiogroup"]').forEach(function (group) {
        group.addEventListener('keydown', function (e) {
            if (['ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight'].indexOf(e.key) === -1) return;
            // Skip options hidden by the service search / category filter.
            var items = $$('[role="radio"]', group).filter(function (el) { return el.offsetParent !== null; });
            var i = items.indexOf(document.activeElement);
            if (i === -1) return;
            e.preventDefault();
            var n = (e.key === 'ArrowDown' || e.key === 'ArrowRight') ? (i + 1) % items.length : (i - 1 + items.length) % items.length;
            items[n].focus();
            items[n].click();
        });
    });

    // ── Service search & category filter ───────────────────────────────
    var svcSearch = $('#svcSearch');
    var svcChips = $$('#svcChips [data-cat]');
    var activeCat = 'all';

    function norm(s) {
        return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/[^a-z0-9]+/g, ' ').trim();
    }

    function filterServices(announce) {
        if (!svcSearch) return;
        var words = norm(svcSearch.value).split(' ').filter(Boolean);
        var shown = 0;
        $$('#svcList [data-group]').forEach(function (group) {
            var inCat = activeCat === 'all' || group.dataset.group === activeCat;
            var count = 0;
            $$('[data-service]', group).forEach(function (btn) {
                var hay = norm(btn.dataset.search);
                var ok = inCat && words.every(function (w) { return hay.indexOf(w) !== -1; });
                btn.hidden = !ok;
                if (ok) count++;
            });
            group.hidden = count === 0;
            shown += count;
        });
        $('#svcEmpty').hidden = shown > 0;
        $('#svcTerm').textContent = svcSearch.value.trim();
        if (announce) $('#svcStatus').textContent = shown + (shown === 1 ? ' service' : ' services') + ' shown';
    }

    function setCategory(cat, announce) {
        activeCat = cat;
        svcChips.forEach(function (c) { c.setAttribute('aria-pressed', String(c.dataset.cat === cat)); });
        filterServices(announce);
    }

    if (svcSearch) {
        var startChip = svcChips.filter(function (c) { return c.getAttribute('aria-pressed') === 'true'; })[0];
        activeCat = startChip ? startChip.dataset.cat : 'all';
        svcChips.forEach(function (chip) {
            chip.addEventListener('click', function () { setCategory(chip.dataset.cat, true); });
        });
        svcSearch.addEventListener('input', function () { filterServices(true); });
        svcSearch.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && svcSearch.value) { svcSearch.value = ''; filterServices(true); }
            if (e.key === 'Enter') {
                e.preventDefault();
                var first = $('#svcList [data-service]:not([hidden])');
                if (first) first.focus();
            }
        });
        $('#svcClear').addEventListener('click', function () {
            svcSearch.value = '';
            setCategory('all', true);
            svcSearch.focus();
        });
        filterServices(false);
    }

    // ── Step 2: stylist, date, slots ───────────────────────────────────
    $$('[data-staff]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            state.staffId = btn.dataset.staff;
            $$('[data-staff]').forEach(function (b) { b.setAttribute('aria-checked', String(b === btn)); });
            resetSlot();
            if (state.date) loadSlots();
            refresh();
        });
    });

    $$('.date-pill').forEach(function (pill) {
        pill.addEventListener('click', function () { selectDate(pill.dataset.date); });
    });

    function selectDate(date) {
        state.date = date;
        $$('.date-pill').forEach(function (p) {
            var on = p.dataset.date === date;
            p.setAttribute('aria-pressed', String(on));
            if (on) {
                $('#monthLabel').textContent = p.dataset.month;
                // Scroll the strip only (not the page) so the pill is visible.
                var strip = p.parentElement;
                strip.scrollTo({ left: p.offsetLeft - strip.clientWidth / 2 + p.clientWidth / 2, behavior: 'smooth' });
            }
        });
        if (calPanel && !calPanel.hidden) renderCalendar();
        resetSlot();
        loadSlots();
        refresh();
    }

    // Dates with an offer get a mark, and the note says what the picked date costs.
    function renderOffers() {
        var svc = service();
        $$('.date-pill').forEach(function (p) {
            p.classList.toggle('has-offer', !!(svc && offerFor(svc, p.dataset.date)));
        });

        var note = $('#offerNote');
        var p = priced();
        var other = svc && (svc.offers || [])[0];
        if (!svc || !state.date || !(p.offer || other)) { note.hidden = true; return; }

        note.hidden = false;
        note.className = 'alert mb-6 ' + (p.offer ? 'alert-success' : 'alert-info');
        note.innerHTML = '<i class="fa-solid fa-tag" aria-hidden="true"></i><div>' + (p.offer
            ? '<strong>' + esc(p.offer.name) + '</strong>: ' + esc(p.offer.label) + ' on this date. You pay ' + esc(p.offer.price) + ' instead of ' + esc(svc.price) + '.'
            : '<strong>' + esc(other.name) + '</strong> (' + esc(other.label) + ') is for visits ' + esc(other.period) + '. Pick a marked date to get it.') + '</div>';
    }

    // ── Month calendar (covers the whole booking window) ──────────────
    var calPanel = $('#calPanel');
    var calToggle = $('#calToggle');
    var bookable = {};           // 'Y-m-d' → open?
    var dateKeys = [];
    $$('.date-pill').forEach(function (p) { bookable[p.dataset.date] = !p.disabled; dateKeys.push(p.dataset.date); });
    var firstMonth = dateKeys.length ? dateKeys[0].slice(0, 7) : null;
    var lastMonth = dateKeys.length ? dateKeys[dateKeys.length - 1].slice(0, 7) : null;
    var calMonth = firstMonth;   // 'YYYY-MM' currently shown
    var todayKey = dateKeys[0];

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function shiftMonth(ym, delta) {
        var p = ym.split('-'), d = new Date(+p[0], +p[1] - 1 + delta, 1);
        return d.getFullYear() + '-' + pad(d.getMonth() + 1);
    }

    function renderCalendar() {
        if (!calMonth) return;
        var p = calMonth.split('-'), y = +p[0], m = +p[1] - 1;
        var first = new Date(y, m, 1);
        var daysInMonth = new Date(y, m + 1, 0).getDate();
        var lead = (first.getDay() + 6) % 7; // Monday-first grid

        $('#calTitle').textContent = first.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        $('[data-cal="prev"]').disabled = calMonth <= firstMonth;
        $('[data-cal="next"]').disabled = calMonth >= lastMonth;

        var html = '';
        for (var i = 0; i < lead; i++) html += '<span class="cal-day is-blank" aria-hidden="true"></span>';
        for (var d = 1; d <= daysInMonth; d++) {
            var key = calMonth + '-' + pad(d);
            var inWindow = key in bookable;
            var open = inWindow && bookable[key];
            var label = new Date(y, m, d).toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' }) +
                (!inWindow ? ' (not bookable)' : (!open ? ' (closed)' : ''));
            html += '<button type="button" class="cal-day' + (key === todayKey ? ' is-today' : '') + (service() && offerFor(service(), key) ? ' has-offer' : '') + '" data-day="' + key + '"' +
                ' aria-pressed="' + (key === state.date) + '" aria-label="' + esc(label) + '"' + (open ? '' : ' disabled') + '>' + d + '</button>';
        }
        $('#calGrid').innerHTML = html;
    }

    function setCalendar(open) {
        calPanel.hidden = !open;
        calToggle.setAttribute('aria-expanded', String(open));
        if (open) {
            if (state.date) calMonth = state.date.slice(0, 7);
            renderCalendar();
            var focusEl = $('#calGrid [aria-pressed="true"]') || $('#calGrid .cal-day:not(:disabled)');
            if (focusEl) focusEl.focus();
        }
    }

    if (calToggle) {
        calToggle.addEventListener('click', function () { setCalendar(calPanel.hidden); });
        calPanel.addEventListener('click', function (e) {
            var nav = e.target.closest('[data-cal]');
            if (nav && !nav.disabled) { calMonth = shiftMonth(calMonth, nav.dataset.cal === 'next' ? 1 : -1); renderCalendar(); return; }
            var day = e.target.closest('.cal-day[data-day]');
            if (day && !day.disabled) {
                selectDate(day.dataset.day);
                setCalendar(false);
                slotArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
        calPanel.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { setCalendar(false); calToggle.focus(); return; }
            var day = e.target.closest('.cal-day[data-day]');
            var moves = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
            if (!day || !(e.key in moves)) return;
            e.preventDefault();
            var days = $$('#calGrid .cal-day[data-day]');
            var idx = days.indexOf(day) + moves[e.key];
            if (days[idx]) days[idx].focus();
        });
    }

    function autoPickDate() {
        var first = $('.date-pill:not(:disabled)');
        if (first) selectDate(first.dataset.date);
    }

    function resetSlot() { state.slot = null; }

    function loadSlots() {
        if (!state.serviceId || !state.date) return;
        var reqId = ++state.slotsRequest;
        slotArea.innerHTML = '<div class="loading-row"><span class="spinner" aria-hidden="true"></span> Checking availability…</div>';

        var url = cfg.routes.slots + '?service_id=' + encodeURIComponent(state.serviceId) +
            '&date=' + encodeURIComponent(state.date) +
            (state.staffId ? '&staff_id=' + encodeURIComponent(state.staffId) : '') +
            (editing ? '&editing=1' : '');

        UI.get(url).then(function (res) {
            if (reqId !== state.slotsRequest) return; // a newer request superseded this one
            if (!res.ok) {
                slotArea.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>' +
                    esc(res.message || 'Could not load times.') + ' <button type="button" class="link-btn" data-retry>Try again</button></div></div>';
                return;
            }
            renderSlots(Array.isArray(res.data) ? res.data : []);
        });
    }

    slotArea.addEventListener('click', function (e) {
        if (e.target.closest('[data-retry]')) { loadSlots(); return; }
        if (e.target.closest('[data-next-day]')) {
            var pills = $$('.date-pill:not(:disabled)');
            var idx = pills.findIndex(function (p) { return p.dataset.date === state.date; });
            if (pills[idx + 1]) selectDate(pills[idx + 1].dataset.date);
            return;
        }
        var btn = e.target.closest('.slot');
        if (!btn) return;
        state.slot = state.slots[+btn.dataset.idx];
        $$('.slot', slotArea).forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); });
        refresh();
    });

    function renderSlots(slots) {
        state.slots = slots;
        if (!slots.length) {
            var who = state.staffId ? esc(cfg.staff[state.staffId].name) + ' has' : 'We have';
            slotArea.innerHTML = '<div class="panel-muted"><div class="fw-600">' + who + ' no free times on ' + esc(prettyDate(state.date)) + '.</div>' +
                '<div class="text-sm text-muted mt-1">Try another day' + (state.staffId ? ' or choose “Any stylist”.' : '.') + '</div>' +
                '<button type="button" class="btn btn-sm mt-3" data-next-day>Next available day <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button></div>';
            return;
        }

        var groups = [
            { title: 'Morning', icon: 'fa-sun', items: [] },
            { title: 'Afternoon', icon: 'fa-cloud-sun', items: [] },
            { title: 'Evening', icon: 'fa-moon', items: [] }
        ];
        slots.forEach(function (s, i) {
            var h = parseInt(s.start, 10);
            groups[h < 12 ? 0 : (h < 17 ? 1 : 2)].items.push({ s: s, i: i });
        });

        var html = '';
        groups.forEach(function (g) {
            if (!g.items.length) return;
            html += '<div class="slot-group"><div class="slot-group-title"><i class="fa-solid ' + g.icon + ' icon-muted" aria-hidden="true"></i>' + g.title + '</div><div class="slot-grid">';
            g.items.forEach(function (it) {
                var isCurrent = editing && state.date === cfg.current.date && it.s.start === cfg.current.start;
                html += '<button type="button" class="slot' + (isCurrent ? ' is-current' : '') + '" aria-pressed="false" data-idx="' + it.i + '" aria-label="' +
                    esc(UI.formatTime(it.s.start) + ' to ' + UI.formatTime(it.s.end) + (isCurrent ? ', your current time' : '')) + '"' +
                    (isCurrent ? ' title="Your current time"' : '') + '>' + esc(UI.formatTime(it.s.start)) + '</button>';
            });
            html += '</div></div>';
        });
        slotArea.innerHTML = html;
    }

    // ── Step 3: details & submit ───────────────────────────────────────
    var form = $('#detailsForm');

    function fieldError(name, message) {
        var input = form.elements[name];
        var el = form.querySelector('[data-error-for="' + name + '"]');
        if (input) {
            input.setAttribute('aria-invalid', message ? 'true' : 'false');
            if (el) { el.id = 'err-' + name; input.setAttribute('aria-describedby', el.id); }
        }
        if (el) { el.textContent = message || ''; el.hidden = !message; }
    }

    function validate() {
        var ok = true;
        var name = form.elements.name.value.trim();
        var phone = form.elements.phone.value.trim();
        var email = form.elements.email.value.trim();

        fieldError('name', name.length < 2 ? 'Please enter your name.' : '');
        if (name.length < 2) ok = false;

        var phoneOk = /^\+?[0-9\s\-()]{7,20}$/.test(phone) && phone.replace(/\D/g, '').length >= 7;
        fieldError('phone', phoneOk ? '' : 'Please enter a valid mobile number.');
        if (!phoneOk) ok = false;

        var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        fieldError('email', emailOk ? '' : (email ? 'Please enter a valid email address.' : 'Please enter your email so we can send your confirmation.'));
        if (!emailOk) ok = false;

        if (!ok) { var bad = form.querySelector('[aria-invalid="true"]'); if (bad) bad.focus(); }
        return ok;
    }

    ['name', 'phone', 'email'].forEach(function (n) {
        var input = form.elements.namedItem(n);
        if (input) input.addEventListener('input', function () { if (this.getAttribute('aria-invalid') === 'true') fieldError(n, ''); });
    });

    // Change mode: show the new details next to the current ones.
    function fillReview() {
        var svc = service();
        var set = function (k, v) { var el = $('[data-review="' + k + '"]'); if (el) el.textContent = v; };
        set('service', svc ? svc.name : '—');
        set('when', state.slot ? prettyDate(state.date) + ', ' + UI.formatTime(state.slot.start) : '—');
        set('stylist', stylistName());
        var p = priced();
        set('price', p ? p.price + (p.offerName ? ' (' + p.offerName + ')' : '') : '—');
    }

    function isUnchanged() {
        return editing && state.slot
            && String(state.serviceId) === String(cfg.current.serviceId)
            && state.date === cfg.current.date
            && state.slot.start === cfg.current.start
            && (!state.staffId || String(state.staffId) === String(cfg.current.staffId));
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var errBox = $('#bookingError');
        errBox.hidden = true;
        if (!editing && !validate()) return;
        if (!state.slot) { goTo(2); return; }
        if (isUnchanged()) {
            errBox.innerHTML = '<i class="fa-solid fa-circle-info" aria-hidden="true"></i><div>That’s the same as your current booking. Choose a different service, stylist or time — or keep your booking.</div>';
            errBox.hidden = false;
            return;
        }

        var buttons = [form.querySelector('[data-confirm-booking]'), mobileNext];
        buttons.forEach(function (b) { UI.setLoading(b, true, editing ? 'Saving…' : 'Booking…'); });

        var payload = {
            service_id: state.serviceId,
            date: state.date,
            start_time: state.slot.start,
            staff_id: state.staffId || null
        };
        if (!editing) {
            payload.name = form.elements.name.value.trim();
            payload.phone = form.elements.phone.value.trim();
            payload.email = form.elements.email.value.trim() || null;
            payload.notes = form.elements.notes.value.trim() || null;
        }

        UI.post(cfg.routes.store, payload).then(function (res) {
            buttons.forEach(function (b) { UI.setLoading(b, false); });
            if (res.ok && res.data.success) { showConfirmation(res.data); return; }

            if (res.status === 422 && res.data.errors) {
                Object.keys(res.data.errors).forEach(function (k) { fieldError(k, res.data.errors[k][0]); });
            }
            errBox.innerHTML = '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>' + esc(res.message || 'Booking failed. Please try again.') + '</div>';
            errBox.hidden = false;

            // The slot was taken in the meantime: refresh availability.
            if (res.status === 422 && !res.data.errors) { resetSlot(); loadSlots(); }
        });
    });

    // ── Confirmation ───────────────────────────────────────────────────
    var booking = null;

    function showConfirmation(data) {
        var svc = service();
        booking = {
            ref: data.appointment_no,
            service: svc.name,
            price: (data.price || svc.price) + (data.offer ? ' (' + data.offer + ')' : ''),
            date: data.date,
            start: data.start,
            end: data.end,
            stylist: data.stylist || 'Assigned on arrival',
            status: data.status === 'confirmed' ? 'Confirmed' : 'Awaiting confirmation'
        };

        $('.booking-layout').hidden = true;
        $('#mobileBar').hidden = true;
        var conf = $('#confirmation');
        conf.hidden = false;

        var set = function (k, v) { $('[data-conf="' + k + '"]', conf).textContent = v; };
        set('ref', booking.ref);
        set('status', booking.status);
        set('service', booking.service);
        set('date', prettyDate(booking.date, true));
        set('time', UI.formatTime(booking.start) + ' – ' + UI.formatTime(booking.end));
        set('stylist', booking.stylist);
        set('price', booking.price);
        var adv = $('#advanceBox');
        if (adv) {
            adv.hidden = !(data.advance && data.advance.amount > 0);
            if (!adv.hidden) {
                set('advance', data.advance.label);
                set('ref2', booking.ref);
            }
        }

        if (data.advance && data.advance.amount > 0 && !editing) {
            $('#confirmLead').textContent = 'Almost done — this service needs an advance. Pay it using the details below to secure your slot.';
        } else if (data.status !== 'confirmed') {
            $('#confirmLead').textContent = editing
                ? 'Your change is saved and waiting for the salon to re-confirm. Your reference is the same.'
                : 'We’ve received your request. You’ll get an email as soon as the salon confirms it. Keep this reference handy.';
        } else if (editing) {
            $('#confirmLead').textContent = 'Your booking has been updated. Your reference and QR code are the same.';
        }

        var box = $('#qrBox');
        box.innerHTML = '';
        if (window.QRCode) {
            new QRCode(box, { text: booking.ref, width: 180, height: 180, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.H });
        } else {
            box.innerHTML = '<div class="ticket-ref" style="padding:40px 12px;">' + esc(booking.ref) + '</div>';
        }

        window.scrollTo({ top: 0 });
        $('#confirmTitle').focus({ preventScroll: true });
    }

    $('#addCalendar').addEventListener('click', function () {
        if (!booking) return;
        var dt = function (date, time) { return date.replace(/-/g, '') + 'T' + time.replace(':', '') + '00'; };
        var stamp = new Date().toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
        var icsEsc = function (s) { return String(s).replace(/([,;\\])/g, '\\$1').replace(/\n/g, '\\n'); };
        var ics = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Salon//Booking//EN', 'BEGIN:VEVENT',
            'UID:' + booking.ref + '@' + location.hostname,
            'DTSTAMP:' + stamp,
            'DTSTART:' + dt(booking.date, booking.start),
            'DTEND:' + dt(booking.date, booking.end),
            'SUMMARY:' + icsEsc(booking.service + ' — ' + cfg.salon),
            'DESCRIPTION:' + icsEsc('Booking reference: ' + booking.ref + '\nStylist: ' + booking.stylist),
            cfg.address ? 'LOCATION:' + icsEsc(cfg.address) : '',
            'END:VEVENT', 'END:VCALENDAR'
        ].filter(Boolean).join('\r\n');
        download(new Blob([ics], { type: 'text/calendar' }), 'appointment-' + booking.ref + '.ics');
    });

    $('#downloadTicket').addEventListener('click', function () {
        if (!booking) return;
        var qr = $('#qrBox canvas');
        var W = 640, PAD = 40, S = 2, rowH = 34;
        var rows = [
            ['Reference', booking.ref], ['Service', booking.service], ['Date', prettyDate(booking.date, true)],
            ['Time', UI.formatTime(booking.start) + ' – ' + UI.formatTime(booking.end)],
            ['Stylist', booking.stylist], ['Price', booking.price], ['Status', booking.status]
        ];
        var H = PAD + 70 + 240 + 24 + rows.length * rowH + PAD;
        var c = document.createElement('canvas');
        c.width = W * S; c.height = H * S;
        var x = c.getContext('2d');
        x.scale(S, S);
        x.fillStyle = '#ffffff'; x.fillRect(0, 0, W, H);
        x.fillStyle = '#8b2f63'; x.fillRect(0, 0, W, 6); // brand accent strip
        x.fillStyle = '#17171c'; x.textAlign = 'center'; x.textBaseline = 'top';
        x.font = '600 26px Georgia, serif'; x.fillText(cfg.salon, W / 2, PAD);
        x.fillStyle = '#686873'; x.font = '14px Arial, sans-serif'; x.fillText('Appointment confirmation', W / 2, PAD + 38);
        var qy = PAD + 70;
        if (qr) { x.drawImage(qr, (W - 220) / 2, qy, 220, 220); }
        var y = qy + 240 + 24;
        x.textBaseline = 'middle';
        rows.forEach(function (r, i) {
            x.strokeStyle = '#e3e3e8'; x.lineWidth = 1;
            if (i) { x.beginPath(); x.moveTo(PAD, y); x.lineTo(W - PAD, y); x.stroke(); }
            x.textAlign = 'left'; x.fillStyle = '#686873'; x.font = '14px Arial, sans-serif'; x.fillText(r[0], PAD, y + rowH / 2);
            x.textAlign = 'right'; x.fillStyle = '#17171c'; x.font = (i === 0 ? 'bold 16px monospace' : 'bold 14px Arial, sans-serif');
            x.fillText(r[1], W - PAD, y + rowH / 2);
            y += rowH;
        });
        c.toBlob(function (blob) { download(blob, 'appointment-' + booking.ref + '.png'); }, 'image/png');
    });

    function download(blob, name) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = name;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    // ── Summary & action state ─────────────────────────────────────────
    function refresh() {
        var svc = service();
        var body = $('[data-summary-body]');
        var emptyEl = $('[data-summary-empty]');
        if (body) {
            body.hidden = !svc;
            emptyEl.hidden = !!svc;
            if (svc) {
                $$('[data-sum="service"]').forEach(function (el) { el.textContent = svc.name; });
                $$('[data-sum="duration"]').forEach(function (el) { el.textContent = svc.duration; });
                var p = priced();
                $$('[data-sum="price"]').forEach(function (el) { el.textContent = p.price; });
                var was = $('[data-sum="was"]');
                was.textContent = p.was || '';
                was.hidden = !p.was;
                var tag = $('[data-sum="offer"]');
                tag.innerHTML = p.offerName ? '<i class="fa-solid fa-tag" aria-hidden="true"></i>' + esc(p.offerName) + (p.offer ? ' · ' + esc(p.offer.label) : '') : '';
                tag.hidden = !p.offerName;
                $('[data-sum="stylist"]').textContent = stylistName();
                $('[data-sum="date"]').textContent = state.date ? prettyDate(state.date, true) : 'Date not chosen';
                $('[data-sum="time"]').textContent = state.slot ? UI.formatTime(state.slot.start) + ' – ' + UI.formatTime(state.slot.end) : 'Time not chosen';
            }
        }

        $$('[data-next="2"]').forEach(function (b) { b.disabled = !canAdvance(1); });
        $$('[data-next="3"]').forEach(function (b) { b.disabled = !canAdvance(2); });

        if (summaryNext) {
            var ready = state.step < 3 && canAdvance(state.step);
            $('#summaryAction').hidden = state.step >= 3;
            summaryNext.disabled = !ready;
            // Say what's missing while the button is disabled.
            $('#summaryHint').textContent = ready ? '' : (state.step === 1 ? 'Choose a service to continue' : (state.date ? 'Pick a time to continue' : 'Pick a date and time to continue'));
            $('#summaryHint').hidden = ready;
        }

        renderOffers();

        // Mobile bar
        var info = $('[data-mobile-info]');
        var title, sub, label;
        if (state.step === 1) {
            title = svc ? svc.name : 'Choose a service';
            sub = svc ? priced().price + ' · ' + svc.duration : 'Step 1 of 3';
            label = 'Continue';
        } else if (state.step === 2) {
            title = state.slot ? prettyDate(state.date) + ', ' + UI.formatTime(state.slot.start) : 'Choose a time';
            sub = svc ? svc.name + ' · ' + priced().price : 'Step 2 of 3';
            label = 'Continue';
        } else {
            title = svc ? priced().price : '';
            sub = state.slot ? prettyDate(state.date) + ', ' + UI.formatTime(state.slot.start) : '';
            label = editing ? 'Confirm change' : 'Confirm booking';
        }
        info.innerHTML = '<strong>' + esc(title) + '</strong><span>' + esc(sub) + '</span>';
        if (!mobileNext.classList.contains('is-loading')) {
            mobileNext.textContent = label;
            mobileNext.disabled = state.step < 3 && !canAdvance(state.step);
        }
    }

    // ── Init ───────────────────────────────────────────────────────────
    if (cfg.preselect && cfg.services[cfg.preselect]) {
        selectService(cfg.preselect);
        var pre = $('[data-service="' + cfg.preselect + '"]');
        // The chosen service must stay visible even if a category was pre-filtered.
        if (pre && pre.hidden) setCategory('all', false);
        if (pre && !editing) pre.scrollIntoView({ block: 'center' });
    }
    // Change mode: start from the current stylist.
    if (editing && cfg.current.staffId) {
        var staffBtn = $('[data-staff="' + cfg.current.staffId + '"]');
        if (staffBtn) {
            state.staffId = String(cfg.current.staffId);
            $$('[data-staff]').forEach(function (b) { b.setAttribute('aria-checked', String(b === staffBtn)); });
        }
    }
    refresh();
})();
