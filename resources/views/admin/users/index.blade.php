@extends('layouts.admin')
@section('title', 'Admin users')

@section('content')
<div class="page-head">
    <div>
        <h1>Admin users</h1>
        <p>Who can sign in to this panel, and what each person can do. Stylists have their own permissions under <a href="{{ route('admin.employees.index') }}">Staff</a>.</p>
    </div>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add user</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table table-stack">
            <thead><tr><th>User</th><th>Access</th><th>Last sign-in</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
                @foreach($users as $u)
                    @php
                        $isMe = $actor->is($u);
                        $locked = $u->is_owner && !$actor->is_owner;
                        $count = count($u->permissions ?? []);
                    @endphp
                    <tr class="{{ $u->is_active ? '' : 'is-muted' }}">
                        <td class="cell-primary">
                            <div class="person">
                                <span class="avatar" aria-hidden="true">{{ initials($u->name) }}</span>
                                <div style="min-width:0;">
                                    <div class="person-name">{{ $u->name }} @if($isMe)<span class="badge badge-neutral no-dot">You</span>@endif</div>
                                    <div class="person-meta truncate">{{ $u->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Access">
                            @unless($u->is_active)
                                <span class="badge badge-cancelled">Deactivated</span>
                            @elseif($u->is_owner)
                                <span class="badge badge-accent no-dot"><i class="fa-solid fa-crown" aria-hidden="true"></i> Owner · full access</span>
                            @else
                                <span class="badge badge-confirmed no-dot">{{ $u->roleLabel() }}</span>
                                @if($count)<div class="text-xs text-muted mt-1">{{ $count }} {{ \Illuminate\Support\Str::plural('permission', $count) }}</div>@endif
                            @endunless
                        </td>
                        <td data-label="Last sign-in" class="text-sm text-muted">{{ $u->last_login_at ? $u->last_login_at->diffForHumans() : 'Never' }}</td>
                        <td class="actions" data-label="">
                            <div class="row" style="flex-wrap:nowrap;">
                                @if($isMe)
                                    <a href="{{ route('admin.account.edit') }}" class="btn btn-ghost btn-sm">My account</a>
                                @elseif(!$locked)
                                    <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm">Edit access</a>
                                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                                          data-confirm="Delete {{ $u->name }}?" data-confirm-message="They won’t be able to sign in. To keep their account for later, deactivate it instead." data-confirm-button="Delete">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Delete {{ $u->name }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
