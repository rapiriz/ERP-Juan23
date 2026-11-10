@if($errors->any())
<div class="alert alert-error" role="alert"><p>Revisá los datos antes de continuar.</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
