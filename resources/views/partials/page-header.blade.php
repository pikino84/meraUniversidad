{{--
    Encabezado estándar de listados.
    @include('partials.page-header', ['title' => 'Cursos', 'subtitle' => '…', 'action' => ['url' => …, 'label' => 'Nuevo curso']])
--}}
<div class="page-header mera-page-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="header-title-wrapper">
                <span class="header-line" aria-hidden="true"></span>
                <div>
                    <h1 class="mera-title h5">{{ $title }}</h1>
                    @isset($subtitle)
                    <p class="mera-subtitle">{{ $subtitle }}</p>
                    @endisset
                </div>
            </div>
        </div>

        @isset($action)
        <div class="col-md-4 text-md-end mt-2 mt-md-0">
            <a href="{{ $action['url'] }}" class="btn mera-btn-primary">
                <span class="mera-btn-icon" aria-hidden="true">+</span>
                {{ $action['label'] }}
            </a>
        </div>
        @endisset
    </div>
</div>
