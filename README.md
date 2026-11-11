# Salon Booking System

Online booking, admin back office, stylist portal and cashier station for a hair & beauty salon.
Laravel 12 · Blade · plain CSS (`public/css/app.css`) · vanilla JS. Light and dark themes.

## Areas

| URL | Who | What |
|---|---|---|
| `/` `/services` | Customers | Homepage, service menu with prices |
| `/booking` | Customers | Book: service → stylist/date/time → details → QR confirmation |
| `/booking/manage` | Customers | Look up a booking (reference + phone) and cancel it |
| `/admin` | Owner / manager | Dashboard, day calendar, appointments, customers, services, staff, hours, settings |
| `/stylist/login` | Stylists | Today's jobs, scan customer QR to start, finish jobs, walk-ins |
| `/cashier` | Front desk | Look up job number / QR, take cash or card payment, print receipt |

## Setup

```bash
composer install
cp .env.example .env && php artisan key:generate
# configure DB_* in .env
php artisan migrate --seed          # settings + default opening hours
php artisan storage:link            # needed for service/hero photos
php artisan salon:admin             # create the first admin account
```

`php artisan salon:admin` can also reset an admin's password.

### Admin users and permissions
Add people in **Admin → Admin users** and tick what each may do, or start from a preset (Manager,
Receptionist, Cashier, Stock keeper). Owners can do everything. Changes apply straight away, even if
the person is signed in. Routes enforce every permission; the catalog is in `app/Support/Permissions.php`,
and `PermissionTest` fails if an admin route is left without a `perm:` check.

Nobody can change their own access or grant a permission they don't hold. Only owners can touch
owners or make someone an owner. `php artisan salon:admin` always creates an active owner, so it is
also the way back in if every owner is locked out.

### Offers
**Admin → Offers** sets a discount (a percentage or an amount) for all services or chosen ones, between
two dates. An offer applies when the **visit** falls on those dates, whenever the booking is made, and
the customer gets the bigger offer if two overlap. The discount is saved on the booking
(`discount_amount`, `offer_name`), so changing or deleting an offer never changes a booking already made.
Moving a booking to another date or service re-checks offers; changing only the time or stylist keeps
the price. Pricing lives in `App\Services\OfferPricing`.

### Cashier station
Open to admin users with the **Take payments** permission. To use it on a front-desk device without
an admin login, set a station PIN in **Admin → Settings → Cashier station**. Anyone with the PIN can
use the station, whatever their permissions.

### Stylist logins
Add staff in **Admin → Staff**, then use **Login** on their row to set a username and password.
The **QR** button prints a station QR that opens the walk-in form for that stylist. **Portal
permissions** on the staff form turn off walk-ins, phone bookings or extras for that person.

## Production checklist

- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, correct `APP_URL` (https).
- Serve over **HTTPS** — browsers only allow camera QR scanning on secure origins.
- `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- Back up the database regularly.

## Booking rules (Admin → Settings)

- **Book up to N days ahead**, **minimum notice**, **online cancellation cut-off**
- **Time slot spacing**: match service length, or every 15/30/60 minutes
- **Auto-confirm** online bookings, or keep them pending for review
- Opening hours and closure dates: **Admin → Opening hours**

All bookings (online, phone, walk-in) are re-validated on the server against live availability under a lock,
so a slot can't be double-booked.

## Tests

```bash
php artisan test
```
