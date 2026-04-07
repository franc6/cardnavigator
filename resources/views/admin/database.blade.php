<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Database Tools') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                    <p class="font-medium">{{ session('status') }}</p>
                    @if (session('output'))
                        <pre class="mt-2 text-xs whitespace-pre-wrap">{{ session('output') }}</pre>
                    @endif
                </div>
            @endif

            {{-- Migrations --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('Migrations') }}</h3>
                <p class="text-sm text-gray-600 mb-4">{{ __('Runs all pending migrations (equivalent to php artisan migrate).') }}</p>
                <form method="POST" action="{{ route('admin.database.migrate') }}"
                      onsubmit="return confirm('{{ __('Run all pending migrations?') }}')">
                    @csrf
                    <x-primary-button>{{ __('Run Migrations') }}</x-primary-button>
                </form>
            </div>

            {{-- Example Data --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('Example Data') }}</h3>
                <p class="text-sm text-gray-600 mb-3">{{ __('These seeders load fictional example data for demonstration and development. Card names are invented. Percentages are set to implausibly high values — many exceed 100% — so they cannot be mistaken for real rates.') }}</p>
                <p class="text-sm text-gray-600 mb-4">{{ __('Load this data to explore the interface, then delete it and add your own cards, categories, and percentages.') }}</p>
                <div class="space-y-2">
                    @foreach ($seeders as $seeder)
                        <form method="POST" action="{{ route('admin.database.seed') }}" class="inline-block"
                              onsubmit="return confirm('{{ __('Load :label?', ['label' => $seeder['label']]) }}')">
                            @csrf
                            <input type="hidden" name="seeder" value="{{ $seeder['class'] }}">
                            <button type="submit"
                                    class="px-3 py-1.5 text-sm bg-indigo-50 text-indigo-700 border border-indigo-200 rounded hover:bg-indigo-100">
                                {{ $seeder['label'] }}
                            </button>
                        </form>
                    @endforeach
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-500">{{ __('To add real data: use :cards to manage your credit cards and :percentages to configure cashback rates.', ['cards' => '/cards', 'percentages' => '/percentages']) }}</p>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
