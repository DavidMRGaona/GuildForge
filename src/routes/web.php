<?php

declare(strict_types=1);

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\CalendarPageController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/nosotros', AboutController::class)->name('about');
Route::post('/contacto', ContactController::class)->middleware('throttle:contact')->name('contact.store');
Route::get('/calendario', CalendarPageController::class)->name('calendar');

Route::get('/eventos/calendario', [CalendarController::class, 'index'])->name('events.calendar');
Route::get('/eventos', [EventController::class, 'index'])->name('events.index');
Route::get('/eventos/{slug}', [EventController::class, 'show'])->name('events.show');

Route::get('/articulos', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articulos/{slug}', [ArticleController::class, 'show'])->name('articles.show');

Route::get('/galeria', [GalleryController::class, 'index'])->name('galleries.index');
Route::get('/galeria/{slug}', [GalleryController::class, 'show'])->name('galleries.show');

Route::get('/buscar', SearchController::class)->name('search');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/{slug}', [LegalPageController::class, 'show'])
    ->where('slug', 'politica-de-privacidad|aviso-legal|politica-de-cookies|terminos-y-condiciones')
    ->name('legal.show');

// Guest routes
Route::middleware('guest')->group(function (): void {
    // Registration (rate limiting only on POST to avoid counting page views)
    Route::middleware('registration.enabled')->group(function (): void {
        Route::get('/registro', [RegisterController::class, 'create'])->name('register');
        Route::post('/registro', [RegisterController::class, 'store'])->middleware('throttle:5,1');
    });

    // Login (rate limiting only on POST to avoid counting page views)
    Route::middleware('login.enabled')->group(function (): void {
        Route::get('/iniciar-sesion', [LoginController::class, 'create'])->name('login');
        Route::post('/iniciar-sesion', [LoginController::class, 'store'])->middleware('throttle:login');
    });

    // Password reset
    Route::get('/olvide-contrasena', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/olvide-contrasena', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/restablecer-contrasena', [ResetPasswordController::class, 'store'])->name('password.update');
});

// Authenticated routes
Route::middleware('auth')->group(function (): void {
    Route::post('/cerrar-sesion', [LoginController::class, 'destroy'])->name('logout');

    // Email verification
    Route::get('/verificar-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verificar-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('/verificar-email/reenviar', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('/verificar-email-pendiente/{id}/{hash}', [EmailVerificationController::class, 'verifyPendingEmail'])
        ->middleware('signed')
        ->name('verification.pending-email');

    // Profile
    Route::get('/perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/perfil/contrasena', [ProfileController::class, 'changePassword'])->name('profile.password');
});
