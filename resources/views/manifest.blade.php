{
    "name": "{{ config('app.name') }}",
    "short_name": "{{ config('app.name') }}",
    "description": "{{ __('Show the best rewards card to use') }}",
    "start_url": "{{ url('/') }}",
    "display": "standalone",
    "background_color": "#ffffff",
    "theme_color": "#ffffff",
    "orientation": "portrait-primary",
    "icons": [
        {
            "src": "{{ asset('images/icon-192.png') }}",
            "sizes": "192x192",
            "type": "image/png",
            "purpose": "any maskable"
        },
        {
            "src": "{{ asset('images/icon-512.png') }}",
            "sizes": "512x512",
            "type": "image/png",
            "purpose": "any maskable"
        }
    ]
}
