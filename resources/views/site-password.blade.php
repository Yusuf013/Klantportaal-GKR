<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toegang vereist</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow">
        <h1 class="text-2xl font-bold text-gray-900">Toegang vereist</h1>

        <p class="mt-2 text-sm text-gray-600">
            Vul het kantoorwachtwoord in om het klantportaal te bekijken.
        </p>

        <form method="POST" action="{{ route('site-password.store') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">
                    Wachtwoord
                </label>

                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autofocus
                    class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >

                @error('password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-gray-900 px-4 py-2 font-semibold text-white hover:bg-gray-800"
            >
                Doorgaan
            </button>
        </form>
    </div>
</body>
</html>