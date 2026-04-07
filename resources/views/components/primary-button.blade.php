<style>
    .btn-primary { background: linear-gradient(to bottom, #0171b4, #01508a ); }
    .btn-primary:hover { background: linear-gradient(to bottom, #022d4e, #013d65); }
    .btn-primary:active { background: linear-gradient(to bottom, #022d4e, #013d65); }
</style>
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-primary inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
