@props(['standardSlots', 'calendarDays'])

{{--
    Gedeeld JavaScript voor de afsprakenpagina's van klant en admin: één datumkiezer en één
    weergave van deelnemers. Laden vóór het script van de pagina zelf.
--}}
<script>
    // --- DATUMKIEZER ---
    // Eén implementatie voor "Nieuwe afspraak", "Past geen van de tijden?" (klant) en "Plan een
    // meeting" (admin). Welke dagen kunnen, komt van de server (werkdagen en gesloten dagen):
    // dezelfde bron als de controle bij het opslaan. Elk tijdslot wordt per gekozen medewerker
    // gecontroleerd (platform én Outlook).
    const monthsNl = ["januari", "februari", "maart", "april", "mei", "juni", "juli", "augustus", "september", "oktober", "november", "december"];
    const standardSlots = @json($standardSlots); // uit config/appointments.php (werktijden)
    const calendarDays = @json($calendarDays);   // werkdagen (ISO 1-7) en gesloten dagen (Y-m-d)

    function toDateStr(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    // "2026-10-20" als lokale datum; new Date("2026-10-20") zou UTC zijn en kan een dag verschuiven.
    function parseDateStr(dateStr) {
        const [year, month, day] = dateStr.split('-').map(Number);
        return new Date(year, month - 1, day);
    }

    function formatDayNl(date) {
        return date.toLocaleDateString('nl-NL', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }

    function isBookableDay(date) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const isoDay = date.getDay() === 0 ? 7 : date.getDay();

        return date >= today
            && calendarDays.working_days.includes(isoDay)
            && !calendarDays.closed.includes(toDateStr(date));
    }

    function slotPlaceholder(text) {
        const p = document.createElement('p');
        p.className = 'text-xs text-gray-400 italic py-4 text-center my-auto';
        p.textContent = text;
        return p;
    }

    /**
     * @param ids          element-id's: days, monthTitle, prev, next, slots, dateLabel
     * @param checkUrl     beschikbaarheidscheck (route ...appointments.check)
     * @param employeeIds  functie die de te controleren medewerker-id's geeft
     * @param isTaken      (dateStr, slot) => true als dit moment op het formulier al gekozen is
     * @param onDate       na het kiezen van een dag: (dateStr, humanDate)
     * @param onSlot       na het kiezen van een vrij tijdslot: (dateStr, slot, humanDate)
     */
    function createDatePicker({ ids, checkUrl, employeeIds, isTaken = () => false, onDate = () => {}, onSlot }) {
        const el = id => document.getElementById(id);
        const firstOfMonth = date => new Date(date.getFullYear(), date.getMonth(), 1);
        let navDate = firstOfMonth(new Date());
        let selectedDate = '';
        let selectedSlot = '';

        function render() {
            const year = navDate.getFullYear();
            const month = navDate.getMonth();
            el(ids.monthTitle).innerText = `${monthsNl[month]} ${year}`;

            const days = el(ids.days);
            days.replaceChildren();

            const firstDayIndex = (new Date(year, month, 1).getDay() + 6) % 7;
            for (let i = 0; i < firstDayIndex; i++) {
                days.appendChild(document.createElement('div'));
            }

            const lastDay = new Date(year, month + 1, 0).getDate();
            for (let day = 1; day <= lastDay; day++) {
                const date = new Date(year, month, day);
                const dateStr = toDateStr(date);

                const dayBtn = document.createElement('button');
                dayBtn.type = 'button';
                dayBtn.className = 'calendar-day-btn py-1.5 w-full text-center hover:bg-gray-100 rounded-full transition relative flex items-center justify-center font-bold text-gray-700';
                dayBtn.textContent = day;

                if (!isBookableDay(date)) {
                    dayBtn.disabled = true;
                } else {
                    const dot = document.createElement('span');
                    dot.className = 'absolute bottom-0.5 w-1 h-1 bg-[#011936] rounded-full';
                    dayBtn.appendChild(dot);

                    if (dateStr === selectedDate) {
                        dayBtn.classList.add('active');
                    }

                    dayBtn.onclick = () => {
                        days.querySelectorAll('.calendar-day-btn').forEach(b => b.classList.remove('active'));
                        dayBtn.classList.add('active');
                        selectDate(dateStr);
                    };
                }
                days.appendChild(dayBtn);
            }
        }

        function selectDate(dateStr) {
            selectedDate = dateStr;
            selectedSlot = '';
            const humanDate = formatDayNl(parseDateStr(dateStr));
            el(ids.dateLabel).innerText = humanDate;
            onDate(dateStr, humanDate);
            showSlots(dateStr);
        }

        function markSlot(slotBtn, statusSpan, buttonClass, statusClass, statusText) {
            slotBtn.disabled = true;
            slotBtn.className = buttonClass;
            statusSpan.className = statusClass;
            statusSpan.textContent = statusText;
        }

        function showSlots(dateStr) {
            const humanDate = formatDayNl(parseDateStr(dateStr));
            const employees = employeeIds();
            const container = el(ids.slots);
            container.classList.remove('justify-center');
            container.replaceChildren();

            if (employees.length === 0) {
                container.appendChild(slotPlaceholder('Kies eerst een GKR-medewerker.'));
                return;
            }

            standardSlots.forEach(slot => {
                const slotBtn = document.createElement('button');
                slotBtn.type = 'button';
                slotBtn.textContent = slot;
                slotBtn.className = 'time-slot-btn w-full text-left p-3 border border-gray-200 rounded-xl text-xs font-bold text-[#011936] hover:bg-gray-50 transition bg-white flex items-center justify-between shadow-sm';

                const statusSpan = document.createElement('span');
                statusSpan.className = 'text-[10px] uppercase font-bold text-gray-400 tracking-wider';
                statusSpan.textContent = 'Checken...';
                slotBtn.appendChild(statusSpan);
                container.appendChild(slotBtn);

                let conflictFound = false;
                let checksCompleted = 0;

                employees.forEach(empId => {
                    fetch(checkUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify({ employee_id: empId, date: dateStr, time_slot: slot }),
                    })
                    .then(res => res.json())
                    .then(data => {
                        checksCompleted++;
                        if (data.status === 'conflict') {
                            conflictFound = true;
                        }
                        if (checksCompleted !== employees.length) {
                            return;
                        }

                        if (conflictFound) {
                            markSlot(slotBtn, statusSpan,
                                'w-full text-left p-3 border border-gray-100 bg-gray-50 text-gray-300 rounded-xl text-xs font-semibold flex items-center justify-between cursor-not-allowed opacity-60',
                                'text-[10px] text-red-500 font-bold tracking-wider', 'BEZET');
                            return;
                        }

                        if (isTaken(dateStr, slot)) {
                            markSlot(slotBtn, statusSpan,
                                'w-full text-left p-3 border border-red-200 bg-red-50/50 text-red-400 rounded-xl text-xs font-semibold flex items-center justify-between cursor-not-allowed transition duration-150',
                                'text-[10px] text-red-600 font-bold tracking-wider bg-red-100 px-2 py-0.5 rounded border border-red-200', 'AL GEKOZEN');
                            return;
                        }

                        statusSpan.className = 'text-[10px] text-emerald-600 font-bold tracking-wider';
                        statusSpan.textContent = 'VRIJ';
                        if (slot === selectedSlot) {
                            slotBtn.classList.add('active');
                        }

                        slotBtn.onclick = () => {
                            container.querySelectorAll('.time-slot-btn').forEach(b => b.classList.remove('active'));
                            slotBtn.classList.add('active');
                            selectedSlot = slot;
                            onSlot(dateStr, slot, humanDate);
                        };
                    })
                    .catch(err => {
                        console.error('Fout tijdens check:', err);
                        checksCompleted++;
                    });
                });
            });
        }

        function reset() {
            selectedDate = '';
            selectedSlot = '';
            const container = el(ids.slots);
            container.classList.add('justify-center');
            container.replaceChildren(slotPlaceholder('Kies links een beschikbare dag.'));
            el(ids.dateLabel).innerText = 'Selecteer een datum';
            render();
        }

        // Open de kiezer op een eerder gekozen moment, of leeg.
        function show(dateStr = '', slot = '') {
            if (!dateStr) {
                navDate = firstOfMonth(new Date());
                reset();
                return;
            }

            navDate = firstOfMonth(parseDateStr(dateStr));
            selectedDate = dateStr;
            selectedSlot = slot;
            el(ids.dateLabel).innerText = formatDayNl(parseDateStr(dateStr));
            render();
            showSlots(dateStr);
        }

        function init() {
            render();
            el(ids.prev).onclick = () => { navDate.setMonth(navDate.getMonth() - 1); render(); };
            el(ids.next).onclick = () => { navDate.setMonth(navDate.getMonth() + 1); render(); };
        }

        return { init, reset, show, showSlots, selectedDate: () => selectedDate, selectedSlot: () => selectedSlot };
    }

    // --- DEELNEMERS IN EEN DETAILPOPUP ---
    // textContent, niet innerHTML: een naam wordt nooit als HTML uitgevoerd.
    function renderAttendeePills(container, attendees, emptyText) {
        container.replaceChildren();

        if (attendees.length === 0) {
            const empty = document.createElement('span');
            empty.className = 'text-xs text-gray-400 italic';
            empty.textContent = emptyText;
            container.appendChild(empty);
            return;
        }

        attendees.forEach(att => {
            const pill = document.createElement('span');
            pill.className = 'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200';
            const dot = document.createElement('span');
            dot.className = 'w-1.5 h-1.5 bg-[#011936] rounded-full mr-1.5';
            pill.append(dot, document.createTextNode(att.name));
            container.appendChild(pill);
        });
    }
</script>
