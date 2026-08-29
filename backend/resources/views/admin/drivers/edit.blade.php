<!DOCTYPE html>
<html>
<head>
    <title>Edit Driver</title>
</head>
<body>

<h1>Edit Driver</h1>

@if ($errors->any())
    <div>
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<form
    method="POST"
    action="{{ route(
        'admin.drivers.update',
        $driver
    ) }}"
>

    @csrf
    @method('PUT')

    <div>
        <label>License Number</label>

        <input
            type="text"
            name="license_number"
            value="{{ old(
                'license_number',
                $driver->license_number
            ) }}"
        >
    </div>

    <div>
        <label>Vehicle Plate</label>

        <input
            type="text"
            name="vehicle_plate"
            value="{{ old(
                'vehicle_plate',
                $driver->vehicle_plate
            ) }}"
        >
    </div>

    <button type="submit">
        Save Changes
    </button>

</form>

<a href="{{ route('admin.drivers.index') }}">
    Back
</a>

</body>
</html>
