@extends('layouts.app')

@section('title', 'Appointment Schedule')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">Appointment Schedule</h4>
            <p class="text-muted small mb-0">Manage bookings — confirming an appointment auto-sends an SMS reminder</p>
        </div>
        <button type="button" class="btn btn-primary no-print" data-bs-toggle="modal" data-bs-target="#createAppointmentModal">
            <i class="bi bi-plus-lg me-1"></i>New Appointment
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-2 mb-3 no-print">
                <div class="col-auto">
                    <select id="filterStatus" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach(\App\Models\Appointment::STATUSES as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <input type="date" id="filterDate" class="form-control form-control-sm">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-outline-secondary" id="clearFilters">
                        <i class="bi bi-x-lg me-1"></i>Clear
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="appointmentsTable" class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Contact</th>
                            <th>Dentist</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Create appointment modal --}}
    <div class="modal fade" id="createAppointmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('appointments.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-calendar-plus me-2 text-primary"></i>Schedule New Appointment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Patient <span class="text-danger">*</span></label>
                                <select name="patient_id" class="form-select" required>
                                    <option value="">Select patient…</option>
                                    @foreach($patients as $patient)
                                        <option value="{{ $patient->id }}">{{ $patient->name }} ({{ $patient->contact_no }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Dentist</label>
                                <select name="dentist_id" class="form-select" id="staffDentistSelect">
                                    <option value="">Unassigned</option>
                                    @foreach($dentists as $dentist)
                                        <option value="{{ $dentist->id }}">{{ $dentist->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Service type <span class="text-danger">*</span></label>
                                <select name="service_type" class="form-select" id="staffService" required>
                                    <option value="">Select procedure…</option>
                                    @foreach(\App\Models\Appointment::onlineProcedures() as $procedure)
                                        <option value="{{ $procedure['name'] }}">
                                            {{ $procedure['name'] }} — {{ $procedure['display'] }}
                                        </option>
                                    @endforeach
                                    @foreach(\App\Models\Appointment::byAppointmentProcedures() as $procedure)
                                        <option value="{{ $procedure['name'] }}">
                                            {{ $procedure['name'] }} — {{ $procedure['display'] }}, {{ \App\Models\Appointment::minutesLabel(\App\Models\Appointment::durationFor($procedure['name'])) }} reserved
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">The procedure's estimated duration decides how much time is blocked, so the time slots below are filtered to what actually fits.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" name="appointment_date" class="form-control" id="staffDate"
                                       min="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Time slot <span class="text-danger">*</span></label>
                                <select name="time_slot" class="form-select" id="staffTimeSlot" required>
                                    <option value="">Select patient, service and date first…</option>
                                </select>
                                <div class="form-text" id="staffSlotHint">
                                    Clinic hours: {{ \App\Models\Appointment::hoursLabel() }} (lunch {{ \App\Models\Appointment::lunchLabel() }}).
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        const table = $('#appointmentsTable').DataTable({
            ...dtDefaults,
            ajax: {
                url: '{{ route('appointments.data') }}',
                data: (d) => ({
                    status: $('#filterStatus').val(),
                    date: $('#filterDate').val()
                }),
                dataSrc: 'data',
                error: () => showToast('Failed to load appointments.', 'danger')
            },
            order: [[0, 'desc'], [1, 'asc']],
            columns: [
                // Lower responsivePriority = kept on screen longer. On phones the
                // responsive plugin drops Contact, Dentist, Service, Status and
                // Actions first, so Date/Time/Patient stay readable.
                { data: 'date', responsivePriority: 1 },
                { data: 'time_slot', responsivePriority: 2 },
                { data: 'patient', responsivePriority: 3 },
                { data: 'patient_contact' },
                { data: 'dentist' },
                { data: 'service_type' },
                {
                    data: 'status',
                    render: (d) => {
                        const map = {
                            Pending: 'badge-status-pending', Confirmed: 'badge-status-confirmed',
                            Completed: 'badge-status-completed', Cancelled: 'badge-status-cancelled'
                        };
                        return `<span class="badge rounded-pill badge-status ${map[d] || 'text-bg-secondary'}">${d}</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-end no-print',
                    orderable: false,
                    render: (d) => `
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-success js-confirm" data-id="${d.id}" ${d.status === 'Confirmed' ? 'disabled' : ''}
                                    title="Confirm & send SMS reminder">
                                <i class="bi bi-check2-circle"></i>
                            </button>
                            <button class="btn btn-outline-primary js-complete" data-id="${d.id}" ${d.status === 'Completed' ? 'disabled' : ''}
                                    title="Mark completed">
                                <i class="bi bi-check2-all"></i>
                            </button>
                            <button class="btn btn-outline-secondary js-cancel" data-id="${d.id}" ${d.status === 'Cancelled' ? 'disabled' : ''}
                                    title="Cancel appointment">
                                <i class="bi bi-x-circle"></i>
                            </button>
                            <button class="btn btn-outline-danger js-delete" data-id="${d.id}" title="Remove">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>`
                }
            ],
            language: {
                ...dtDefaults.language,
                emptyTable: 'No appointments match the current filters.'
            }
        });

        // ---- New appointment: time slots follow the chosen procedure ----
        // A 2-hour surgery cannot start at 3:30 PM, so the slot list is rebuilt
        // from the server's availability (which already knows each procedure's
        // duration, the clinic hours and the lunch break).
        const staffService = document.getElementById('staffService');
        const staffDate = document.getElementById('staffDate');
        const staffDentist = document.getElementById('staffDentistSelect');
        const staffSlot = document.getElementById('staffTimeSlot');
        const staffHint = document.getElementById('staffSlotHint');
        const staffSubmit = document.querySelector('#createAppointmentModal button[type="submit"]');
        let slotTimer = null;
        // Availability requests are async; only the newest one may touch the
        // slot list, otherwise a slow earlier request overwrites the answer for
        // the procedure the staff member has just picked.
        let staffReqId = 0;

        function resetStaffSlots(message) {
            staffSlot.innerHTML = `<option value="">${message}</option>`;
            staffSlot.disabled = true;
            if (staffSubmit) staffSubmit.disabled = true;
        }

        async function loadStaffSlots() {
            if (!staffService.value || !staffDate.value) {
                resetStaffSlots('Select service and date first…');
                return;
            }

            const requestId = ++staffReqId;
            staffSlot.innerHTML = '<option value="">Checking availability…</option>';
            staffSlot.disabled = true;
            if (staffSubmit) staffSubmit.disabled = true;

            const params = new URLSearchParams({ date: staffDate.value, service_type: staffService.value });
            if (staffDentist.value) params.set('dentist_id', staffDentist.value);

            try {
                const res = await apiFetch(`{{ route('appointments.available-slots') }}?${params}`);

                if (requestId !== staffReqId) return;

                if (!res.slots.length) {
                    resetStaffSlots(res.reason || 'No free slot for this procedure on that date.');
                    staffHint.textContent = res.reason || 'Pick another date or dentist.';
                    return;
                }

                staffSlot.innerHTML = res.slots
                    .map(s => `<option value="${s}">${formatStaffTime(s)}</option>`)
                    .join('');
                staffSlot.disabled = false;
                if (staffSubmit) staffSubmit.disabled = false;
                staffHint.textContent = `Reserving ${res.duration_label || res.duration_minutes + ' minutes'} · ${res.slots.length} slot${res.slots.length === 1 ? '' : 's'} free.`;
            } catch (e) {
                if (requestId !== staffReqId) return;
                resetStaffSlots('Could not load slots.');
                staffHint.textContent = e.message;
            }
        }

        function formatStaffTime(slot) {
            const [h, m] = slot.split(':').map(Number);
            const suffix = h >= 12 ? 'PM' : 'AM';
            const hour = h % 12 === 0 ? 12 : h % 12;
            return `${hour}:${String(m).padStart(2, '0')} ${suffix}`;
        }

        [staffService, staffDate, staffDentist].forEach((el) => {
            el?.addEventListener('change', () => {
                clearTimeout(slotTimer);
                slotTimer = setTimeout(loadStaffSlots, 200);
            });
        });

        // Reopening the modal should not keep slots from a previous attempt.
        document.getElementById('createAppointmentModal')?.addEventListener('show.bs.modal', () => {
            resetStaffSlots('Select service and date first…');
            staffHint.textContent = 'Clinic hours: {{ \App\Models\Appointment::hoursLabel() }} (lunch {{ \App\Models\Appointment::lunchLabel() }}).';
        });

        $('#filterStatus, #filterDate').on('change', () => table.ajax.reload());
        $('#clearFilters').on('click', () => {
            $('#filterStatus').val('');
            $('#filterDate').val('');
            table.ajax.reload();
        });

        // ---- Status updates via AJAX (with SMS trigger on Confirm) ----
        const setStatus = async (id, status) => {
            const btn = document.querySelector(`.js-${status === 'Confirmed' ? 'confirm' : status === 'Completed' ? 'complete' : 'cancel'}[data-id="${id}"]`);
            if (btn) btn.disabled = true;
            try {
                const res = await apiFetch(`/appointments/${id}/status`, {
                    method: 'PATCH',
                    body: { status }
                });
                showToast(res.message + (res.sms_triggered ? ` · SMS reminder ${res.sms_status === 'Failed' ? 'FAILED' : 'sent'} 📱` : ''), res.sms_triggered && res.sms_status !== 'Failed' ? 'success' : 'info');
                table.ajax.reload();
            } catch (e) {
                showToast(e.message, 'danger');
                if (btn) btn.disabled = false;
            }
        };

        $('#appointmentsTable').on('click', '.js-confirm', function () {
            setStatus($(this).data('id'), 'Confirmed');
        });
        $('#appointmentsTable').on('click', '.js-complete', function () {
            setStatus($(this).data('id'), 'Completed');
        });
        $('#appointmentsTable').on('click', '.js-cancel', function () {
            confirmAction('Cancel this appointment?', () => setStatus($(this).data('id'), 'Cancelled'));
        });

        // ---- Delete ----
        $('#appointmentsTable').on('click', '.js-delete', function () {
            const id = $(this).data('id');
            confirmAction('Permanently remove this appointment?', async () => {
                try {
                    await apiFetch(`/appointments/${id}`, { method: 'DELETE' });
                    showToast('Appointment removed.');
                    table.ajax.reload();
                } catch (e) {
                    showToast(e.message, 'danger');
                }
            });
        });
    });
</script>
@endpush
