<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Percentages') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('percentages.update') }}">
                @csrf

                <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Category') }}</th>
                                @foreach ($cards as $card)
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        @if ($card->image_data)
                                            <img src="{{ route('cards.image', $card) }}"
                                                 alt="{{ $card->name }}"
                                                 class="mx-auto mb-1 rounded"
                                                 style="max-height: 48px; width: auto; height: auto;">
                                        @endif
                                        {{ $card->name }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($categories as $category)
                                <tr>
                                    <td class="px-6 py-2 text-sm text-gray-700 whitespace-nowrap">{{ $category }}</td>
                                    @foreach ($cards as $card)
                                        @php $hasFtf = !isset($card->foreign_transaction_fee) || $card->foreign_transaction_fee !== 0; @endphp
                                        <td class="{{ $hasFtf ? 'bg-red-50' : '' }} px-4 py-2 text-center">
                                            <input
                                                type="number"
                                                name="percentages[{{ $category }}][{{ $card->id }}]"
                                                value="{{ $percentages[$category][$card->id]->percentage ?? 0 }}"
                                                min="0"
                                                max="100"
                                                step=".001"
                                                class="w-16 text-center border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                                inputmode="decimal"
                                            />
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                </div>

            </form>
        </div>
    </div>
</x-app-layout>
