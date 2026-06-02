<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dashboard | {{ config('app.name', 'Ticketera') }}</title>
        @vite(['resources/css/app.css', 'resources/js/Dashboard/app.js'])
    </head>
    <body>
        <div id="dashboard-app"></div>
    </body>
</html>

