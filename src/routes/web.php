<?php

declare(strict_types=1);

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Auth\CalendarFeedTokenController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MailPreferencesController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\CalendarEntryICalendarController;
use App\Http\Controllers\CalendarPageController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Knowledge\KnowledgeController;
use App\Http\Controllers\Knowledge\KnowledgeFeedbackController;
use App\Http\Controllers\Knowledge\KnowledgeSearchController;
use App\Http\Controllers\Knowledge\KnowledgeVisibleGuidesController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\PersonalCalendarFeedController;
use App\Http\Controllers\PublicCalendarFeedController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SearchSuggestionsController;
use App\Http\Controllers\SitemapController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', HomeController::class)->name('home');
Route::get('/nosotros', AboutController::class)->name('about');
Route::post('/contacto', ContactController::class)->middleware('throttle:contact')->name('contact.store');
Route::get('/calendario', CalendarPageController::class)->name('calendar');
// Same for everyone: no session (nor its cookie), so shared caches can keep it
Route::get('/calendario.ics', PublicCalendarFeedController::class)
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        AddQueuedCookiesToResponse::class,
    ])
    ->middleware('throttle:calendar-public-feed')
    ->name('calendar.public.ics');
// Before /calendario/{source}/{id}.ics, whose pattern would swallow it
Route::get('/calendario/personal/{token}.ics', PersonalCalendarFeedController::class)
    ->where('token', '[A-Za-z0-9_-]{43}')
    ->middleware('throttle:calendar-feed')
    ->name('calendar.personal.ics');
Route::get('/calendario/{source}/{id}.ics', CalendarEntryICalendarController::class)
    ->where(['source' => '[a-z0-9-]+', 'id' => '[A-Za-z0-9_-]+'])
    ->name('calendar.entry.ics');

Route::get('/eventos/calendario', [CalendarController::class, 'index'])->name('events.calendar');
Route::get('/eventos', [EventController::class, 'index'])->name('events.index');
Route::get('/eventos/{slug}', [EventController::class, 'show'])->name('events.show');

Route::get('/articulos', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articulos/{slug}', [ArticleController::class, 'show'])->name('articles.show');

Route::get('/galeria', [GalleryController::class, 'index'])->name('galleries.index');
Route::get('/galeria/{slug}', [GalleryController::class, 'show'])->name('galleries.show');

Route::get('/buscar', SearchController::class)->name('search');
Route::get('/buscar/sugerencias', SearchSuggestionsController::class)
    ->middleware('throttle:search-suggestions')
    ->name('search.suggestions');

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
    // Throttled: it stays open to unverified accounts and an email change sends a mail.
    // Own prefix: without it, it would share the per-user counter of verification.send
    Route::post('/perfil', [ProfileController::class, 'update'])
        ->middleware('throttle:6,1,profile')
        ->name('profile.update');
    Route::put('/perfil/contrasena', [ProfileController::class, 'changePassword'])->name('profile.password');
    // Which optional mail the person receives. Open to accounts that must verify their email
    // (it is where they stop a mail); the service decides which categories are editable
    Route::put('/perfil/correos', [MailPreferencesController::class, 'update'])
        ->middleware('throttle:30,1,profile-mail-preferences')
        ->name('profile.mail-preferences.update');
    // Personal calendar address: issued (or regenerated) and revoked from the profile
    Route::post('/perfil/calendario', [CalendarFeedTokenController::class, 'store'])
        ->middleware('throttle:6,1,calendar-feed-token')
        ->name('profile.calendar-feed.store');
    Route::delete('/perfil/calendario', [CalendarFeedTokenController::class, 'destroy'])
        ->middleware('throttle:6,1,calendar-feed-token')
        ->name('profile.calendar-feed.destroy');

    // Help centre. Literal routes go before /ayuda/{guide}, whose pattern would swallow them
    Route::get('/ayuda', [KnowledgeController::class, 'index'])->name('knowledge.index');
    // Own prefixes: without them, search, vote and verification.send would share one per-user counter
    Route::get('/ayuda/buscar', KnowledgeSearchController::class)
        ->middleware('throttle:60,1,knowledge-search')
        ->name('knowledge.search');
    Route::get('/ayuda/visibles', KnowledgeVisibleGuidesController::class)->name('knowledge.visible');
    Route::get('/ayuda/categoria/{category}', [KnowledgeController::class, 'category'])->name('knowledge.category');
    Route::post('/ayuda/{guide}/voto', KnowledgeFeedbackController::class)
        ->where('guide', '[a-z0-9.-]+')
        ->middleware('throttle:10,1,knowledge-vote')
        ->name('knowledge.vote');
    Route::get('/ayuda/{guide}', [KnowledgeController::class, 'show'])
        ->where('guide', '[a-z0-9.-]+')
        ->name('knowledge.show');
});
