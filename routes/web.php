<?php

use App\Http\Controllers\Admin\ShowController;
use App\Http\Controllers\Site\CheckoutController;
use App\Http\Controllers\Site\ShowController as SiteShowController;
use App\Http\Controllers\Site\StaticPageController;
use Illuminate\Support\Facades\Route;

// FRONT DINAMIC PAGES
Route::get('/shows/{season}/{slug?}', [SiteShowController::class, 'index']);
Route::get('/checkout/{status}', [CheckoutController::class, 'result'])->whereIn('status', ['success', 'failure', 'pending']);

// FRONT STATIC PAGES
Route::redirect('/', '/venta-de-entradas');
Route::get('/contacto', [StaticPageController::class, 'contact']);
Route::get('/sobre-nosotros', [StaticPageController::class, 'aboutUs']);
Route::get('/politica-cookies', [StaticPageController::class, 'cookies']);
Route::get('/medios-pago', [StaticPageController::class, 'paymentMethods']);
Route::get('/terminos-condiciones', [StaticPageController::class, 'terms']);
Route::get('/politica-privacidad', [StaticPageController::class, 'privacy']);
Route::get('/venta-de-entradas', [StaticPageController::class, 'homeLanding']);
Route::get('/publica-tu-obra', [StaticPageController::class, 'publishYourShow']);
Route::get('/gestionar-tickets', [StaticPageController::class, 'manageMyTickets']);
Route::get('/preguntas-frecuentes', [StaticPageController::class, 'frequentlyAskedQuestions']);
Route::get('/comentarios/{token}', [StaticPageController::class, 'comment'])->where('token', '[A-Za-z0-9]{64}');

// ADMIN
Route::redirect('/admin', '/admin/events');
Route::get('/admin/login', [ShowController::class, 'index']);
Route::get('/admin/shows', [ShowController::class, 'index']);
Route::get('/admin/venues', [ShowController::class, 'index']);
Route::get('/admin/people', [ShowController::class, 'index']);
Route::get('/admin/orders', [ShowController::class, 'index']);
Route::get('/admin/users', [ShowController::class, 'index']);
Route::get('/admin/buyers', [ShowController::class, 'index']);
Route::get('/admin/presentations', [ShowController::class, 'index']);
Route::get('/admin/seasons', [ShowController::class, 'index']);
Route::get('/admin/newsletter-subscribers', [ShowController::class, 'index']);
Route::get('/admin/comments', [ShowController::class, 'index']);
Route::get('/admin/season-ticket-types', [ShowController::class, 'index']);
Route::get('/admin/organizations', [ShowController::class, 'index']);
Route::get('/admin/account', [ShowController::class, 'index']);
Route::get('/admin/organizations/new', [ShowController::class, 'index']);
Route::get('/admin/organizations/{organization}/edit', [ShowController::class, 'index'])->whereNumber('organization');
Route::get('/admin/team', [ShowController::class, 'index']);
Route::get('/admin/team/{member}/edit', [ShowController::class, 'index'])->whereNumber('member');
Route::get('/admin/invitations/new', [ShowController::class, 'index']);
Route::get('/admin/invitations/{invitation}/edit', [ShowController::class, 'index'])->whereNumber('invitation');
Route::get('/admin/door', [ShowController::class, 'index']);
Route::get('/admin/events/new', [ShowController::class, 'index']);
Route::get('/admin/events', [ShowController::class, 'index']);
Route::get('/admin/events/{season}', [ShowController::class, 'index'])->whereNumber('season');
Route::get('/admin/invitation/{token}', [ShowController::class, 'index'])->where('token', '[A-Za-z0-9]{64}');
