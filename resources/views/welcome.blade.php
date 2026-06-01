<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Ticketera') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-zinc-50 text-zinc-950">
        <main class="mx-auto flex min-h-screen w-full max-w-5xl flex-col justify-center px-6 py-16">
            <p class="text-sm font-semibold uppercase tracking-normal text-emerald-700">
                Admin MVP
            </p>
            <h1 class="mt-3 text-4xl font-semibold leading-tight sm:text-5xl">
                Ticketera
            </h1>
            <p class="mt-5 max-w-2xl text-lg leading-8 text-zinc-600">
                Infrastructure for independent cultural projects. The first backend cut is ready for admin shows.
            </p>
            <div class="mt-8 grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg border border-zinc-200 bg-white p-4">
                    <p class="text-sm font-medium text-zinc-500">Step 1</p>
                    <p class="mt-1 font-semibold">Admin shows</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-4">
                    <p class="text-sm font-medium text-zinc-500">Next</p>
                    <p class="mt-1 font-semibold">Performances</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-4">
                    <p class="text-sm font-medium text-zinc-500">Then</p>
                    <p class="mt-1 font-semibold">Public show page</p>
                </div>
            </div>
        </main>
    </body>
</html>
