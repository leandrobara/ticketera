<?php

use Illuminate\Support\Facades\Route;

// ADMIN API ROUTES
use App\Http\Controllers\Api\Admin\ShowController;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\BuyerController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\VenueController;
use App\Http\Controllers\Api\Admin\ImageController;
use App\Http\Controllers\Api\Admin\PersonController;
use App\Http\Controllers\Api\Admin\TicketController;
use App\Http\Controllers\Api\Admin\SeasonController;
use App\Http\Controllers\Api\Admin\CommentController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\ShowLinkController;
use App\Http\Controllers\Api\Admin\OrderItemController;
use App\Http\Controllers\Api\Admin\ShowCreditController;
use App\Http\Controllers\Api\OrderItemPricingController;
use App\Http\Controllers\Api\Admin\PresentationController;
use App\Http\Controllers\Api\Admin\NewsletterSubscriberController;
use App\Http\Controllers\Api\Admin\ShowPerformanceHistoryController;
use App\Http\Controllers\Api\Admin\PresentationTicketTypeController;
use App\Http\Controllers\Api\Notifications\MercadoPagoNotificationController;
use App\Http\Controllers\Api\Checkout\OrderController as CheckoutOrderController;

// SITE API ROUTES
use App\Http\Controllers\Api\Site\NewsletterSubscriptionController;
use App\Http\Controllers\Api\Site\ShowController as SiteShowController;
use App\Http\Controllers\Api\Site\CommentController as SiteCommentController;
use App\Http\Controllers\Api\Site\PresentationController as SitePresentationController;


Route::post('/checkout/create-order', [CheckoutOrderController::class, 'create']);
Route::post('/checkout/price-preview', [OrderItemPricingController::class, 'calculateAmounts']);
Route::post('/notifications/mercado-pago', [MercadoPagoNotificationController::class, 'handleNotification']);

Route::post(
    '/site/shows/{show}/comment-requests',
    [SiteCommentController::class, 'requestToken']
)->middleware('throttle:comment-request');
Route::get('/site/comment-tokens/{token}', [SiteCommentController::class, 'validateToken']);
Route::post('/site/comment-tokens/{token}/comments', [SiteCommentController::class, 'create']);
Route::post('/site/newsletter-subscriptions', [NewsletterSubscriptionController::class, 'create']);

// Season *pasar el controlador a Season en lugar de Show
Route::get('/site/seasons/{season}', [SiteShowController::class, 'show']);

// Presentation - ticket types
Route::get('/shows/{season}/presentations', [SitePresentationController::class, 'list']);

// Comments
Route::get('/shows/{season}/comments', [SiteCommentController::class, 'list']);

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

        // images
        Route::get('/images', [ImageController::class, 'list']);
        Route::post('/images', [ImageController::class, 'create']);
        Route::get('/images/{image}', [ImageController::class, 'show']);
        Route::post('/images/{image}', [ImageController::class, 'update']);
        Route::delete('/images/{image}', [ImageController::class, 'delete']);

        // venues
        Route::get('/venues', [VenueController::class, 'list']);
        Route::post('/venues', [VenueController::class, 'create']);
        Route::get('/venues/{venue}', [VenueController::class, 'show']);
        Route::put('/venues/{venue}', [VenueController::class, 'update']);
        Route::delete('/venues/{venue}', [VenueController::class, 'delete']);

        // people
        Route::get('/people', [PersonController::class, 'list']);
        Route::get('/people/candidates', [PersonController::class, 'candidates']);
        Route::post('/people', [PersonController::class, 'create']);
        Route::get('/people/{person}', [PersonController::class, 'show']);
        Route::put('/people/{person}', [PersonController::class, 'update']);
        Route::delete('/people/{person}', [PersonController::class, 'delete']);

        // show credits
        Route::get('/show-credits', [ShowCreditController::class, 'list']);
        Route::post('/show-credits', [ShowCreditController::class, 'create']);
        Route::get('/show-credits/{showCredit}', [ShowCreditController::class, 'show']);
        Route::put('/show-credits/{showCredit}', [ShowCreditController::class, 'update']);
        Route::post('/show-credits/{showCredit}', [ShowCreditController::class, 'update']);
        Route::delete('/show-credits/{showCredit}', [ShowCreditController::class, 'delete']);

        // show performance history
        Route::get('/show-performance-histories', [ShowPerformanceHistoryController::class, 'list']);
        Route::post('/show-performance-histories', [ShowPerformanceHistoryController::class, 'create']);
        Route::get('/show-performance-histories/{showPerformanceHistory}', [ShowPerformanceHistoryController::class, 'show']);
        Route::put('/show-performance-histories/{showPerformanceHistory}', [ShowPerformanceHistoryController::class, 'update']);
        Route::delete('/show-performance-histories/{showPerformanceHistory}', [ShowPerformanceHistoryController::class, 'delete']);

        // show links
        Route::get('/show-links', [ShowLinkController::class, 'list']);
        Route::post('/show-links', [ShowLinkController::class, 'create']);
        Route::get('/show-links/{showLink}', [ShowLinkController::class, 'show']);
        Route::put('/show-links/{showLink}', [ShowLinkController::class, 'update']);
        Route::delete('/show-links/{showLink}', [ShowLinkController::class, 'delete']);

        // presentations
        Route::get('/presentations', [PresentationController::class, 'list']);
        Route::post('/presentations', [PresentationController::class, 'create']);
        Route::get('/presentations/{presentation}', [PresentationController::class, 'show']);
        Route::put('/presentations/{presentation}', [PresentationController::class, 'update']);
        Route::delete('/presentations/{presentation}', [PresentationController::class, 'delete']);

        // seasons
        Route::get('/seasons', [SeasonController::class, 'list']);
        Route::post('/seasons', [SeasonController::class, 'create']);
        Route::get('/seasons/{season}', [SeasonController::class, 'show']);
        Route::put('/seasons/{season}', [SeasonController::class, 'update']);
        Route::delete('/seasons/{season}', [SeasonController::class, 'delete']);

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

        // newsletter subscribers
        Route::get('/newsletter-subscribers', [NewsletterSubscriberController::class, 'list']);
        Route::delete('/newsletter-subscribers/{newsletterSubscriber}', [NewsletterSubscriberController::class, 'delete']);

        // comments
        Route::get('/comments', [CommentController::class, 'list']);
        Route::put('/comments/{comment}', [CommentController::class, 'update']);
        Route::delete('/comments/{comment}', [CommentController::class, 'delete']);

        // orders
        Route::get('/orders', [OrderController::class, 'list']);
        Route::post('/orders', [OrderController::class, 'createManual']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::put('/orders/{order}', [OrderController::class, 'update']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
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
        Route::post('/tickets/{ticket}/cancel', [TicketController::class, 'cancel']);
        Route::post('/tickets/{ticket}/mark-used', [TicketController::class, 'markUsed']);
        Route::delete('/tickets/{ticket}', [TicketController::class, 'delete']);

        // payments
        Route::get('/payments', [PaymentController::class, 'list']);
        Route::post('/payments', [PaymentController::class, 'create']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
        Route::put('/payments/{payment}', [PaymentController::class, 'update']);
        Route::delete('/payments/{payment}', [PaymentController::class, 'delete']);
    });
});
