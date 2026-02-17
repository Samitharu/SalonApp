// =====================================
// BOOKING PAGE INITIALIZER
// =====================================
window.initBookingPage = function () {

    const service = document.getElementById('service');
    const date = document.getElementById('date');
    const slotsSelect = document.getElementById('slots');
    const startInput = document.getElementById('start_time');
    const endInput = document.getElementById('end_time');
    const staff = document.getElementById('staff');
    if (!service || !date || !slotsSelect) return;
    // =====================================
    // AUTO SELECT SERVICE FROM URL
    // =====================================
    (function autoSelectServiceFromUrl() {
        const url = new URL(window.location.href);
        const serviceFromUrl = url.searchParams.get('service');

        if (!serviceFromUrl) return;

        // wait a tick in case SPA loads async
        setTimeout(() => {
            if (service.querySelector(`option[value="${serviceFromUrl}"]`)) {
                service.value = serviceFromUrl;

                // trigger change to load slots
                service.dispatchEvent(new Event('change'));
            }
        }, 50);
    })();

    async function loadSlots() {

        if (!service.value || !date.value) return;

        slotsSelect.innerHTML = '<option>Loading...</option>';

        try {
            const staff = document.getElementById('staff');

            const res = await fetch(
                `/booking/slots?service_id=${service.value}&date=${date.value}&staff_id=${staff.value}`
            );

            const slots = await res.json();

            slotsSelect.innerHTML = '';

            if (slots.length === 0) {
                slotsSelect.innerHTML = '<option>No slots available</option>';
                startInput.value = '';
                endInput.value = '';
                return;
            }

            slots.forEach((slot, i) => {
                const opt = document.createElement('option');
                opt.value = JSON.stringify(slot);
                opt.textContent = slot[0] + ' → ' + slot[1];
                slotsSelect.appendChild(opt);

                // ✅ auto select first slot
                if (i === 0) {
                    startInput.value = slot[0];
                    endInput.value = slot[1];
                    slotsSelect.selectedIndex = 0;
                }
            });

        } catch (err) {
            slotsSelect.innerHTML = '<option>Error loading slots</option>';
            startInput.value = '';
            endInput.value = '';
        }
    }

    // update hidden inputs when user changes slot
    slotsSelect.onchange = e => {
        const [start, end] = JSON.parse(e.target.value);
        startInput.value = start;
        endInput.value = end;
    };

    service.onchange = loadSlots;
    date.onchange = loadSlots;
    staff.onchange = loadSlots;
};


// =====================================
// FORM SUBMIT (AJAX)
// =====================================
window.initBookingSubmit = function () {

    const form = document.getElementById('bookingForm');
    const result = document.getElementById('bookingResult');

    if (!form) return;

    form.addEventListener('submit', async e => {
        e.preventDefault();

        const formData = new FormData(form);

        // guard: ensure slot selected
        if (!formData.get('start_time')) {
            result.innerHTML =
                "<div class='alert alert-warning'>Please select a time slot</div>";
            return;
        }

        result.innerHTML =
            "<div class='alert alert-info'>Saving booking...</div>";

        try {
            const res = await fetch('/booking/store', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name=\"csrf-token\"]').content
                },
                body: formData
            });

            const data = await res.json();

            if (data.success) {
                result.innerHTML =
                    "<div class='alert alert-success'>Booking saved ✅</div>";

                form.reset();

                const slots = document.getElementById('slots');
                if (slots) slots.innerHTML = '';

            } else {
                result.innerHTML =
                    "<div class='alert alert-danger'>Booking failed ❌</div>";
            }

        } catch (err) {
            result.innerHTML =
                "<div class='alert alert-danger'>Server error ❌</div>";
        }
    });
};