<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\BookingController;

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\AppointmentController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\SettingController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/services', [ServiceController::class, 'index'])->name('services');

Route::get('/booking', [BookingController::class, 'index'])->name('booking');
Route::post('/booking/store', [BookingController::class, 'store'])->name('booking.store');
Route::get('/booking/slots', [BookingController::class, 'availableSlots']);

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::get('/admin/login',[AuthController::class,'loginForm']);
Route::post('/admin/login',[AuthController::class,'login']);

Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('sliders', SliderController::class);
    Route::resource('categories', ServiceCategoryController::class);
    Route::resource('services', AdminServiceController::class);
    Route::resource('staff', StaffController::class);
    Route::resource('customers', CustomerController::class);
    Route::resource('appointments', AppointmentController::class);
    Route::resource('settings', SettingController::class)->only(['index', 'update']);

    Route::post(
        'appointments/{appointment}/status',
        [AppointmentController::class, 'updateStatus']
    )->name('appointments.status');

    Route::get('/logout', [AuthController::class, 'logout']);
});
