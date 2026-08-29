<!DOCTYPE html>
<html>
<head>
    <title>Add Driver</title>
</head>
<body>

<h1>Add Driver</h1>

@if ($errors->any())
    <div>
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<form
    method="POST"
    action="{{ route('admin.drivers.store') }}"
>

    @csrf

    <div>
        <label>Name</label>

        <input
            type="text"
            name="name"
            value="{{ old('name') }}"
            required
        >
    </div>

    <div>
        <label>Email</label>

        <input
            type="email"
            name="email"
            value="{{ old('email') }}"
            required
        >
    </div>

    <div>
        <label>Phone</label>

        <input
            type="text"
            name="phone"
            value="{{ old('phone') }}"
        >
    </div>

    <div>
        <label>License Number</label>

        <input
            type="text"
            name="license_number"
            value="{{ old('license_number') }}"
        >
    </div>

    <div>
        <label>Vehicle Plate</label>

        <input
            type="text"
            name="vehicle_plate"
            value="{{ old('vehicle_plate') }}"
        >
    </div>

    <button type="submit">
        Create Driver
    </button>

</form>

<a href="{{ route('admin.drivers.index') }}">
    Back
</a>

</body>
</html>
