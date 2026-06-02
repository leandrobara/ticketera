<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\BuyerController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\OrderItemController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\ShowController;
use App\Http\Controllers\Api\Admin\TicketController;
use App\Http\Controllers\Api\Admin\VenueController;
use App\Http\Controllers\Api\Admin\PresentationController;
use App\Http\Controllers\Api\Admin\PresentationTicketTypeController;


// ADMIN API ROUTES
Route::group(['prefix' => 'admin'], function () {

    // login
    Route::middleware('throttle:admin-login')->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login']);
    });

    Route::middleware('admin.token')->group(function () {
        // auth
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // shows
        Route::get('/shows', [ShowController::class, 'list']);
        Route::post('/shows', [ShowController::class, 'create']);
        Route::get('/shows/{show}', [ShowController::class, 'show']);
        Route::put('/shows/{show}', [ShowController::class, 'update']);
        Route::delete('/shows/{show}', [ShowController::class, 'delete']);

        // venues
        Route::get('/venues', [VenueController::class, 'list']);
        Route::post('/venues', [VenueController::class, 'create']);
        Route::get('/venues/{venue}', [VenueController::class, 'show']);
        Route::put('/venues/{venue}', [VenueController::class, 'update']);
        Route::delete('/venues/{venue}', [VenueController::class, 'delete']);

        // presentations
        Route::get('/presentations', [PresentationController::class, 'list']);
        Route::post('/presentations', [PresentationController::class, 'create']);
        Route::get('/presentations/{presentation}', [PresentationController::class, 'show']);
        Route::put('/presentations/{presentation}', [PresentationController::class, 'update']);
        Route::delete('/presentations/{presentation}', [PresentationController::class, 'delete']);

        // presentation ticket types
        Route::get('/presentation-ticket-types', [PresentationTicketTypeController::class, 'list']);
        Route::post('/presentation-ticket-types', [PresentationTicketTypeController::class, 'create']);
        Route::get('/presentation-ticket-types/{presentationTicketType}', [PresentationTicketTypeController::class, 'show']);
        Route::put('/presentation-ticket-types/{presentationTicketType}', [PresentationTicketTypeController::class, 'update']);
        Route::delete('/presentation-ticket-types/{presentationTicketType}', [PresentationTicketTypeController::class, 'delete']);

        // buyers
        Route::get('/buyers', [BuyerController::class, 'list']);
        Route::post('/buyers', [BuyerController::class, 'create']);
        Route::get('/buyers/{buyer}', [BuyerController::class, 'show']);
        Route::put('/buyers/{buyer}', [BuyerController::class, 'update']);
        Route::delete('/buyers/{buyer}', [BuyerController::class, 'delete']);

        // orders
        Route::get('/orders', [OrderController::class, 'list']);
        Route::post('/orders', [OrderController::class, 'create']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::put('/orders/{order}', [OrderController::class, 'update']);
        Route::delete('/orders/{order}', [OrderController::class, 'delete']);

        // order items
        Route::get('/order-items', [OrderItemController::class, 'list']);
        Route::post('/order-items', [OrderItemController::class, 'create']);
        Route::get('/order-items/{orderItem}', [OrderItemController::class, 'show']);
        Route::put('/order-items/{orderItem}', [OrderItemController::class, 'update']);
        Route::delete('/order-items/{orderItem}', [OrderItemController::class, 'delete']);

        // tickets
        Route::get('/tickets', [TicketController::class, 'list']);
        Route::post('/tickets', [TicketController::class, 'create']);
        Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
        Route::put('/tickets/{ticket}', [TicketController::class, 'update']);
        Route::delete('/tickets/{ticket}', [TicketController::class, 'delete']);

        // payments
        Route::get('/payments', [PaymentController::class, 'list']);
        Route::post('/payments', [PaymentController::class, 'create']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
        Route::put('/payments/{payment}', [PaymentController::class, 'update']);
        Route::delete('/payments/{payment}', [PaymentController::class, 'delete']);
    });
});
