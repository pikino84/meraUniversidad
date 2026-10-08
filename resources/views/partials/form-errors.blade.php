@if ($errors->any())
<div class="alert alert-danger" role="alert">
    <strong>Revisa los siguientes campos:</strong>
    <ul class="mb-0 mt-2">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif
