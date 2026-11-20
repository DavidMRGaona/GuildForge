<?php

declare(strict_types=1);

use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Auth\CalendarFeedTokenController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MailPauseController;
use App\Http\Controllers\Auth\MailPreferencesController;
use App\Http\Controllers\Auth\MutedMailSubjectsController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\ProfileTwoFactorController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\StopImpersonationController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\CalendarEntryICalendarController;
use App\Http\Controllers\CalendarPageController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EditorImageUploadController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Knowledge\KnowledgeController;
use App\Http\Controllers\Knowledge\KnowledgeFeedbackController;
use App\Http\Controllers\Knowledge\KnowledgeSearchController;
use App\Http\Controllers\Knowledge\KnowledgeVisibleGuidesController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\MailLogoController;
use App\Http\Controllers\MailSubjectMuteController;
use App\Http\Controllers\MailUnsubscribeController;
use App\Http\Controllers\OneClickMailUnsubscribeController;
use App\Http\Controllers\PersonalCalendarFeedController;
use App\Http\Controllers\Profiles\PublicProfileCardController;
use App\Http\Controllers\Profiles\PublicProfileController;
use App\Http\Controllers\Profiles\PublicProfileEditController;
use App\Http\Controllers\Proposals\ArticleProposalController;
use App\Http\Controllers\Proposals\EventProposalController;
use App\Http\Controllers\Proposals\ProposalActionController;
use App\Http\Controllers\PublicCalendarFeedController;
use App\Http\Controllers\RealtimeAuthController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SearchSuggestionsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SiteNotificationsController;
use App\Http\Controllers\WebApp\ServiceWorkerController;
use App\Http\Controllers\WebApp\WebAppManifestController;
use App\Http\Middleware\KeepOutOfSearchEngines;
use App\Http\Middleware\ThrottleWhereListed;
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

// The SVG mail logo drawn as a PNG (#500). Same for everyone and no session, so the image proxies
// of mail clients can keep it; not throttled, since they fetch for many readers from few addresses
Route::get('/correos/logo.png', MailLogoController::class)
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        AddQueuedCookiesToResponse::class,
    ])
    ->name('mail.logo');

// The installable site (core#802): manifest and service worker at fixed URLs, the same for everybody,
// so no session or cookie. Both stay reachable during maintenance (EnsureSiteIsNotInMaintenance).
Route::withoutMiddleware([
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
    AddQueuedCookiesToResponse::class,
])->group(function (): void {
    Route::get('/manifest.webmanifest', WebAppManifestController::class)->name('web-app.manifest');
    Route::get('/sw.js', ServiceWorkerController::class)->name('web-app.service-worker');
});

// Unsubscribe links of the mail footer and the List-Unsubscribe header: signed per account and
// category, no expiry, no sign-in. Throttled before the signature, so guessing counts too.
Route::middleware(['throttle:mail-unsubscribe', 'signed'])
    ->where(['user' => '[0-9a-fA-F-]{36}', 'category' => '[a-z0-9]+(?:-[a-z0-9]+)*\.[a-z0-9]+(?:-[a-z0-9]+)*'])
    ->group(function (): void {
        // Opening only asks to confirm: spam filters open the links of a mail
        Route::get('/correos/baja/{user}/{category}', [MailUnsubscribeController::class, 'show'])->name('mail.unsubscribe');
        Route::post('/correos/baja/{user}/{category}', [MailUnsubscribeController::class, 'store'])->name('mail.unsubscribe.store');
        Route::delete('/correos/baja/{user}/{category}', [MailUnsubscribeController::class, 'destroy'])->name('mail.unsubscribe.destroy');

        // RFC 8058: the mail client posts with no session nor CSRF token. The only route without CSRF
        Route::post('/correos/baja/{user}/{category}/un-clic', [OneClickMailUnsubscribeController::class, 'store'])
            ->withoutMiddleware([
                StartSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                AddQueuedCookiesToResponse::class,
            ])
            ->name('mail.unsubscribe.one-click');
        Route::get('/correos/baja/{user}/{category}/un-clic', [OneClickMailUnsubscribeController::class, 'show'])
            ->name('mail.unsubscribe.one-click.show');
    });

// Muting one thing (a game, a tournament...) from a mail footer (core#334): signed per account,
// thing and names (query string), no expiry, no sign-in. Same rate limit as the unsubscribe links
Route::middleware(['throttle:mail-unsubscribe', 'signed'])
    ->where(['user' => '[0-9a-fA-F-]{36}', 'type' => MailSubjectDTO::TYPE_PATTERN, 'subject' => MailSubjectDTO::ID_PATTERN])
    ->group(function (): void {
        // Opening only asks to confirm: spam filters open the links of a mail
        Route::get('/correos/silenciar/{user}/{type}/{subject}', [MailSubjectMuteController::class, 'show'])->name('mail.subject-mute');
        Route::post('/correos/silenciar/{user}/{type}/{subject}', [MailSubjectMuteController::class, 'store'])->name('mail.subject-mute.store');
        Route::delete('/correos/silenciar/{user}/{type}/{subject}', [MailSubjectMuteController::class, 'destroy'])->name('mail.subject-mute.destroy');
    });

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

// Public profiles (core#741): decided per viewer by ProfileAccessRule, never indexed, with the same 404
// for an address that does not exist and for a profile the viewer may not see
Route::get('/miembros/{handle}', [PublicProfileController::class, 'show'])
    ->middleware(KeepOutOfSearchEngines::class)
    ->name('profiles.show');
// Its summary card (core#744), as JSON, under the same rule, the same 404 and a limit of its own. The
// limit runs inside the web group (after the impersonation) and inside the robots header, so the 429
// carries it; `throttle:` would be moved ahead of them (see ThrottleWhereListed)
Route::get('/miembros/{handle}/tarjeta', PublicProfileCardController::class)
    ->middleware([KeepOutOfSearchEngines::class, ThrottleWhereListed::class.':profile-card'])
    ->name('profiles.card');

// The owner editing their own profile from it (core#743): signed in, throttled per account, and in the
// web group like every write, so a read-only impersonation refuses it. Nobody but the owner, not even an
// administrator: anybody else gets the 404 of an unknown address
Route::middleware(['auth', KeepOutOfSearchEngines::class])->group(function (): void {
    Route::put('/miembros/{handle}', [PublicProfileEditController::class, 'update'])
        ->middleware('throttle:profile-edit')
        ->name('profiles.update');
    Route::get('/miembros/{handle}/disponibilidad', [PublicProfileEditController::class, 'availability'])
        ->middleware('throttle:profile-handle-check')
        ->name('profiles.handle-availability');
    Route::post('/miembros/{handle}/banner', [PublicProfileEditController::class, 'storeBanner'])
        ->middleware('throttle:profile-banner')
        ->name('profiles.banner.store');
    Route::delete('/miembros/{handle}/banner', [PublicProfileEditController::class, 'destroyBanner'])
        ->middleware('throttle:profile-banner')
        ->name('profiles.banner.destroy');
});

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

// The second step of signing in (core#770): outside `guest`, since an account already signed in
// is sent where it was going, and closed with the login
Route::middleware('login.enabled')->group(function (): void {
    Route::get('/iniciar-sesion/verificacion', [TwoFactorChallengeController::class, 'create'])->name('login.two-factor');
    Route::post('/iniciar-sesion/verificacion', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:two-factor-challenge')
        ->name('login.two-factor.store');
});

// Authenticated routes
Route::middleware('auth')->group(function (): void {
    Route::post('/cerrar-sesion', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/impersonar/salir', StopImpersonationController::class)->name('impersonation.stop');

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
    // Two-factor authentication from «Seguridad» (core#769): the secret and the recovery codes are only in
    // the answer that creates them. Same per-account limit on all of them (codes are guessed here too)
    Route::middleware('throttle:two-factor-manage')->prefix('/perfil/doble-factor')->group(function (): void {
        Route::post('/', [ProfileTwoFactorController::class, 'begin'])->name('profile.two-factor.begin');
        Route::post('/confirmar', [ProfileTwoFactorController::class, 'confirm'])->name('profile.two-factor.confirm');
        Route::post('/codigos', [ProfileTwoFactorController::class, 'recoveryCodes'])->name('profile.two-factor.recovery-codes');
        Route::post('/app', [ProfileTwoFactorController::class, 'beginAppChange'])->name('profile.two-factor.app.begin');
        Route::post('/app/confirmar', [ProfileTwoFactorController::class, 'confirmAppChange'])->name('profile.two-factor.app.confirm');
        Route::delete('/', [ProfileTwoFactorController::class, 'disable'])->name('profile.two-factor.disable');
    });
    // Which optional mail the person receives. Open to accounts that must verify their email
    // (it is where they stop a mail); the service decides which categories are editable
    Route::put('/perfil/correos', [MailPreferencesController::class, 'update'])
        ->middleware('throttle:30,1,profile-mail-preferences')
        ->name('profile.mail-preferences.update');
    // Pause every optional mail for a while, or resume. Same middleware and limit as the choices
    Route::put('/perfil/correos/pausa', [MailPauseController::class, 'update'])
        ->middleware('throttle:30,1,profile-mail-preferences')
        ->name('profile.mail-pause.update');
    Route::delete('/perfil/correos/pausa', [MailPauseController::class, 'destroy'])
        ->middleware('throttle:30,1,profile-mail-preferences')
        ->name('profile.mail-pause.destroy');
    // «Volver a recibir» a thing muted from a mail footer (core#334). Same middleware and limit
    Route::delete('/perfil/correos/silenciados', [MutedMailSubjectsController::class, 'destroy'])
        ->middleware('throttle:30,1,profile-mail-preferences')
        ->name('profile.mail-subject-mutes.destroy');
    // Personal calendar address: issued (or regenerated) and revoked from the profile
    Route::post('/perfil/calendario', [CalendarFeedTokenController::class, 'store'])
        ->middleware('throttle:6,1,calendar-feed-token')
        ->name('profile.calendar-feed.store');
    Route::delete('/perfil/calendario', [CalendarFeedTokenController::class, 'destroy'])
        ->middleware('throttle:6,1,calendar-feed-token')
        ->name('profile.calendar-feed.destroy');

    // A browser asks to listen to a private real-time channel (core#493); 404 without real time.
    // Every page with the bell subscribes once, and again after each reconnection: its own limit
    Route::post('/broadcasting/auth', RealtimeAuthController::class)
        ->middleware('throttle:60,1,realtime-auth')
        ->name('realtime.auth');

    // Images of the web rich editor (core#573); who may upload is decided by EditorImageUploadAuthorizerInterface
    Route::post('/editor/imagenes/articulos', EditorImageUploadController::class)
        ->middleware('throttle:editor-image-upload')
        ->name('editor-images.articles.store');

    // Proposals of events and articles from the web (core#579). Who may propose is checked by the
    // form requests and the service (403); someone else's proposal answers 404. Writes share a limit
    Route::prefix('/propuestas')->where(['id' => '[0-9a-fA-F-]{36}', 'type' => 'eventos|articulos'])->group(function (): void {
        Route::get('/eventos/nuevo', [EventProposalController::class, 'create'])->name('proposals.events.create');
        Route::get('/eventos/{id}/editar', [EventProposalController::class, 'edit'])->name('proposals.events.edit');
        Route::get('/articulos/nuevo', [ArticleProposalController::class, 'create'])->name('proposals.articles.create');
        Route::get('/articulos/{id}/editar', [ArticleProposalController::class, 'edit'])->name('proposals.articles.edit');

        Route::middleware('throttle:proposals')->group(function (): void {
            Route::post('/eventos', [EventProposalController::class, 'store'])->name('proposals.events.store');
            Route::put('/eventos/{id}', [EventProposalController::class, 'update'])->name('proposals.events.update');
            Route::post('/articulos', [ArticleProposalController::class, 'store'])->name('proposals.articles.store');
            Route::put('/articulos/{id}', [ArticleProposalController::class, 'update'])->name('proposals.articles.update');

            Route::post('/{type}/{id}/enviar', [ProposalActionController::class, 'submit'])->name('proposals.submit');
            Route::post('/{type}/{id}/retirar', [ProposalActionController::class, 'withdraw'])->name('proposals.withdraw');
            Route::delete('/{type}/{id}', [ProposalActionController::class, 'destroy'])->name('proposals.destroy');
        });
    });

    // Notification centre (core#491). The bell asks for the counter every minute: its own limit
    Route::get('/avisos', [SiteNotificationsController::class, 'index'])->name('site-notifications.index');
    Route::get('/avisos/contador', [SiteNotificationsController::class, 'count'])
        ->middleware('throttle:120,1,site-notifications')
        ->name('site-notifications.count');
    Route::get('/avisos/recientes', [SiteNotificationsController::class, 'recent'])
        ->middleware('throttle:120,1,site-notifications')
        ->name('site-notifications.recent');
    // The bell opened (core#800): seen, not read. Exempt from «solo mirar», the safety net undoes it
    Route::post('/avisos/vistos', [SiteNotificationsController::class, 'seen'])
        ->middleware('throttle:60,1,site-notifications-seen')
        ->name('site-notifications.seen');
    Route::post('/avisos/leidos', [SiteNotificationsController::class, 'markAllRead'])
        ->middleware('throttle:30,1,site-notifications-read')
        ->name('site-notifications.read-all');
    // One notice from its menu (core#670): read, unread again, or hidden for the person
    Route::middleware('throttle:60,1,site-notifications-row')->whereUuid('notification')->group(function (): void {
        Route::post('/avisos/{notification}/leido', [SiteNotificationsController::class, 'markRead'])
            ->name('site-notifications.read');
        Route::post('/avisos/{notification}/no-leido', [SiteNotificationsController::class, 'markUnread'])
            ->name('site-notifications.unread');
        Route::post('/avisos/{notification}/ocultar', [SiteNotificationsController::class, 'hide'])
            ->name('site-notifications.hide');
    });
    // Opening a notice marks it read, then goes where it leads
    Route::get('/avisos/{notification}', [SiteNotificationsController::class, 'open'])
        ->whereUuid('notification')
        ->name('site-notifications.open');

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
