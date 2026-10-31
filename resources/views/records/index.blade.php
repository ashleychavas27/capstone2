@extends('layouts.app')

@section('title', 'My Records')

@section('content')
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-journal-medical"></i></div>
        <div>
            <h4 class="fw-bold mb-0">My Records</h4>
            <p class="text-muted small mb-0">Your appointment history, diagnostic records and e-prescriptions.</p>
        </div>
    </div>

    <div class="row g-4">
        @if($standalonePrescriptions->isNotEmpty())
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-file-earmark-medical me-2 text-primary"></i>My e-Prescriptions
                    </div>
                    <div class="card-body pb-1">
                        @foreach($standalonePrescriptions as $rx)
                            @include('records._prescription', ['rx' => $rx])
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @forelse($appointments as $appt)
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>
                            <i class="bi bi-calendar2-check me-2 text-primary"></i>
                            {{ $appt->appointment_date->format('l, M d, Y') }} at {{ $appt->formatted_slot }}
                        </span>
                        <span class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="text-muted small">{{ $appt->service_type }} · {{ $appt->dentist->name ?? '—' }}</span>
                            @include('partials.status-badge', ['status' => $appt->status])
                        </span>
                    </div>
                    <div class="card-body">
                        @if($appt->treatmentRecord)
                            <div class="border rounded p-3 mb-3 bg-light">
                                <h6 class="fw-bold text-primary mb-2"><i class="bi bi-clipboard2-pulse me-1"></i>Diagnostic / Treatment Record</h6>
                                <p class="mb-1 small">{{ $appt->treatmentRecord->treatment_details }}</p>
                                @if($appt->treatmentRecord->clinical_notes)
                                    <p class="mb-0 text-muted small"><em>Notes: {{ $appt->treatmentRecord->clinical_notes }}</em></p>
                                @endif
                            </div>
                        @else
                            <p class="text-muted small mb-3"><i class="bi bi-info-circle me-1"></i>No diagnostic record yet for this visit.</p>
                        @endif

                        @foreach($appt->prescriptions as $rx)
                            @include('records._prescription', ['rx' => $rx])
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="bi bi-journal-x d-block fs-3 mb-2"></i>
                        You have no appointment records yet. Book an appointment to get started.
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection