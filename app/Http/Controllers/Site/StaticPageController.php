<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;

class StaticPageController extends Controller
{
    public function home()
    {
        return view('site.app', [
            'page' => 'home',
            'pageTitle' => 'Inicio',
        ]);
    }

    public function homeLanding()
    {
        return view('site.app', [
            'page' => 'home',
            'pageTitle' => 'Venta de entradas',
        ]);
    }

    public function contact()
    {
        return view('site.app', [
            'page' => 'contact',
            'pageTitle' => 'Contacto',
        ]);
    }

    public function paymentMethods()
    {
        return view('site.app', [
            'page' => 'payment-methods',
            'pageTitle' => 'Medios de pago',
        ]);
    }

    public function publishYourShow()
    {
        return view('site.app', [
            'page' => 'publish-your-show',
            'pageTitle' => 'Publicá tu obra',
        ]);
    }

    public function frequentlyAskedQuestions()
    {
        return view('site.app', [
            'page' => 'frequently-asked-questions',
            'pageTitle' => 'Preguntas frecuentes',
        ]);
    }

    public function manageMyTickets()
    {
        return view('site.app', [
            'page' => 'manage-my-tickets',
            'pageTitle' => 'Gestionar mis tickets',
        ]);
    }

    public function aboutUs()
    {
        return view('site.app', [
            'page' => 'about-us',
            'pageTitle' => 'Sobre nosotros',
        ]);
    }

    public function terms()
    {
        return view('site.app', [
            'page' => 'terms',
            'pageTitle' => 'Términos y condiciones',
        ]);
    }

    public function privacy()
    {
        return view('site.app', [
            'page' => 'privacy',
            'pageTitle' => 'Política de privacidad',
        ]);
    }

    public function cookies()
    {
        return view('site.app', [
            'page' => 'cookies',
            'pageTitle' => 'Política de cookies',
        ]);
    }

    public function comment(string $token)
    {
        return view('site.app', [
            'page' => 'comment',
            'commentToken' => $token,
            'pageTitle' => 'Dejá tu comentario',
        ]);
    }
}
