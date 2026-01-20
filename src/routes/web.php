<?php

declare(strict_types=1);

use App\Http\Controllers\AboutController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CalendarPageController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/nosotros', AboutController::class)->name('about');
Route::post('/contacto', ContactController::class)->middleware('throttle:contact')->name('contact.store');
Route::get('/calendario', CalendarPageController::class)->name('calendar');

Route::get('/eventos', [EventController::class, 'index'])->name('events.index');
Route::get('/eventos/{slug}', [EventController::class, 'show'])->name('events.show');

Route::get('/articulos', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articulos/{slug}', [ArticleController::class, 'show'])->name('articles.show');

Route::get('/galeria', [GalleryController::class, 'index'])->name('galleries.index');
Route::get('/galeria/{slug}', [GalleryController::class, 'show'])->name('galleries.show');

Route::get('/buscar', SearchController::class)->name('search');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
