@extends('catalogo.layout')
@section('title', $product->nombre.' | Catálogo PyB Pigüé')
@section('sidebar')
<aside id="catalogo-sidebar" class="cat-sidebar cat-detail-sidebar" aria-label="Navegación del catálogo"><div class="cat-sidebar-heading"><h2>Catálogo de productos</h2><button type="button" class="cat-close-menu" data-cat-close aria-label="Cerrar categorías">×</button></div><a class="cat-back" href="{{ route('catalogo.index') }}">@include('catalogo.partials.icon', ['icon' => 'back']) Todos los productos</a><a class="cat-category cat-detail-category" href="{{ route('catalogo.index', ['categoria' => $product->categoriaId]) }}">{{ $product->categoria }}</a><div class="cat-sidebar-note">@include('catalogo.partials.icon', ['icon' => 'check'])<p><strong>Elegí con información.</strong>Consultá la presentación y el estado antes de planificar tu compra.</p></div></aside>
<button class="cat-button" type="button" data-add-cart="{{ $product->id }}">Agregar a mi selección</button>
@endsection
@section('content')
<nav class="cat-breadcrumb" aria-label="Ubicación"><a href="{{ route('catalogo.index') }}">Catálogo</a><span>/</span><a href="{{ route('catalogo.index', ['categoria' => $product->categoriaId]) }}">{{ $product->categoria }}</a><span>/</span><span>{{ $product->codigo }}</span></nav>
<article class="cat-product-detail">
    @include('catalogo.partials.picture', ['large' => true])
    <div class="cat-detail-info"><div class="cat-card-meta"><span>{{ $product->marca }}</span><small>Cód. {{ $product->codigo }}</small></div><h1>{{ $product->nombre }}</h1><p class="cat-detail-presentation">{{ $product->presentacion }}</p><span class="cat-stock cat-stock-{{ $product->disponibilidad }}"><i></i>{{ $product->etiqueta() }}</span>
        <p class="cat-detail-description">{{ $product->descripcion ?: 'Consultá la presentación y disponibilidad informadas para este producto.' }}</p><dl><div><dt>Categoría</dt><dd>{{ $product->categoria }}</dd></div><div><dt>Marca</dt><dd>{{ $product->marca }}</dd></div><div><dt>Código</dt><dd>{{ $product->codigo }}</dd></div><div><dt>Presentación</dt><dd>{{ $product->presentacion }}</dd></div></dl>
        <div class="cat-detail-price"><small>Precio de referencia</small><strong>{{ $product->precioVisible() }}</strong></div><p class="cat-detail-disclaimer">La consulta no reserva unidades ni confirma un pedido. La disponibilidad puede cambiar.</p><a class="cat-button" href="{{ route('catalogo.show', $product->id) }}">@include('catalogo.partials.icon', ['icon' => 'refresh']) Actualizar consulta</a><p class="cat-freshness">Consultado a las {{ $checkedAt->format('H:i') }} · Argentina</p>
    </div>
</article>
@if($related->isNotEmpty())<section class="cat-related"><span class="cat-eyebrow">SEGUÍ EXPLORANDO</span><h2>{{ $product->disponibilidad === 'sin-stock' ? 'Otras opciones disponibles en esta categoría' : 'También en esta categoría' }}</h2><div class="cat-products-grid">@foreach($related as $product)@include('catalogo.partials.card')@endforeach</div></section>@endif
@if($product->disponibilidad === 'sin-stock' && $related->isEmpty())
<section class="cat-empty" role="status"><h2>{{ $relatedChecked ? 'Sin alternativas disponibles por ahora' : 'No pudimos consultar las alternativas' }}</h2><p>{{ $relatedChecked ? 'Este producto está agotado y no encontramos otras opciones con disponibilidad en su categoría. Podés explorar el resto del catálogo.' : 'La consulta de otras opciones no está disponible en este momento. Volvé a actualizar en unos instantes.' }}</p><a class="cat-button" href="{{ route('catalogo.index') }}">Explorar todos los productos</a></section>
@endif
<button class="cat-button" type="button" data-add-cart="{{ $product->id }}">Agregar a mi selección</button>
@endsection
