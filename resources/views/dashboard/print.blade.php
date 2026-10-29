<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily Clinic Report · {{ config('app.name', 'Dental Clinic') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; color: #111; }
        .report-title { border-bottom: 3px double #333; }
        .meta-row td { border: none; padding: .1rem .5rem; }
        .table th { background: #f1f3f5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        /* Phones: keep the A4 layout intact for printing, but let the header
           stack and let the wide tables scroll instead of overflowing the page. */
        @media screen and (max-width: 767.98px) {
            .container-fluid { padding-inline: 1rem !important; }
            .report-title { flex-direction: column; gap: .5rem; }
            .report-title .text-end { text-align: left !important; }
            /* The metric strip is one wide row; stack it so no label is cut off. */
            .meta-row, .meta-row tbody, .meta-row tr { display: block; }
            .meta-row td { display: block; }
            .meta-row td.fw-bold { margin-top: .35rem; }
        }
        @media print {
            .no-print { display: none !important; }
            @page { margin: 1.5cm; }
            /* Let every column print; the scroll wrapper is for phones only. */
            .table-responsive { overflow: visible !important; }
        }
    </style>
</head>
<body>
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-start report-title pb-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">{{ config('app.name', 'Dental Clinic') }}</h3>
            <h5 class="mb-0">Daily Appointment Report</h5>
        </div>
        <div class="text-end small">
            <div><strong>Date:</strong> {{ \Carbon\Carbon::parse($date)->format('F d, Y') }}</div>
            <div><strong>Generated:</strong> {{ now()->format('M d, Y h:i A') }}</div>
            <div><strong>Prepared by:</strong> {{ auth()->user()->name }} ({{ auth()->user()->role }})</div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-borderless meta-row mb-4">
            <tr>
                <td class="fw-bold">Registered Patients</td><td>{{ $metrics['totalPatients'] }}</td>
                <td class="fw-bold">Upcoming Appointments</td><td>{{ $metrics['upcomingAppointments'] }}</td>
                <td class="fw-bold">Appointments on {{ \Carbon\Carbon::parse($date)->format('M d') }}</td><td>{{ $schedule->count() }}</td>
            </tr>
        </table>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>#</th>
                <th>Time Slot</th>
                <th>Patient</th>
                <th>Contact No.</th>
                <th>Dentist</th>
                <th>Service Type</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($schedule as $i => $appt)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $appt->formatted_slot }}</td>
                    <td>{{ $appt->patient->name ?? '—' }}</td>
                    <td>{{ $appt->patient->contact_no ?? '—' }}</td>
                    <td>{{ $appt->dentist->name ?? 'Unassigned' }}</td>
                    <td>{{ $appt->service_type }}</td>
                    <td>{{ $appt->status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No appointments scheduled for this date.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>

    <div class="small text-muted mt-4">
        {{ $schedule->count() }} appointment(s) total. This report is system-generated and requires no signature.
    </div>

    <div class="text-center mt-4 no-print">
        <button type="button" class="btn btn-primary" id="btnPrintReport">
            <i class="bi bi-printer me-1"></i>Print / Save as PDF
        </button>
        <button type="button" class="btn btn-outline-secondary" id="btnCloseReport">Close</button>
    </div>
</div>

<script>
    // Printing and closing both fail inside in-app browsers (Messenger,
    // Facebook) and when the tab was not opened by a script, so each button
    // falls back to something that always works on a phone.
    document.getElementById('btnPrintReport').addEventListener('click', function () {
        try {
            window.print();
        } catch (e) {
            window.alert('This browser cannot open the print dialog.\n\nOpen this page in Chrome or Safari, then use the browser menu and choose Print or Share > Print.');
        }
    });

    document.getElementById('btnCloseReport').addEventListener('click', function () {
        // Only a script-opened tab may be closed; otherwise the call is ignored.
        window.close();

        setTimeout(function () {
            if (window.closed) return;
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = @json(route('dashboard'));
            }
        }, 250);
    });
</script>
</body>
</html>
