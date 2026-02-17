@extends('layouts.app')

@section('content')

<div class="container py-5">

    <div class="row justify-content-center">
        <div class="col-md-6">

            <div class="card p-4">

                <h2 class="text-center mb-4">Book Appointment</h2>

                <form id="bookingForm" class="d-grid gap-3">

                    <div>
                        <label class="form-label">Your Name</label>
                        <input name="name" class="form-control" placeholder="Enter your name" required>
                    </div>

                    <div>
                        <label class="form-label">Phone</label>
                        <input name="phone" class="form-control" placeholder="Enter phone number" required>
                    </div>

                    <div>
                        <label class="form-label">Service</label>
                        <select name="service_id" id="service" class="form-select" required>
                            <option value="">Select service</option>
                            @foreach($services as $service)
                            <option value="{{ $service->id }}">
                                {{ $service->name }} ({{ $service->duration_minutes }} mins)
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Preferred Staff (optional)</label>
                        <select name="staff_id" id="staff" class="form-select">
                            <option value="">Any available staff</option>

                            @foreach($staff as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach

                        </select>
                    </div>
                    <div>
                        <label class="form-label">Date</label>
                        <input type="date" name="date" id="date" class="form-control" required>
                    </div>

                    <div>
                        <label class="form-label">Available Time</label>
                        <select id="slots" class="form-select"></select>
                        <input type="hidden" name="start_time" id="start_time">
                        <input type="hidden" name="end_time" id="end_time">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-3">
                        Confirm Booking
                    </button>

                </form>

                <div id="bookingResult" class="text-center mt-3"></div>

            </div>
        </div>
    </div>

</div>



@endsection