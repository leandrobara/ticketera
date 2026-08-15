<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>
            @isset($show)
                {{ $show->title }} | {{ config('app.name', 'Ticketera') }}
            @elseif(isset($pageTitle))
                {{ $pageTitle }} | {{ config('app.name', 'Ticketera') }}
            @else
                {{ config('app.name', 'Ticketera') }}
            @endisset
        </title>
        <script src="https://www.mercadopago.com/v2/security.js" view="checkout"></script>
        @vite(['resources/js/site/app.js'])
        <script>
            window.ticketeraSite = {
                page: @json($page ?? 'show'),
                showId: @json($show->id ?? null),
                seasonId: @json($season->id ?? null),
                checkoutStatus: @json($checkoutStatus ?? null),
                commentToken: @json($commentToken ?? null),
                pageTitle: @json($pageTitle ?? null),
            };
        </script>
    </head>
    <body>
        <div id="site-app"></div>
    </body>
</html>
