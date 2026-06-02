<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin | {{ config('app.name', 'Ticketera') }}</title>
        @vite(['resources/js/admin/app.js'])
    </head>
    <body>
        <div id="admin-app"></div>
    </body>
</html>
