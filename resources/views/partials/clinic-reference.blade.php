{{-- Clinic reference: the client's procedure sheet, dentist duty days and clinic hours. --}}
<div class="row g-4 mt-1">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-clipboard2-pulse me-2 text-primary"></i>Dental Procedures and Estimated Duration</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th style="width:3rem;">#</th><th>Procedure</th><th>Local name</th><th>Estimated duration</th></tr>
                    </thead>
                    <tbody>
                        {{-- Patients see only the procedures they can book online;
                             "by appointment" ones are removed from their reference sheet. --}}
                        @php
                            $listedProcedures = auth()->user()->isStaff()
                                ? \App\Models\Appointment::procedures()
                                : \App\Models\Appointment::onlineProcedures();
                        @endphp
                        @foreach($listedProcedures as $procedure)
                            <tr>
                                <td class="text-muted">{{ $loop->iteration }}</td>
                                <td class="fw-semibold">{{ $procedure['name'] }}</td>
                                <td class="text-muted small">{{ $procedure['local'] }}</td>
                                <td>
                                    @if($procedure['online'] ?? false)
                                        {{ $procedure['display'] }}
                                    @else
                                        <span class="badge rounded-pill text-bg-light border">{{ $procedure['display'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(auth()->user()->isStaff())
                <div class="card-footer small text-muted">
                    Procedures marked <em>By appointment</em> are scheduled by the clinic — please contact us and we will arrange the session.
                </div>
            @endif
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-person-badge me-2 text-primary"></i>Dentist Schedule</div>
            <ul class="list-group list-group-flush">
                @foreach($dentists as $d)
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <span class="fw-semibold">Dr. {{ $d->name }}</span>
                        <span class="small text-muted text-end">{{ \App\Models\Appointment::dutyDayLabel($d) }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="card-header border-top"><i class="bi bi-clock-history me-2 text-primary"></i>Clinic Schedule</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span>{{ \App\Models\Appointment::openDaysLabel() }}</span><span class="fw-semibold">{{ \App\Models\Appointment::hoursLabel() }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Sunday</span><span class="fw-semibold text-danger">CLOSED</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Holidays</span><span class="fw-semibold text-success">Open</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Lunch break</span><span class="fw-semibold">{{ \App\Models\Appointment::lunchLabel() }}</span></li>
            </ul>
        </div>
    </div>
</div>
