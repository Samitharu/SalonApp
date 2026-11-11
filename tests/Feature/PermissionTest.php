<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\SystemLog;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Who can do what: owners, limited admin users and stylists in the portal.
 */
class PermissionTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $permissions = [], array $attributes = []): Admin
    {
        return Admin::create($attributes + [
            'name'        => 'User ' . Str::random(4),
            'email'       => Str::lower(Str::random(8)) . '@s.lk',
            'password'    => Hash::make('secret123'),
            'permissions' => $permissions,
        ]);
    }

    /** A fresh session per user, as signing in gives. The session is tied to one user's password. */
    private function signInAs(Admin $user): static
    {
        $this->flushSession();

        return $this->actingAs($user, 'admin');
    }

    private function owner(array $attributes = []): Admin
    {
        return $this->user([], $attributes + ['is_owner' => true]);
    }

    private function appointment(array $over = []): Appointment
    {
        $service = Service::firstOrCreate(['slug' => 'facial'], ['name' => 'Facial', 'price' => 5000, 'duration_minutes' => 60, 'is_active' => true]);
        $customer = Customer::firstOrCreate(['phone' => '0771234567'], ['name' => 'Dilini Perera', 'email' => 'd@x.lk']);

        return Appointment::create($over + [
            'appointment_no' => strtoupper(Str::random(8)), 'customer_id' => $customer->id, 'service_id' => $service->id,
            'appointment_date' => now()->addDay()->toDateString(), 'slot_start' => '10:00:00', 'slot_end' => '11:00:00',
            'type' => 'online', 'status' => 'pending', 'payment_status' => 'unpaid',
        ]);
    }

    private function product(): Product
    {
        return Product::create(['name' => 'Cream', 'unit' => 'ml', 'uses_per_unit' => 1, 'stock' => 100, 'is_active' => true]);
    }

    private function userPayload(array $over = []): array
    {
        return $over + ['name' => 'Nimali', 'email' => 'nimali@s.lk', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'permissions' => []];
    }

    // ── The safety net ──────────────────────────────────────────────────

    public function test_every_admin_route_requires_a_permission_unless_deliberately_open(): void
    {
        $open = ['admin.dashboard', 'admin.logout'];
        $unguarded = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (!str_starts_with($route->uri(), 'admin') || in_array($route->uri(), ['admin/login'], true)) {
                continue;
            }
            if (in_array($name, $open, true) || str_starts_with($name, 'admin.account.')) {
                continue;
            }

            $guarded = collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'perm:'));
            if (!$guarded) {
                $unguarded[] = $route->uri() . ' (' . $name . ')';
            }
        }

        $this->assertSame([], $unguarded, 'These admin routes have no permission check.');
    }

    public function test_route_guards_only_name_permissions_that_exist(): void
    {
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $m) {
                if (is_string($m) && str_starts_with($m, 'perm:')) {
                    foreach (explode(',', substr($m, 5)) as $permission) {
                        $this->assertContains($permission, Permissions::all(), $route->uri());
                    }
                }
            }
        }
    }

    public function test_every_preset_and_implied_permission_is_real(): void
    {
        foreach (Permissions::ROLES as [$label, $permissions]) {
            $this->assertSame(array_values(array_intersect($permissions, Permissions::all())), array_values($permissions), $label);
        }
        foreach (Permissions::IMPLIES as $permission => $implied) {
            $this->assertContains($permission, Permissions::all());
            foreach ($implied as $p) {
                $this->assertContains($p, Permissions::all());
            }
        }
    }

    // ── Enforcement ─────────────────────────────────────────────────────

    public function test_user_without_permissions_is_refused_everywhere_but_the_dashboard_and_their_account(): void
    {
        $this->actingAs($this->user(), 'admin');

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('doesn’t have any permissions yet');
        $this->get(route('admin.account.edit'))->assertOk();

        foreach (['admin.appointments.index', 'admin.schedule', 'admin.customers.index', 'admin.services.index', 'admin.categories.index',
            'admin.products.index', 'admin.employees.index', 'admin.users.index', 'admin.reports', 'admin.logs.index',
            'admin.hours.index', 'admin.sliders.index', 'admin.gallery.index', 'admin.settings.index', 'admin.feedback.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_refusal_explains_what_is_missing_and_offers_a_way_back(): void
    {
        $this->actingAs($this->user(), 'admin');

        $this->get(route('admin.settings.index'))->assertForbidden()
            ->assertSee('Manage settings')
            ->assertSee('Back to dashboard');
    }

    public function test_refusals_are_logged(): void
    {
        $user = $this->user();
        $this->actingAs($user, 'admin')->get(route('admin.settings.index'));

        $this->assertDatabaseHas('system_logs', ['action' => 'auth.permission_denied', 'level' => 'warning']);
    }

    public function test_each_section_opens_with_its_own_permission_only(): void
    {
        $sections = [
            'appointments.view' => 'admin.appointments.index',
            'customers.view'    => 'admin.customers.index',
            'feedback.manage'   => 'admin.feedback.index',
            'services.manage'   => 'admin.services.index',
            'stock.view'        => 'admin.products.index',
            'staff.manage'      => 'admin.employees.index',
            'users.manage'      => 'admin.users.index',
            'reports.view'      => 'admin.reports',
            'logs.view'         => 'admin.logs.index',
            'hours.manage'      => 'admin.hours.index',
            'website.manage'    => 'admin.gallery.index',
            'settings.manage'   => 'admin.settings.index',
        ];

        foreach ($sections as $permission => $route) {
            $this->signInAs($this->user([$permission]));
            $this->get(route($route))->assertOk();

            foreach ($sections as $other => $otherRoute) {
                if ($other !== $permission) {
                    $this->get(route($otherRoute))->assertForbidden();
                }
            }
        }
    }

    public function test_owner_can_open_everything_even_with_no_ticks(): void
    {
        $this->actingAs($this->owner(), 'admin');

        foreach (['admin.appointments.index', 'admin.products.index', 'admin.users.index', 'admin.reports', 'admin.logs.index', 'admin.settings.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_view_only_stock_user_sees_stock_but_cannot_change_it(): void
    {
        $product = $this->product();
        $this->actingAs($this->user(['stock.view']), 'admin');

        $this->get(route('admin.products.index'))->assertOk()->assertDontSee(route('admin.products.create'));
        $this->get(route('admin.products.show', $product))->assertOk()->assertDontSee(route('admin.products.edit', $product));
        $this->get(route('admin.products.restock'))->assertForbidden();
        $this->post(route('admin.products.restock.store'), [])->assertForbidden();
    }

    public function test_managing_implies_seeing(): void
    {
        $this->assertSame(['appointments.view', 'appointments.manage'], Permissions::normalise(['appointments.manage']));

        $user = $this->user(Permissions::normalise(['stock.manage']));
        $this->assertTrue($user->hasPermission('stock.view'));
        $this->actingAs($user, 'admin')->get(route('admin.products.index'))->assertOk();
    }

    public function test_view_only_booking_user_cannot_change_a_booking(): void
    {
        $a = $this->appointment();
        $this->actingAs($this->user(['appointments.view']), 'admin');

        $this->get(route('admin.appointments.show', $a))->assertOk()
            ->assertDontSee('Danger zone')
            ->assertDontSee(route('admin.appointments.status', $a));

        $this->post(route('admin.appointments.status', $a), ['status' => 'confirmed'])->assertForbidden();
        $this->delete(route('admin.appointments.destroy', $a))->assertForbidden();
        $this->post(route('admin.appointments.payments.store', $a), [])->assertForbidden();
        $this->get(route('admin.appointments.export'))->assertForbidden();
        $this->assertSame('pending', $a->fresh()->status);
    }

    // ── Screens ─────────────────────────────────────────────────────────

    public function test_sidebar_only_lists_what_the_user_can_open(): void
    {
        $this->actingAs($this->user(['stock.view']), 'admin');

        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.products.index'))
            ->assertDontSee(route('admin.appointments.index'))
            ->assertDontSee(route('admin.users.index'))
            ->assertDontSee(route('admin.settings.index'))
            ->assertDontSee(route('cashier.index'))
            ->assertDontSee(route('admin.appointments.create'));
    }

    public function test_owner_sidebar_includes_admin_users(): void
    {
        $this->actingAs($this->owner(), 'admin')->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.users.index'));
    }

    public function test_dashboard_hides_revenue_and_customers_from_a_receptionist_without_them(): void
    {
        Customer::create(['name' => 'A', 'phone' => '0770000001']);
        $this->actingAs($this->user(['appointments.view']), 'admin');

        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee("Today's schedule", false)
            ->assertDontSee('Revenue today')
            ->assertDontSee('Top services this month')
            ->assertDontSee('new this month');
    }

    public function test_dashboard_low_stock_needs_stock_permission(): void
    {
        Product::create(['name' => 'Bleach Powder', 'unit' => 'g', 'uses_per_unit' => 1, 'stock' => 0, 'low_stock_level' => 5, 'is_active' => true]);

        $this->signInAs($this->user(['appointments.view']))->get(route('admin.dashboard'))->assertDontSee('Bleach Powder');
        $this->signInAs($this->user(['stock.view']))->get(route('admin.dashboard'))->assertSee('Bleach Powder')->assertDontSee(route('admin.products.restock'));
        $this->signInAs($this->user(['stock.view', 'stock.manage']))->get(route('admin.dashboard'))->assertSee(route('admin.products.restock'));
    }

    // ── Cashier ─────────────────────────────────────────────────────────

    public function test_cashier_station_needs_payments_permission_or_the_pin(): void
    {
        $this->signInAs($this->user(['payments.take']))->get(route('cashier.index'))->assertOk();

        $this->signInAs($this->user(['stock.view']));
        $this->get(route('cashier.index'))->assertRedirect(route('cashier.unlock'));
        // The PIN screen must show, not bounce back and forth.
        $this->get(route('cashier.unlock'))->assertOk();

        $this->withSession([\App\Http\Middleware\CashierAccess::SESSION_KEY => now()->timestamp])
            ->get(route('cashier.index'))->assertOk();
    }

    // ── Signing in ──────────────────────────────────────────────────────

    public function test_keep_me_signed_in_works(): void
    {
        $user = $this->user([], ['email' => 'keep@s.lk']);

        $this->post('/admin/login', ['email' => 'keep@s.lk', 'password' => 'secret123', 'remember' => '1'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user, 'admin');
        $this->assertNotNull($user->fresh()->remember_token);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_deactivated_user_cannot_sign_in(): void
    {
        $this->user([], ['email' => 'gone@s.lk', 'is_active' => false]);

        $this->post('/admin/login', ['email' => 'gone@s.lk', 'password' => 'secret123']);

        $this->assertGuest('admin');
    }

    public function test_deactivating_signs_out_a_user_who_is_already_in(): void
    {
        $user = $this->user(['stock.view']);
        $this->actingAs($user, 'admin')->get(route('admin.products.index'))->assertOk();

        $user->update(['is_active' => false]);

        $this->get(route('admin.products.index'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_new_permissions_apply_straight_away(): void
    {
        $user = $this->user(['stock.view']);
        $this->actingAs($user, 'admin')->get(route('admin.products.index'))->assertOk();

        $user->update(['permissions' => []]);

        $this->get(route('admin.products.index'))->assertForbidden();
    }

    public function test_password_reset_by_owner_ends_the_users_sessions(): void
    {
        $user = $this->user(['stock.view'], ['email' => 'staff@s.lk']);

        // The user's browser signs in for real, so the session holds the fingerprint.
        $this->post('/admin/login', ['email' => 'staff@s.lk', 'password' => 'secret123']);
        $this->get(route('admin.products.index'))->assertOk();

        $user->update(['password' => Hash::make('different123')]);
        $this->app['auth']->forgetGuards();

        $this->get(route('admin.products.index'))->assertRedirect(route('admin.login'));
    }

    public function test_changing_your_own_password_keeps_you_signed_in(): void
    {
        $user = $this->user(['stock.view'], ['email' => 'me@s.lk']);
        $this->post('/admin/login', ['email' => 'me@s.lk', 'password' => 'secret123']);

        $this->put(route('admin.account.update'), [
            'name' => $user->name, 'email' => 'me@s.lk', 'current_password' => 'secret123',
            'password' => 'newsecret123', 'password_confirmation' => 'newsecret123',
        ])->assertSessionHasNoErrors();

        $this->get(route('admin.products.index'))->assertOk();
    }

    // ── Managing users ──────────────────────────────────────────────────

    public function test_owner_adds_a_user_with_chosen_permissions(): void
    {
        $this->actingAs($this->owner(), 'admin');

        $this->post(route('admin.users.store'), $this->userPayload(['permissions' => ['stock.manage', 'reports.view']]))
            ->assertRedirect(route('admin.users.index'));

        $user = Admin::where('email', 'nimali@s.lk')->first();
        $this->assertFalse($user->is_owner);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasPermission('stock.manage'));
        $this->assertTrue($user->hasPermission('stock.view'), 'manage should bring view with it');
        $this->assertFalse($user->hasPermission('settings.manage'));
        $this->assertDatabaseHas('system_logs', ['action' => 'user.created']);
    }

    public function test_users_screens_render_for_an_owner(): void
    {
        $this->actingAs($this->owner(), 'admin');
        $user = $this->user(['stock.view']);

        $this->get(route('admin.users.index'))->assertOk()->assertSee($user->email);
        $this->get(route('admin.users.create'))->assertOk()->assertSee('Receptionist');
        $this->get(route('admin.users.edit', $user))->assertOk()->assertSee($user->email);
    }

    public function test_unknown_permission_is_rejected(): void
    {
        $this->actingAs($this->owner(), 'admin');

        $this->post(route('admin.users.store'), $this->userPayload(['permissions' => ['everything']]))
            ->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseMissing('admins', ['email' => 'nimali@s.lk']);
    }

    public function test_cannot_grant_what_you_do_not_hold(): void
    {
        $this->actingAs($this->user(['users.manage', 'stock.view']), 'admin');

        $this->post(route('admin.users.store'), $this->userPayload(['permissions' => ['stock.view', 'settings.manage', 'reports.view']]));

        $this->assertSame(['stock.view'], Admin::where('email', 'nimali@s.lk')->first()->permissions);
    }

    public function test_editing_keeps_permissions_you_have_no_say_over(): void
    {
        $target = $this->user(['reports.view', 'stock.view']);
        $this->actingAs($this->user(['users.manage', 'stock.view']), 'admin');

        // Unticks everything it can see; reports.view isn't theirs to remove.
        $this->put(route('admin.users.update', $target), ['name' => 'Kept', 'email' => $target->email, 'is_active' => '1', 'permissions' => []])
            ->assertRedirect();

        $this->assertSame(['reports.view'], $target->fresh()->permissions);
    }

    public function test_cannot_change_your_own_access(): void
    {
        $user = $this->user(['users.manage']);
        $this->actingAs($user, 'admin');

        $this->get(route('admin.users.edit', $user))->assertRedirect(route('admin.account.edit'));
        $this->put(route('admin.users.update', $user), ['name' => 'Me', 'email' => $user->email, 'is_active' => '1', 'permissions' => ['settings.manage']])
            ->assertForbidden();
        $this->delete(route('admin.users.destroy', $user));

        $this->assertSame(['users.manage'], $user->fresh()->permissions);
        $this->assertNotNull($user->fresh());
    }

    public function test_non_owner_cannot_touch_or_make_an_owner(): void
    {
        $owner = $this->owner();
        $this->actingAs($this->user(['users.manage']), 'admin');

        $this->get(route('admin.users.edit', $owner))->assertForbidden();
        $this->put(route('admin.users.update', $owner), ['name' => 'Hacked', 'email' => $owner->email, 'is_active' => '1'])->assertForbidden();
        $this->delete(route('admin.users.destroy', $owner))->assertForbidden();

        $this->post(route('admin.users.store'), $this->userPayload(['is_owner' => '1']));
        $this->assertFalse(Admin::where('email', 'nimali@s.lk')->first()->is_owner);

        $target = $this->user();
        $this->put(route('admin.users.update', $target), ['name' => 'X', 'email' => $target->email, 'is_active' => '1', 'is_owner' => '1']);
        $this->assertFalse($target->fresh()->is_owner);
        $this->assertSame($owner->name, $owner->fresh()->name);
    }

    public function test_cannot_reset_the_password_of_someone_with_more_access(): void
    {
        $senior = $this->user(['reports.view', 'stock.view'], ['email' => 'senior@s.lk']);
        $junior = $this->user(['stock.view']);
        $this->actingAs($this->user(['users.manage', 'stock.view']), 'admin');

        $payload = ['is_active' => '1', 'password' => 'takeover123', 'password_confirmation' => 'takeover123', 'permissions' => ['stock.view']];

        $this->put(route('admin.users.update', $senior), $payload + ['name' => 'Senior', 'email' => 'senior@s.lk'])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('secret123', $senior->fresh()->password));

        $this->put(route('admin.users.update', $junior), $payload + ['name' => 'Junior', 'email' => $junior->email])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('takeover123', $junior->fresh()->password));
    }

    public function test_owner_can_remove_another_owner_but_never_themselves(): void
    {
        $owner = $this->owner();
        $second = $this->owner();
        $this->actingAs($owner, 'admin');

        $this->put(route('admin.users.update', $second), ['name' => 'Second', 'email' => $second->email, 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertFalse($second->fresh()->is_owner);

        $this->put(route('admin.users.update', $owner), ['name' => 'Me', 'email' => $owner->email, 'is_active' => '1'])->assertForbidden();
        $this->delete(route('admin.users.destroy', $owner))->assertSessionHas('error');
        $this->assertTrue($owner->fresh()->is_owner);
    }

    /** The signed-in owner counts as "another owner", so this only fires when two owners act at the same moment. */
    public function test_last_owner_guard_refuses_when_nobody_else_is_left(): void
    {
        $only = $this->owner();
        $this->owner(['is_active' => false]);
        $guard = new \ReflectionMethod(\App\Http\Controllers\Admin\AdminUserController::class, 'ensureAnotherOwner');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $guard->invoke(new \App\Http\Controllers\Admin\AdminUserController(), $only);
    }

    public function test_last_owner_guard_passes_when_another_active_owner_exists(): void
    {
        $only = $this->owner();
        $this->owner();
        $guard = new \ReflectionMethod(\App\Http\Controllers\Admin\AdminUserController::class, 'ensureAnotherOwner');

        $guard->invoke(new \App\Http\Controllers\Admin\AdminUserController(), $only);
        $this->assertTrue($only->fresh()->is_owner);
    }

    public function test_permission_changes_are_logged_with_what_changed(): void
    {
        $target = $this->user(['stock.view', 'reports.view']);
        $this->actingAs($this->owner(), 'admin');

        $this->put(route('admin.users.update', $target), ['name' => $target->name, 'email' => $target->email, 'is_active' => '1', 'permissions' => ['stock.view', 'logs.view']]);

        $log = SystemLog::where('action', 'user.updated')->latest('id')->first();
        $this->assertSame('warning', $log->level);
        $this->assertSame(['See the system log'], $log->properties['permissions']['granted']);
        $this->assertSame(['See reports & revenue'], $log->properties['permissions']['revoked']);
    }

    public function test_deleting_a_user_is_logged(): void
    {
        $target = $this->user();
        $this->actingAs($this->owner(), 'admin');

        $this->delete(route('admin.users.destroy', $target))->assertRedirect();

        $this->assertNull($target->fresh());
        $this->assertDatabaseHas('system_logs', ['action' => 'user.deleted', 'level' => 'warning']);
    }

    // ── Stylist portal ──────────────────────────────────────────────────

    private function stylist(?array $permissions = null): Staff
    {
        return Staff::create([
            'name' => 'Amaya', 'is_active' => true, 'login_username' => 'amaya' . Str::random(4), 'password' => Hash::make('secret123'),
            'table_qr_token' => Str::random(40), 'portal_permissions' => $permissions,
        ]);
    }

    public function test_stylists_can_do_everything_by_default(): void
    {
        $this->actingAs($stylist = $this->stylist(), 'staff');

        foreach (array_keys(Permissions::PORTAL) as $permission) {
            $this->assertTrue($stylist->portalCan($permission));
        }

        $this->get(route('stylist.dashboard'))->assertOk()->assertSee('Walk-in')->assertSee('Phone booking');
        $this->get(route('stylist.walkin'))->assertOk();
    }

    public function test_stylist_without_walkins_gets_phone_bookings_only(): void
    {
        $this->actingAs($this->stylist(['portal.bookings']), 'staff');
        $this->appointment(['appointment_date' => now()->toDateString()]);
        $service = Service::first();

        $this->get(route('stylist.dashboard'))->assertOk()->assertDontSee(route('stylist.walkin') . '"', false)->assertSee('Phone booking');
        $this->get(route('stylist.walkin'))->assertOk()->assertDontSee('data-mode="walkin"', false)->assertSee('data-mode="later"', false);

        $this->getJson(route('stylist.walkin-slots', ['service_id' => $service->id, 'mode' => 'walkin']))->assertForbidden();
        $this->postJson(route('stylist.walkin-store'), ['customer_name' => 'New', 'customer_phone' => '0771112222', 'service_id' => $service->id, 'start_time' => '10:00'])->assertForbidden();
    }

    public function test_stylist_without_phone_bookings_gets_walkins_only(): void
    {
        $this->actingAs($this->stylist(['portal.walkin']), 'staff');
        $this->appointment();
        $service = Service::first();

        $this->get(route('stylist.walkin', ['mode' => 'later']))->assertOk()->assertDontSee('data-mode="later"', false)->assertSee('is-active', false);

        $this->getJson(route('stylist.walkin-slots', ['service_id' => $service->id, 'mode' => 'later', 'date' => now()->toDateString()]))->assertForbidden();
        $this->postJson(route('stylist.walkin-store'), [
            'mode' => 'later', 'type' => 'phone', 'date' => now()->toDateString(), 'customer_name' => 'New', 'customer_phone' => '0771112222',
            'customer_email' => 'n@x.lk', 'service_id' => $service->id, 'start_time' => '10:00',
        ])->assertForbidden();
    }

    public function test_stylist_with_neither_is_sent_back_with_an_explanation(): void
    {
        $this->actingAs($this->stylist([]), 'staff');

        $this->get(route('stylist.walkin'))->assertRedirect(route('stylist.dashboard'))->assertSessionHas('error');
    }

    public function test_stylist_without_extras_cannot_add_them(): void
    {
        $stylist = $this->stylist(['portal.walkin', 'portal.bookings']);
        $a = $this->appointment(['staff_id' => $stylist->id, 'appointment_date' => now()->toDateString(), 'status' => 'in_progress']);
        $this->actingAs($stylist, 'staff');

        $this->get(route('stylist.dashboard'))->assertOk()->assertDontSee('data-extras="' . $a->id . '"', false);
        $this->postJson(url("/stylist/appointments/{$a->id}/items"), ['name' => 'Toner', 'price' => 500, 'quantity' => 1])->assertForbidden();
        $this->assertSame(0, $a->items()->count());
    }

    public function test_stylist_with_extras_sees_the_button(): void
    {
        $stylist = $this->stylist();
        $a = $this->appointment(['staff_id' => $stylist->id, 'appointment_date' => now()->toDateString(), 'status' => 'in_progress']);

        $this->actingAs($stylist, 'staff')->get(route('stylist.dashboard'))->assertSee('data-extras="' . $a->id . '"', false);
    }

    public function test_staff_form_saves_portal_permissions(): void
    {
        $stylist = $this->stylist();
        $this->actingAs($this->user(['staff.manage']), 'admin');
        $base = ['name' => 'Amaya', 'is_active' => '1', 'portal_form' => '1'];

        $this->get(route('admin.employees.edit', $stylist))->assertOk()->assertSee('Portal permissions');

        // Unknown values never get stored.
        $this->put(route('admin.employees.update', $stylist), $base + ['portal_permissions' => ['portal.walkin', 'portal.everything']])->assertSessionHasNoErrors();
        $this->assertSame(['portal.walkin'], $stylist->fresh()->portal_permissions);

        // Nothing ticked means nothing allowed, not "everything".
        $this->put(route('admin.employees.update', $stylist), $base);
        $this->assertSame([], $stylist->fresh()->portal_permissions);
        $this->assertFalse($stylist->fresh()->portalCan('portal.walkin'));

        // Everything ticked goes back to the default.
        $this->put(route('admin.employees.update', $stylist), $base + ['portal_permissions' => array_keys(Permissions::PORTAL)]);
        $this->assertNull($stylist->fresh()->portal_permissions);
    }

    public function test_staff_update_without_the_portal_section_leaves_it_alone(): void
    {
        $stylist = $this->stylist(['portal.walkin']);
        $this->actingAs($this->user(['staff.manage']), 'admin');

        $this->put(route('admin.employees.update', $stylist), ['name' => 'Amaya', 'is_active' => '1'])->assertSessionHasNoErrors();

        $this->assertSame(['portal.walkin'], $stylist->fresh()->portal_permissions);
    }

    public function test_settings_page_hides_cashier_link_without_payments(): void
    {
        Setting::updateOrCreate(['key' => 'salon_name'], ['value' => 'Falothes']);
        setting('__flush');

        $this->actingAs($this->user(['settings.manage']), 'admin')
            ->get(route('admin.settings.index'))->assertOk()
            ->assertDontSee(route('cashier.index'));
    }
}
