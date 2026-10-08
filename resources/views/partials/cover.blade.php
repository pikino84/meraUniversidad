{{--
    Portada de curso con respaldo de marca si el archivo no existe (p. ej. cursos que solo
    están en producción). Parámetros: $course, $class, $width, $height
--}}
<img src="{{ $course->cover_url }}" alt=""
    @isset($width) width="{{ $width }}" @endisset
    @isset($height) height="{{ $height }}" @endisset
    class="{{ $class ?? '' }}" loading="lazy" decoding="async"
    onerror="this.onerror=null;this.src='{{ asset('images/logo_university.svg') }}';this.classList.add('mera-cover-fallback');">
