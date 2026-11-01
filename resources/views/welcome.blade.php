<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900">
    <main class="mx-auto max-w-3xl px-6 py-20">
        <p class="mb-4 text-sm text-slate-500">Inspektorat Daerah</p>
        <h1 class="text-3xl font-semibold">{{ config('app.name') }}</h1>
        <p class="mt-4 text-slate-600">Fondasi aplikasi pengusulan dan reviu SHS/SBU.</p>
        @if (app()->environment('local'))
            <a class="mt-8 inline-flex rounded-lg bg-orange-700 px-5 py-3 font-medium text-white" href="{{ route('prototype.operator') }}">Buka prototipe Operator SKPD →</a>
        @endif
    </main>
</body>
</html>
