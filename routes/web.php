<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\OfferController as AdminOfferController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\ReceiptController;

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AppointmentController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\FeedbackController;
use App\Http\Controllers\Admin\GalleryController as AdminGalleryController;
use App\Http\Controllers\Admin\HoursController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Admin\SystemLogController;

use App\Http\Controllers\Stylist\StylistAuthController;
use App\Http\Controllers\Stylist\StylistDashboardController;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/services', [ServiceController::class, 'index'])->name('services');
Route::get('/services/{category:slug}', [ServiceController::class, 'category'])->name('services.category');
Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery');
Route::get('/offers', [OfferController::class, 'index'])->name('offers');

Route::get('/booking', [BookingController::class, 'index'])->name('booking');
Route::get('/booking/slots', [BookingController::class, 'availableSlots'])
    ->middleware('throttle:slots')->name('booking.slots');
Route::post('/booking/store', [BookingController::class, 'store'])
    ->middleware('throttle:booking')->name('booking.store');

Route::get('/booking/manage', [BookingController::class, 'manage'])->name('booking.manage');
Route::post('/booking/manage', [BookingController::class, 'lookup'])
    ->middleware('throttle:lookup')->name('booking.lookup');
Route::post('/booking/cancel', [BookingController::class, 'cancel'])
    ->middleware('throttle:lookup')->name('booking.cancel');
Route::post('/booking/manage/forget', [BookingController::class, 'forget'])->name('booking.forget');
Route::get('/booking/manage/change', [BookingController::class, 'edit'])->name('booking.edit');
Route::post('/booking/manage/change', [BookingController::class, 'update'])
    ->middleware('throttle:booking')->name('booking.update');
Route::post('/booking/manage/feedback', [BookingController::class, 'feedback'])
    ->middleware('throttle:lookup')->name('booking.feedback');

// Rate your visit (receipt QR or reference + phone)
Route::get('/feedback', [ReviewController::class, 'create'])->name('review.create');
Route::get('/feedback/visit/{appointment}', [ReviewController::class, 'link'])
    ->middleware(['signed', 'throttle:lookup'])->name('review.link');
Route::post('/feedback/find', [ReviewController::class, 'lookup'])
    ->middleware('throttle:lookup')->name('review.lookup');
Route::post('/feedback', [ReviewController::class, 'store'])
    ->middleware('throttle:lookup')->name('review.store');
Route::post('/feedback/other', [ReviewController::class, 'forget'])->name('review.forget');

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/

Route::get('/admin/login', [AuthController::class, 'loginForm'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {

    // Every signed-in user. The dashboard only shows what they're allowed to see.
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('account', [AccountController::class, 'update'])->name('account.update');

    // Bookings. Fixed paths come before appointments/{appointment}.
    Route::get('appointments/export', [AppointmentController::class, 'export'])->middleware('perm:appointments.export')->name('appointments.export');

    Route::middleware('perm:appointments.manage')->group(function () {
        Route::get('appointments/slots', [AppointmentController::class, 'slots'])->name('appointments.slots');
        Route::get('appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
        Route::post('appointments/{appointment}/reschedule', [AppointmentController::class, 'saveReschedule'])->name('appointments.reschedule.save');
        Route::post('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.status');
        Route::post('appointments/{appointment}/confirmation-email', [AppointmentController::class, 'resendConfirmation'])->name('appointments.confirmation-email');
        Route::post('appointments/{appointment}/items', [AppointmentController::class, 'addItem'])->name('appointments.items.store');
        Route::delete('appointments/{appointment}/items/{item}', [AppointmentController::class, 'removeItem'])->name('appointments.items.destroy');
        Route::resource('appointments', AppointmentController::class)->only(['create', 'store', 'update']);
    });

    Route::post('appointments/{appointment}/payments', [AppointmentController::class, 'addPayment'])->middleware('perm:payments.take')->name('appointments.payments.store');
    Route::delete('appointments/{appointment}/payments/{payment}', [AppointmentController::class, 'voidPayment'])->middleware('perm:payments.void')->name('appointments.payments.destroy');
    Route::resource('appointments', AppointmentController::class)->only(['destroy'])->middleware('perm:appointments.delete');

    Route::middleware('perm:appointments.view')->group(function () {
        Route::get('schedule', [ScheduleController::class, 'index'])->name('schedule');
        Route::get('appointments/{appointment}/receipt', [ReceiptController::class, 'show'])->name('appointments.receipt');
        Route::resource('appointments', AppointmentController::class)->only(['index', 'show']);
    });

    // Customers & reviews
    Route::resource('customers', CustomerController::class)->only(['edit', 'update', 'destroy'])->middleware('perm:customers.manage');
    Route::resource('customers', CustomerController::class)->only(['index', 'show'])->middleware('perm:customers.view');

    Route::middleware('perm:feedback.manage')->group(function () {
        Route::get('feedback', [FeedbackController::class, 'index'])->name('feedback.index');
        Route::post('feedback/{feedback}/publish', [FeedbackController::class, 'togglePublish'])->name('feedback.publish');
        Route::delete('feedback/{feedback}', [FeedbackController::class, 'destroy'])->name('feedback.destroy');
    });

    // Catalog
    Route::middleware('perm:services.manage')->group(function () {
        Route::resource('services', AdminServiceController::class)->except(['show']);
        Route::resource('categories', ServiceCategoryController::class)->except(['show']);
    });
    Route::resource('offers', AdminOfferController::class)->except(['show'])->middleware('perm:offers.manage');

    // Stock. Fixed paths come before stock/{product}.
    Route::middleware('perm:stock.manage')->group(function () {
        Route::get('stock/restock', [ProductController::class, 'restockForm'])->name('products.restock');
        Route::post('stock/restock', [ProductController::class, 'restock'])->name('products.restock.store');
        Route::get('stock/{product}/adjust', [ProductController::class, 'adjustForm'])->name('products.adjust');
        Route::post('stock/{product}/adjust', [ProductController::class, 'adjust'])->name('products.adjust.store');
        Route::post('stock/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');
        Route::resource('stock', ProductController::class)->parameters(['stock' => 'product'])->names('products')->only(['create', 'store', 'edit', 'update', 'destroy']);
    });
    Route::resource('stock', ProductController::class)->parameters(['stock' => 'product'])->names('products')->only(['index', 'show'])->middleware('perm:stock.view');

    // Team
    Route::middleware('perm:staff.manage')->group(function () {
        Route::resource('employees', EmployeeController::class)->except(['show']);
        Route::post('employees/{employee}/set-login', [EmployeeController::class, 'setLogin'])->name('employees.set-login');
        Route::post('employees/{employee}/regenerate-qr', [EmployeeController::class, 'regenerateQr'])->name('employees.regenerate-qr');
    });

    Route::resource('users', AdminUserController::class)->except(['show'])->middleware('perm:users.manage');

    // Reports
    Route::middleware('perm:reports.view')->group(function () {
        Route::get('reports/{tab?}', [ReportController::class, 'show'])
            ->whereIn('tab', array_keys(ReportController::TABS))->name('reports');
        Route::get('reports/{tab}/export', [ReportController::class, 'export'])
            ->whereIn('tab', array_keys(ReportController::TABS))->name('reports.export');
    });

    Route::middleware('perm:logs.view')->group(function () {
        Route::get('logs', [SystemLogController::class, 'index'])->name('logs.index');
        Route::get('logs/export', [SystemLogController::class, 'export'])->name('logs.export');
    });

    // Business
    Route::middleware('perm:hours.manage')->group(function () {
        Route::get('hours', [HoursController::class, 'index'])->name('hours.index');
        Route::put('hours', [HoursController::class, 'update'])->name('hours.update');
        Route::post('hours/closures', [HoursController::class, 'storeClosure'])->name('hours.closures.store');
        Route::delete('hours/closures/{dayOff}', [HoursController::class, 'destroyClosure'])->name('hours.closures.destroy');
    });

    Route::middleware('perm:website.manage')->group(function () {
        Route::resource('sliders', SliderController::class)->except(['show']);
        Route::resource('gallery', AdminGalleryController::class)->except(['show', 'create'])
            ->parameters(['gallery' => 'image']);
    });

    Route::middleware('perm:settings.manage')->group(function () {
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::put('settings/cashier-pin', [SettingController::class, 'updateCashierPin'])->name('settings.cashier-pin');
        Route::post('settings/branding', [SettingController::class, 'updateBranding'])->name('settings.branding');
        Route::post('settings/test-email', [SettingController::class, 'testEmail'])->name('settings.test-email');
    });
});

/*
|--------------------------------------------------------------------------
| Stylist portal
|--------------------------------------------------------------------------
*/

Route::get('/stylist/login', [StylistAuthController::class, 'loginForm'])->name('stylist.login');
Route::post('/stylist/login', [StylistAuthController::class, 'login'])->middleware('throttle:login')->name('stylist.login.post');
Route::post('/stylist/logout', [StylistAuthController::class, 'logout'])->name('stylist.logout');

Route::middleware('staff')->prefix('stylist')->name('stylist.')->group(function () {
    Route::get('/dashboard', [StylistDashboardController::class, 'index'])->name('dashboard');
    Route::post('/scan-customer', [StylistDashboardController::class, 'scanCustomerQr'])->name('scan-customer');
    Route::post('/complete/{appointment}', [StylistDashboardController::class, 'completeJob'])->name('complete');
    Route::get('/walkin', [StylistDashboardController::class, 'walkinForm'])->name('walkin');
    Route::get('/walkin-slots', [StylistDashboardController::class, 'walkinSlots'])->name('walkin-slots');
    Route::post('/walkin-store', [StylistDashboardController::class, 'walkinStore'])->name('walkin-store');
    Route::middleware('portal:portal.extras')->group(function () {
        Route::get('/appointments/{appointment}/charges', [StylistDashboardController::class, 'charges'])->name('charges');
        Route::post('/appointments/{appointment}/items', [StylistDashboardController::class, 'addItem'])->name('items.store');
        Route::delete('/appointments/{appointment}/items/{item}', [StylistDashboardController::class, 'removeItem'])->name('items.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Cashier station (admin session or station PIN)
|--------------------------------------------------------------------------
*/

Route::get('/cashier/unlock', [CashierController::class, 'unlockForm'])->name('cashier.unlock');
Route::post('/cashier/unlock', [CashierController::class, 'unlock'])->middleware('throttle:login')->name('cashier.unlock.post');

Route::middleware('cashier')->prefix('cashier')->name('cashier.')->group(function () {
    Route::get('/', [CashierController::class, 'index'])->name('index');
    Route::post('/lookup', [CashierController::class, 'lookup'])->middleware('throttle:lookup-staff')->name('lookup');
    Route::post('/pay/{appointment}', [CashierController::class, 'processPayment'])->name('pay');
    Route::get('/receipt/{appointment}', [ReceiptController::class, 'show'])->name('receipt');
    Route::post('/appointments/{appointment}/items', [CashierController::class, 'addItem'])->name('items.store');
    Route::delete('/appointments/{appointment}/items/{item}', [CashierController::class, 'removeItem'])->name('items.destroy');
    Route::post('/lock', [CashierController::class, 'lock'])->name('lock');
});
