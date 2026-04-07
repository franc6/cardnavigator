<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('User Management') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            {{-- User List --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Users') }}</h3>
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-sm font-medium text-gray-600">
                            <th class="pb-2 pr-4">{{ __('Name') }}</th>
                            <th class="pb-2 pr-4">{{ __('Email') }}</th>
                            <th class="pb-2 pr-4">{{ __('Force Password Change') }}</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $user)
                            <tr class="text-sm text-gray-800">
                                <td class="py-3 pr-4">{{ $user->name }}</td>
                                <td class="py-3 pr-4">{{ $user->email }}</td>
                                <td class="py-3 pr-4">
                                    @if ($user->force_password_change)
                                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">{{ __('Yes') }}</span>
                                    @else
                                        <span class="text-gray-400">{{ __('No') }}</span>
                                    @endif
                                </td>
                                <td class="py-3 text-right space-x-2">
                                    <button onclick="document.getElementById('reset-dialog-{{ $user->id }}').showModal()"
                                            class="text-xs text-indigo-600 hover:text-indigo-800">
                                        {{ __('Reset Password') }}
                                    </button>

                                    @unless ($user->is(Auth::user()))
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline"
                                              onsubmit="return confirm('{{ __('Delete :name?', ['name' => $user->name]) }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:text-red-800">{{ __('Delete') }}</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>

                            {{-- Reset password dialog --}}
                            <dialog id="reset-dialog-{{ $user->id }}"
                                    class="rounded-lg shadow-xl p-6 w-full max-w-sm backdrop:bg-black/40">
                                <h4 class="text-base font-semibold text-gray-900 mb-4">
                                    {{ __('Reset Password — :name', ['name' => $user->name]) }}
                                </h4>
                                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="flex flex-col gap-4">
                                    @csrf
                                    @method('PATCH')
                                    <div>
                                        <label class="block text-sm text-gray-600 mb-1">{{ __('New Password') }}</label>
                                        <input type="password" name="password" required minlength="8"
                                               class="border border-gray-300 rounded px-3 py-2 text-sm w-full" />
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-600 mb-1">{{ __('Confirm Password') }}</label>
                                        <input type="password" name="password_confirmation" required
                                               class="border border-gray-300 rounded px-3 py-2 text-sm w-full" />
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" name="force_password_change" id="fpc-{{ $user->id }}" value="1"
                                               {{ $user->force_password_change ? 'checked' : '' }}>
                                        <label for="fpc-{{ $user->id }}" class="text-sm text-gray-600">{{ __('Force change at next login') }}</label>
                                    </div>
                                    <div class="flex justify-end gap-3 mt-2">
                                        <button type="button"
                                                onclick="document.getElementById('reset-dialog-{{ $user->id }}').close()"
                                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                                            {{ __('Cancel') }}
                                        </button>
                                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                                    </div>
                                </form>
                            </dialog>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <x-primary-button onclick="document.getElementById('add-user-dialog').showModal()">
                    {{ __('Add User') }}
                </x-primary-button>
            </div>

            <dialog id="add-user-dialog" class="rounded-lg shadow-xl p-6 w-full max-w-sm backdrop:bg-black/40">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Add User') }}</h3>
                <form method="POST" action="{{ route('admin.users.store') }}" class="flex flex-col gap-4">
                    @csrf
                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" name="name" type="text" inputmode="text" autocapitalize="words" class="mt-1 block w-full" :value="old('name')" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" name="email" type="email" inputmode="email" class="mt-1 block w-full" :value="old('email')" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="new_password" :value="__('Password')" />
                        <x-text-input id="new_password" name="password" type="password" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="new_password_confirmation" :value="__('Confirm Password')" />
                        <x-text-input id="new_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required />
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="force_password_change" id="fpc_new" value="1" {{ old('force_password_change') ? 'checked' : '' }}>
                        <label for="fpc_new" class="text-sm text-gray-600">{{ __('Force password change at first login') }}</label>
                    </div>
                    <div class="flex justify-end gap-3 mt-2">
                        <button type="button" onclick="document.getElementById('add-user-dialog').close()"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                            {{ __('Cancel') }}
                        </button>
                        <x-primary-button>{{ __('Add User') }}</x-primary-button>
                    </div>
                </form>
            </dialog>

        </div>
    </div>
</x-app-layout>
