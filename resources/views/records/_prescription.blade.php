{{-- One patient-facing e-prescription card. Expects $rx (with items loaded). --}}
<div class="border rounded p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h6 class="fw-bold mb-0 text-primary">
            <i class="bi bi-file-earmark-text me-1"></i>e-Prescription · {{ $rx->date_issued->format('M d, Y') }}
        </h6>
        <a href="{{ route('records.prescription.print', $rx) }}" target="_blank" rel="noopener"
           class="btn btn-sm btn-outline-primary no-print">
            <i class="bi bi-printer me-1"></i>Print / View
        </a>
    </div>
    <p class="small mb-2"><strong>Diagnosis:</strong> {{ $rx->diagnosis }}</p>
    <p class="small text-muted mb-2">
        Issued by Dr. {{ $rx->dentist->name ?? '—' }}
        @if($rx->appointment)
            · {{ $rx->appointment->appointment_date->format('M d, Y') }} {{ $rx->appointment->service_type }}
        @endif
    </p>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 table-stack">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Medication</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Duration</th>
                    <th>Instructions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rx->items as $i => $item)
                    <tr>
                        <td data-label="#">{{ $i + 1 }}</td>
                        <td class="fw-semibold" data-label="Medication">{{ $item->drug_name }}</td>
                        <td data-label="Dosage">{{ $item->dosage ?? '—' }}</td>
                        <td data-label="Frequency">{{ $item->frequency ?? '—' }}</td>
                        <td data-label="Duration">{{ $item->duration ?? '—' }}</td>
                        <td class="small" data-label="Instructions">{{ $item->instructions ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($rx->notes)
        <p class="text-muted small mb-0 mt-2"><strong>Notes:</strong> {{ $rx->notes }}</p>
    @endif
</div>
