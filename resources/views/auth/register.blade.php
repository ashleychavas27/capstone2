@extends('layouts.app')

@section('title', 'Register')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card mt-4 shadow">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <img src="{{ asset('logo.png') }}" alt="Logo" class="stat-icon bg-primary-subtle mx-auto mb-3 rounded-circle" style="object-fit: cover;">
                    <h4 class="fw-bold mb-1">Create Patient Account</h4>
                    <p class="text-muted small mb-0">Register to book appointments online</p>
                </div>

                <form method="POST" action="{{ route('register.attempt') }}">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                                   value="{{ old('name') }}" required autofocus>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="contact_no" class="form-label">Contact number <span class="text-danger">*</span></label>
                            <input type="tel" inputmode="numeric" maxlength="11"
                                   class="form-control js-digits-only @error('contact_no') is-invalid @enderror" id="contact_no"
                                   name="contact_no" value="{{ old('contact_no') }}" placeholder="09XXXXXXXXX"
                                   pattern="09\d{9}" title="11 digits, starting with 09" required>
                            <div class="form-text">Used for SMS appointment reminders. Format: 09XX XXX XXXX.</div>
                            @error('contact_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                                   value="{{ old('email') }}" placeholder="test@gmail.com" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="age" class="form-label">Age</label>
                            {{-- max 2 digits: maxlength is ignored on type=number, so this is a
                                 numeric text field with a hard character limit. --}}
                            <input type="text" inputmode="numeric" maxlength="2"
                                   class="form-control js-digits-only @error('age') is-invalid @enderror"
                                   id="age" name="age" value="{{ old('age') }}" placeholder="e.g. 24">
                            @error('age')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="barangaySearch" class="form-label">Address (Barangay)</label>
                        <div class="position-relative js-barangay-select">
                            <input type="hidden" id="address" name="address" value="{{ old('address') }}">
                            <button type="button"
                                    class="form-select text-start js-barangay-toggle @error('address') is-invalid @enderror"
                                    id="barangayToggle" aria-haspopup="listbox" aria-expanded="false"
                                    aria-controls="barangayList">
                                <span class="js-barangay-label text-muted">Select your barangay</span>
                            </button>

                            <div class="barangay-panel d-none">
                                <input type="text" class="form-control form-control-sm mb-2" id="barangaySearch"
                                       placeholder="Search barangay…" autocomplete="off"
                                       aria-label="Search barangay">
                                <ul class="list-unstyled mb-0 barangay-list" id="barangayList" role="listbox"
                                    aria-label="Barangays of Dingle">
                                    @foreach (config('clinic.barangays') as $barangay)
                                        <li role="option" class="barangay-option" data-value="{{ $barangay }}"
                                            tabindex="-1">{{ $barangay }}</li>
                                    @endforeach
                                </ul>
                                <div class="small text-muted px-2 py-2 d-none js-barangay-empty">
                                    No barangay matches that search.
                                </div>
                            </div>
                        </div>
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="medical_history" class="form-label">Medical history</label>
                        <textarea class="form-control @error('medical_history') is-invalid @enderror" id="medical_history"
                                  name="medical_history" rows="2"
                                  placeholder="Allergies, existing conditions, medications, etc.">{{ old('medical_history') }}</textarea>
                        @error('medical_history')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password"
                                       name="password" required minlength="8" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary js-toggle-password"
                                        data-target="password" aria-label="Show or hide password" title="Show / hide password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">At least 8 characters.</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="password_confirmation" class="form-label">Confirm password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                   id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                            <div class="form-text text-success d-none" id="matchHint"><i class="bi bi-check2 me-1"></i>Passwords match</div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-check me-1"></i>Create Account
                    </button>
                </form>

                <p class="text-center text-muted small mt-3 mb-0">
                    Already have an account? <a href="{{ route('login') }}" class="text-decoration-none">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Searchable barangay dropdown: a scrollable list under a search box, so
       33 barangays stay usable on a phone without a native-list hair-pull. */
    .barangay-panel {
        position: absolute;
        inset: calc(100% + .25rem) 0 auto 0;
        z-index: 1055;
        padding: .5rem;
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-radius: .5rem;
        background: var(--bs-body-bg, #fff);
        box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .12);
    }

    .barangay-list {
        max-height: 220px;
        overflow-y: auto;
    }

    .barangay-option {
        padding: .375rem .5rem;
        border-radius: .375rem;
        cursor: pointer;
    }

    .barangay-option:hover,
    .barangay-option.is-active {
        background: var(--bs-primary-bg-subtle, #cfe2ff);
        color: var(--bs-primary-text-emphasis, #052c65);
    }

    .barangay-option.is-selected {
        font-weight: 600;
    }
</style>
@endpush

@push('scripts')
<script>
    // Show / hide password toggle.
    document.querySelectorAll('.js-toggle-password').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.querySelector('i').classList.toggle('bi-eye', !show);
            btn.querySelector('i').classList.toggle('bi-eye-slash', show);
        });
    });

    // Live password-match feedback before submit.
    const pwd = document.getElementById('password');
    const confirmPwd = document.getElementById('password_confirmation');
    const matchHint = document.getElementById('matchHint');

    function checkMatch() {
        if (!confirmPwd.value) {
            matchHint.classList.add('d-none');
            return;
        }
        matchHint.classList.toggle('d-none', pwd.value !== confirmPwd.value);
        confirmPwd.setCustomValidity(pwd.value === confirmPwd.value ? '' : 'Passwords do not match.');
    }

    pwd.addEventListener('input', checkMatch);
    confirmPwd.addEventListener('input', checkMatch);

    // Contact number (11 digits) and age (2 digits): keep digits only and
    // enforce the hard character limit, since maxlength does not apply to
    // every input type the same way.
    document.querySelectorAll('.js-digits-only').forEach((input) => {
        const limit = parseInt(input.getAttribute('maxlength'), 10) || 0;
        input.addEventListener('input', () => {
            let value = input.value.replace(/\D+/g, '');
            if (limit) value = value.slice(0, limit);
            if (value !== input.value) input.value = value;
        });
    });

    // Searchable barangay dropdown. The visible button shows the chosen
    // barangay; the hidden input carries the value to the server.
    (function () {
        const wrapper = document.querySelector('.js-barangay-select');
        if (!wrapper) return;

        const toggle = wrapper.querySelector('.js-barangay-toggle');
        const label = wrapper.querySelector('.js-barangay-label');
        const panel = wrapper.querySelector('.barangay-panel');
        const search = wrapper.querySelector('#barangaySearch');
        const hidden = wrapper.querySelector('#address');
        const empty = wrapper.querySelector('.js-barangay-empty');
        const options = Array.from(wrapper.querySelectorAll('.barangay-option'));
        let activeIndex = -1;

        function visibleOptions() {
            return options.filter((option) => !option.classList.contains('d-none'));
        }

        function paintLabel(value) {
            label.textContent = value || 'Select your barangay';
            label.classList.toggle('text-muted', !value);
            options.forEach((option) => {
                option.classList.toggle('is-selected', option.dataset.value === value);
                option.setAttribute('aria-selected', option.dataset.value === value ? 'true' : 'false');
            });
        }

        function setActive(option) {
            options.forEach((item) => item.classList.remove('is-active'));
            activeIndex = option ? visibleOptions().indexOf(option) : -1;
            if (option) {
                option.classList.add('is-active');
                option.scrollIntoView({ block: 'nearest' });
            }
        }

        function open() {
            panel.classList.remove('d-none');
            toggle.setAttribute('aria-expanded', 'true');
            search.value = '';
            applyFilter();
            search.focus();
        }

        function close() {
            panel.classList.add('d-none');
            toggle.setAttribute('aria-expanded', 'false');
            setActive(null);
        }

        function choose(option) {
            if (!option) return;
            hidden.value = option.dataset.value;
            paintLabel(option.dataset.value);
            close();
            toggle.focus();
        }

        function applyFilter() {
            const term = search.value.trim().toLowerCase();
            let shown = 0;
            options.forEach((option) => {
                const match = option.dataset.value.toLowerCase().includes(term);
                option.classList.toggle('d-none', !match);
                if (match) shown++;
            });
            empty.classList.toggle('d-none', shown > 0);
            setActive(null);
        }

        toggle.addEventListener('click', () => {
            panel.classList.contains('d-none') ? open() : close();
        });

        search.addEventListener('input', applyFilter);

        search.addEventListener('keydown', (event) => {
            const shown = visibleOptions();
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (!shown.length) return;
                const step = event.key === 'ArrowDown' ? 1 : -1;
                let next = activeIndex + step;
                if (next < 0) next = shown.length - 1;
                if (next >= shown.length) next = 0;
                setActive(shown[next]);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                choose(shown[activeIndex] || shown[0]);
            } else if (event.key === 'Escape') {
                close();
                toggle.focus();
            }
        });

        options.forEach((option) => {
            option.addEventListener('click', () => choose(option));
            option.addEventListener('mouseenter', () => setActive(option));
        });

        document.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target)) close();
        });

        // Restore the selection after a failed submit (old input).
        paintLabel(hidden.value);
    })();
</script>
@endpush
