@extends('layouts.app')

@section('title', 'SMS Notifications')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">SMS Notifications</h4>
            <p class="text-muted small mb-0">Automated reminders &amp; message history (sms_logs)</p>
        </div>
        <button type="button" class="btn btn-primary no-print" data-bs-toggle="modal" data-bs-target="#sendSmsModal">
            <i class="bi bi-send me-1"></i>Send SMS
        </button>
    </div>

    @if($smsMock)
        <div class="alert alert-warning small no-print">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Mock mode is ON</strong> — messages are only recorded in this log, nothing is sent to a phone.
            Set <code>SMS_MOCK=false</code> plus the gateway credentials in the server environment to send real SMS.
        </div>
    @else
        <div class="alert alert-success small no-print">
            <i class="bi bi-broadcast me-1"></i>
            <strong>Live gateway</strong> — sending through <code>{{ parse_url($smsEndpoint, PHP_URL_HOST) ?: 'the configured endpoint' }}</code>
            as sender <code>{{ $smsSender ?: 'default' }}</code>.
        </div>
    @endif

    <div class="alert alert-info small d-flex align-items-center gap-2 no-print">
        <i class="bi bi-lightbulb"></i>
        <span>Reminders are sent automatically whenever an appointment is marked <strong>Confirmed</strong>. This page lets you review the history or send a manual message.</span>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="smsTable" class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Recipient</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Sent At</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Manual send modal --}}
    <div class="modal fade" id="sendSmsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-send me-2 text-primary"></i>Send SMS</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="smsPhone" class="form-label">Recipient phone <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="smsPhone" name="recipient_phone"
                               placeholder="09XX XXX XXXX" list="patientPhones" required>
                        <datalist id="patientPhones">
                            @foreach($patients as $patient)
                                <option value="{{ $patient->contact_no }}">{{ $patient->name }}</option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="mb-2">
                        <label for="smsMessage" class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="smsMessage" name="message" rows="4" maxlength="160"
                                  placeholder="Your message (max 160 characters)" required></textarea>
                        <div class="form-text text-end" id="charCount">0 / 160</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="smsSendBtn">
                        <i class="bi bi-send me-1"></i>Send
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        const smsTable = $('#smsTable').DataTable({
            ...dtDefaults,
            order: [[0, 'desc']],
            ajax: {
                url: '{{ route('sms.data') }}',
                dataSrc: 'data',
                error: () => showToast('Failed to load SMS logs.', 'danger')
            },
            columns: [
                { data: 'id' },
                { data: 'recipient_phone' },
                { data: 'message', render: (d) => `<span class="d-inline-block text-truncate" style="max-width:380px" title="${d}">${d}</span>` },
                {
                    data: 'status',
                    render: (d) => {
                        const color = d === 'Failed' ? 'danger' : (d === 'Mocked' ? 'secondary' : 'success');
                        return `<span class="badge rounded-pill text-bg-${color}">${d}</span>`;
                    }
                },
                { data: 'sent_at' }
            ],
            language: { ...dtDefaults.language, emptyTable: 'No SMS messages logged yet.' }
        });

        // Character counter
        const msgEl = document.getElementById('smsMessage');
        msgEl.addEventListener('input', () => {
            document.getElementById('charCount').textContent = `${msgEl.value.length} / 160`;
        });

        // AJAX send — no full page reload, instant feedback
        document.getElementById('smsSendBtn').addEventListener('click', async function () {
            const phone   = document.getElementById('smsPhone').value.trim();
            const message = msgEl.value.trim();

            if (!phone || !message) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing fields',
                    text: 'Please fill in both the phone number and the message.',
                    confirmButtonColor: '#C89B27'
                });
                return;
            }

            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Sending…';

            try {
                const formData = new FormData();
                formData.append('_token', csrfToken);
                formData.append('recipient_phone', phone);
                formData.append('message', message);

                const res = await fetch('{{ route('sms.send') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: formData
                });

                // Controller redirects (302) after send — treat any non-5xx as success
                if (res.status < 500) {
                    bootstrap.Modal.getInstance(document.getElementById('sendSmsModal')).hide();
                    document.getElementById('smsPhone').value = '';
                    msgEl.value = '';
                    document.getElementById('charCount').textContent = '0 / 160';
                    smsTable.ajax.reload(null, false);

                    Swal.fire({
                        icon: 'success',
                        title: 'SMS Sent!',
                        html: `Message dispatched to <strong>${phone}</strong> via Semaphore.<br><small class="text-muted">It should arrive within a few seconds.</small>`,
                        confirmButtonColor: '#C89B27',
                        timer: 5000,
                        timerProgressBar: true
                    });
                } else {
                    const data = await res.json().catch(() => ({}));
                    const errMsg = data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : (data.message || 'SMS delivery failed. Verify the phone number and try again.');
                    Swal.fire({ icon: 'error', title: 'Send Failed', text: errMsg, confirmButtonColor: '#C89B27' });
                }
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Could not reach the server. Please check your internet connection.',
                    confirmButtonColor: '#C89B27'
                });
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send me-1"></i>Send';
            }
        });
    });
</script>
@endpush
