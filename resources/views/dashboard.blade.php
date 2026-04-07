<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ config('app.name') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('Finding businesses within 300 meters using your location...') }}</p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @if (session('status'))
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->has('google_places'))
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-semibold text-red-800">{{ __('Unable to Load Nearby Businesses') }}</h3>
                                <p class="mt-2 text-sm text-red-700">{{ $errors->first('google_places') }}</p>
                                <p class="mt-3 text-xs text-red-600">{{ __('Please try again later or check that your browser location services are enabled.') }}</p>
                            </div>
                        </div>
                    </div>
                @elseif ($errors->has('google_maps_api_key'))
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-semibold text-red-800">{{ __('Configuration Error') }}</h3>
                                <p class="mt-2 text-sm text-red-700">{{ $errors->first('google_maps_api_key') }}</p>
                            </div>
                        </div>
                    </div>
                @elseif ($errors->any())
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif



                @if (!$errors->any())
                    <div id="locate-prompt" class="text-center py-4">
                        <button type="button" onclick="geolocateUser()" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ __('Find Nearby Businesses') }}
                        </button>
                    </div>
                @endif

                @if ($search)
                    <table class="text-xs text-black">
                        <tr>
                            <td class="pr-8">{{ __('Your location') }}</td>
                            <td class="pr-8">{{ $search['latitude'] }}</td>
                            <td class="pr-8">{{ $search['longitude'] }}</td>
                        </tr>
                        @if ($cachedLocation)
                        <tr style="background-color: rgba(240, 0, 0, 0.25);">
                            <td class="pr-8">{{ __('Cached location') }}</td>
                            <td class="pr-8">{{ $cachedLocation['latitude'] }}</td>
                            <td class="pr-8">{{ $cachedLocation['longitude'] }}</td>
                        </tr>
                        @endif
                    </table>
                @endif

                <form id="nearby-location-form" method="POST" action="{{ route('dashboard.search') }}" class="hidden">
                    @csrf
                    <input id="latitude" name="latitude" value="{{ old('latitude', data_get($search, 'latitude')) }}" type="hidden" />
                    <input id="longitude" name="longitude" value="{{ old('longitude', data_get($search, 'longitude')) }}" type="hidden" />
                </form>

            </div>

            @if ($results)
                <table class="mt-6 w-full bg-white shadow-sm sm:rounded-lg">
                    <tbody>
                        @foreach ($results as $business)
                            @php
                                $recommendedCard = $cards->firstWhere('name', $business['recommended_card']);
                                $uncategorized = !$business['friendly_category'];
                                $bgColor       = $recommendedCard?->color ?? '#6B7280';
                                $rowBg         = $uncategorized ? 'background-color: #DC2626;' : 'background-color: ' . $bgColor . '22;';
                                $borderColor   = $uncategorized ? '#DC2626' : $bgColor;
                                $textStyle     = $uncategorized ? 'color: white;' : '';
                            @endphp
                            <tr onclick="window.location='{{ route('places.show', $business['place_id']) }}'" style="{{ $rowBg }} border-bottom: 1px solid #e5e7eb; cursor: pointer;">
                                <td class="px-6 py-3 text-sm" style="border-left: 4px solid {{ $borderColor }}; {{ $textStyle }}">
                                    {{ $business['name'] }}
                                </td>
                                <td class="px-4 py-3 text-center" style="width: 15%; {{ $textStyle }}">
                                    @if ($recommendedCard?->image_data)
                                        <img src="{{ route('cards.image', $recommendedCard) }}"
                                             alt="{{ $recommendedCard->name }}"
                                             class="rounded mx-auto mb-1"
                                             style="max-height: 48px; width: auto; height: auto;">
                                    @endif
                                    <div class="text-xs font-medium leading-tight">
                                        {{ $business['recommended_card'] ?? __('Unknown') }}
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @elseif($search)
                <div class="mt-6 bg-white shadow-sm sm:rounded-lg p-6 text-gray-700">
                    {{ __('No businesses were found within 300 meters of the selected location.') }}
                </div>
            @endif
        </div>
    </div>

    <script>
        function geolocateUser() {
            if (!navigator.geolocation) {
                alert("{{ __('Geolocation is not supported by your browser.') }}");
                return;
            }

            if (!window.isSecureContext) {
                alert("{{ __('Location access requires a secure (HTTPS) connection. Please contact the site administrator.') }}");
                return;
            }

            // enableHighAccuracy: false — use network/WiFi location rather than GPS.
            // GPS is unnecessarily precise for a 300 m search radius and causes
            // Android to spin up the GPS radio, adding 10-30 s on a cold start.
            // maximumAge: 60000 — accept a cached position up to 60 s old.
            // timeout: 10000 — give up after 10 s rather than waiting indefinitely.
            var options = { enableHighAccuracy: false, maximumAge: 60000, timeout: 10000 };

            navigator.geolocation.getCurrentPosition(function(position) {
                document.getElementById('latitude').value = position.coords.latitude;
                document.getElementById('longitude').value = position.coords.longitude;
                document.getElementById('nearby-location-form').submit();
            }, function(error) {
                if (error.code === error.PERMISSION_DENIED) {
                    alert("{{ __('Location permission was denied. Please enable location access for this site in your device settings, then reload the page.') }}");
                } else if (error.code === error.POSITION_UNAVAILABLE) {
                    alert("{{ __('Your location could not be determined. Please try again.') }}");
                } else {
                    alert("{{ __('Unable to retrieve your location. Please try again.') }}");
                }
            }, options);
        }
    </script>
</x-app-layout>
