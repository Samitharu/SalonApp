<div class="mb-3">
<label>Name</label>
<input name="name"
class="form-control"
value="{{ $employee->name ?? '' }}"
required>
</div>

<div class="mb-3">
<label>Phone</label>
<input name="phone"
class="form-control"
value="{{ $employee->phone ?? '' }}">
</div>

<div class="mb-3">
<label>Email</label>
<input name="email"
class="form-control"
value="{{ $employee->email ?? '' }}">
</div>

<div class="mb-3">
<label>Bio</label>
<textarea name="bio"
class="form-control">{{ $employee->bio ?? '' }}</textarea>
</div>