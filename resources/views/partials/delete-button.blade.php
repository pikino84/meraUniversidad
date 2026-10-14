{{--
    Botón de eliminar con confirmación (ver resources/js/modules/confirm.js).
    @include('partials.delete-button', ['action' => route(...), 'label' => 'Eliminar módulo X', 'confirm' => '¿Eliminar el módulo?', 'strong' => 'Nombre (opcional)', 'detail' => '…'])
--}}
<form action="{{ $action }}" method="POST" class="d-inline"
    data-confirm="{{ $confirm }}" data-confirm-tone="danger"
    @isset($strong) data-confirm-strong="{{ $strong }}" @endisset
    @isset($detail) data-confirm-detail="{{ $detail }}" @endisset>
    @csrf
    @method('DELETE')
    <button type="submit" class="mera-action-btn mera-delete-btn" title="{{ $label }}" aria-label="{{ $label }}">
        <i class="fa fa-trash" aria-hidden="true"></i>
    </button>
</form>
