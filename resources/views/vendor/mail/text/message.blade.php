<x-mail::layout>
    {{-- Encabezado --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            ML Habitat
        </x-mail::header>
    </x-slot:header>

    {{-- Cuerpo --}}
    {{ $slot }}

    {{-- Texto secundario --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Pie --}}
    <x-slot:footer>
        <x-mail::footer>
            ML Habitat · Diseñamos, fabricamos e instalamos muebles a la medida.
            © {{ date('Y') }} {{ \App\Support\Settings::get('footer.legal_name') ?: 'ML Habitat' }}. Todos los derechos reservados.
            Nunca te pediremos tu contraseña ni los datos completos de tu tarjeta por correo.
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
