<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ url()->previous(route('dashboard')) }}"
               class="text-gray-500 hover:text-gray-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $place['name'] }}</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Place details --}}
            <div class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100">
                <dl>
                    <div class="px-6 py-3 flex gap-4">
                        <dt class="w-40 shrink-0 text-sm font-medium text-gray-500">{{ __('Address') }}</dt>
                        <dd class="text-sm text-gray-900">
                            <a href="geo:0,0?q={{ urlencode($place['address']) }}"
                               data-maps-url="maps://?q={{ urlencode($place['address']) }}"
                               class="decoration-dotted"
                               onclick="if(/iPad|iPhone|iPod|Macintosh/.test(navigator.userAgent)){window.location=this.dataset.mapsUrl;return false;}">{{ $place['address'] }}</a>
                        </dd>
                    </div>
                    <div class="px-6 py-3 flex gap-4">
                        <dt class="w-40 shrink-0 text-sm font-medium text-gray-500">{{ __('Category') }}</dt>
                        <dd class="text-sm text-gray-900">{{ $place['friendly_category'] ?? __('Uncategorized') }}</dd>
                    </div>
                    <div class="px-6 py-3 flex gap-4">
                        <dt class="w-40 shrink-0 text-sm font-medium text-gray-500">{{ __('Place Type') }}</dt>
                        <dd class="text-sm text-gray-900 font-mono">
                            <button type="button"
                                    class="hover:underline"
                                    x-data=""
                                    x-on:click="$dispatch('open-modal', 'assign-category')">{{ $place['api_category'] }}</button>
                        </dd>
                    </div>
                </dl>
            </div>

            <x-modal name="assign-category" :show="$errors->hasAny()" maxWidth="sm" focusable>
                <form method="POST" action="{{ route('places.category.update', $placeId) }}" class="p-6">
                    @csrf

                    <h2 class="text-lg font-medium text-gray-900">{{ __('Assign Category') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 font-mono">{{ $place['api_category'] }}</p>

                    <div class="mt-4">
                        <x-input-label for="friendly_name" :value="__('Category')" />
                        <select id="friendly_name" name="friendly_name"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach ($friendlyNames as $name)
                                <option value="{{ $name }}" @selected($name === ($currentFriendlyName ?? old('friendly_name')))>{{ $name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('friendly_name')" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button x-on:click="$dispatch('close-modal', 'assign-category')">
                            {{ __('Cancel') }}
                        </x-secondary-button>
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </div>
                </form>
            </x-modal>

            {{-- Card recommendations --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">{{ __('Cards') }}</h3>
                </div>
                @if ($cards->isEmpty())
                    <p class="px-6 py-4 text-sm text-gray-500">{{ __('No cards configured.') }}</p>
                @else
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead>
                            <tr>
                                <th class="px-6 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">{{ __('Card') }}</th>
                                <th class="px-6 py-2 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">{{ __('Cash Back') }}</th>
                                <th class="px-6 py-2 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">{{ __('Foreign Fee') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($cards as $card)
                                @php
                                    $disqualified = $place['is_outside_us'] && $card->foreign_transaction_fee > 0;
                                @endphp
                                <tr class="{{ $disqualified ? 'opacity-40' : '' }}">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            @if ($card->image_data)
                                                <img src="{{ route('cards.image', $card) }}"
                                                     alt="{{ $card->name }}"
                                                     class="rounded"
                                                     style="max-height: 36px; width: auto;">
                                            @endif
                                            <span class="text-sm font-medium text-gray-900">{{ $card->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-right text-sm text-gray-900">
                                        {{ $card->category_percentage }}%
                                    </td>
                                    <td class="px-6 py-3 text-right text-sm">
                                        @if ($card->foreign_transaction_fee > 0)
                                            <span class="{{ $place['is_outside_us'] ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                                +{{ $card->foreign_transaction_fee }}%
                                            </span>
                                        @else
                                            <span class="text-gray-300">&mdash;</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
