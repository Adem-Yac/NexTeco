<!DOCTYPE html>
<html lang="fr" class="h-full" x-data="{}" x-init="document.documentElement.classList.add(window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : ''); window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => { document.documentElement.classList.toggle('dark', e.matches); });">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexTeco - Bienvenue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#6366f1',
                            dark: '#818cf8',
                        },
                    },
                },
            },
        }
    </script>
    <style>
        html {
            font-family: 'Inter', 'Segoe UI', 'Roboto', Arial, sans-serif;
        }
    </style>
</head>
<body class="h-full min-h-screen bg-white dark:bg-gray-900 flex items-center justify-center transition-colors duration-300">
    <div class="w-full max-w-4xl mx-auto px-6 py-16 bg-white dark:bg-gray-800 shadow-xl rounded-2xl border border-gray-200 dark:border-gray-700 flex flex-col items-center transition-colors duration-300">
        <div class="flex flex-col items-center flex-1 w-full">
            <h1 class="text-5xl md:text-6xl font-extrabold mb-4">
                <span class="text-black dark:text-white">NexTeco</span>
            </h1>
            <p class="mt-2 text-gray-600 dark:text-gray-300 text-2xl text-center max-w-2xl">
                L'application dédiée a la gestion du personnel .
            </p>
            <div class="flex flex-wrap justify-center gap-6 w-full mt-10">
                <a href="{{ url('/grh/login') }}"
                   class="flex items-center justify-center px-8 py-5 text-2xl font-semibold rounded-lg bg-primary text-white shadow hover:bg-indigo-700 dark:hover:bg-primary-dark transition w-64">
                    Grh
                    <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
                <a href="{{ url('/employe/login') }}"
                   class="flex items-center justify-center px-8 py-5 text-2xl font-semibold rounded-lg bg-primary text-white shadow hover:bg-indigo-700 dark:hover:bg-primary-dark transition w-64">
                    Employé
                    <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            <p class="text-sm text-gray-800 dark:text-gray-300 mt-12 text-center">
                &copy; {{ date('Y') }} NexTeco — Tous droits réservés.
            </p>
        </div>
    </div>
</body>
</html>
