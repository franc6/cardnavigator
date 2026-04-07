<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Cards') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Card list --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Image') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Color') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Name') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Foreign Transaction Fee') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Preference') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($cards as $card)
                            <tr>
                                <form method="POST" action="{{ route('cards.update', $card) }}"
                                      id="card-form-{{ $card->id }}"
                                      enctype="multipart/form-data" class="contents">
                                    @csrf
                                    @method('PATCH')

                                    {{-- Image cell --}}
                                    <td class="px-4 py-3 align-middle">
                                        <label class="block cursor-pointer"
                                               title="{{ __('Tap to choose a new image') }}"
                                               aria-label="{{ __('Tap to choose a new image') }}">
                                            @if ($card->image_data)
                                                <img src="{{ route('cards.image', $card) }}"
                                                     alt=""
                                                     class="h-14 w-20 object-contain rounded border border-gray-200 bg-white">
                                            @else
                                                <span class="flex items-center justify-center h-14 w-20 rounded border border-dashed border-gray-300 text-xs text-gray-400">
                                                    {{ __('No image') }}
                                                </span>
                                            @endif
                                            <input type="file" name="image_file" accept="image/*" class="sr-only">
                                        </label>
                                    </td>

                                    {{-- Color cell --}}
                                    <td class="px-4 py-3 align-middle">
                                        <input type="color" name="color"
                                               value="{{ $card->color ?? '#6B7280' }}"
                                               title="{{ __('Tap to change the color') }}"
                                               aria-label="{{ __('Tap to change the color') }}"
                                               class="h-9 w-9 cursor-pointer rounded-full border border-gray-300 bg-white p-0 appearance-none [&::-webkit-color-swatch]:rounded-full [&::-webkit-color-swatch]:border-none [&::-webkit-color-swatch-wrapper]:p-0 [&::-moz-color-swatch]:rounded-full [&::-moz-color-swatch]:border-none">
                                    </td>

                                    <td class="px-4 py-3 align-middle">
                                        <x-text-input name="name" type="text" inputmode="text" autocapitalize="words" class="w-full" value="{{ $card->name }}" required />
                                    </td>
                                    <td class="px-4 py-3 align-middle">
                                        <x-text-input name="foreign_transaction_fee" type="number" inputmode="decimal" step=".001" min="0" max="100" class="w-20" value="{{ $card->foreign_transaction_fee }}" required />
                                    </td>
                                    <td class="px-4 py-3 align-middle">
                                        <x-text-input name="preference" type="number" inputmode="numeric" min="0" max="255" class="w-20" value="{{ $card->preference }}" required />
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap align-middle">
                                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                                </form>
                                        <form method="POST" action="{{ route('cards.destroy', $card) }}" class="inline" onsubmit="return confirm('{{ __('Delete this card?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <x-danger-button>{{ __('Delete') }}</x-danger-button>
                                        </form>
                                    </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <x-primary-button onclick="document.getElementById('add-card-dialog').showModal()">
                    {{ __('Add Card') }}
                </x-primary-button>
            </div>

            <dialog id="add-card-dialog" class="rounded-lg shadow-xl p-6 w-full max-w-sm backdrop:bg-black/40">
                <h3 class="text-base font-semibold text-gray-800 mb-4">{{ __('Add Card') }}</h3>
                <form method="POST" action="{{ route('cards.store') }}"
                      id="card-form-add"
                      enctype="multipart/form-data"
                      class="flex flex-col gap-4">
                    @csrf
                    <div>
                        <x-input-label for="new_name" :value="__('Name')" />
                        <x-text-input id="new_name" name="name" type="text" inputmode="text" autocapitalize="words" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="new_ftf" :value="__('Foreign Transaction Fee')" />
                        <x-text-input id="new_ftf" name="foreign_transaction_fee" type="number" inputmode="decimal" step=".001" min="0" max="100" class="mt-1 block w-full" value="0" required />
                        <x-input-error :messages="$errors->get('foreign_transaction_fee')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="new_pref" :value="__('Preference')" />
                        <x-text-input id="new_pref" name="preference" type="number" inputmode="numeric" min="0" max="255" class="mt-1 block w-full" value="{{ $cards->count() }}" required />
                        <x-input-error :messages="$errors->get('preference')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="new_image" :value="__('Image')" />
                        <input id="new_image" type="file" name="image_file" accept="image/*"
                               class="mt-1 text-sm text-gray-600">
                        <x-input-error :messages="$errors->get('image_file')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="new_color" :value="__('Color')" />
                        <input id="new_color" type="color" name="color"
                               value="{{ old('color', '#6B7280') }}"
                               class="mt-1 h-10 w-16 cursor-pointer rounded border border-gray-300 bg-white p-0 appearance-none">
                        <x-input-error :messages="$errors->get('color')" class="mt-1" />
                    </div>
                    <div class="flex justify-end gap-3 mt-2">
                        <button type="button" onclick="document.getElementById('add-card-dialog').close()"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                            {{ __('Cancel') }}
                        </button>
                        <x-primary-button>{{ __('Add') }}</x-primary-button>
                    </div>
                </form>
            </dialog>

        </div>
    </div>
</x-app-layout>
