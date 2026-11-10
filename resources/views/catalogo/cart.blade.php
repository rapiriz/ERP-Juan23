@extends('catalogo.layout')
@section('title', 'Tu selección de productos')
@section('content')
<section data-catalog-cart data-product-url="{{ url('/api/v1/catalogo') }}" data-order-url="{{ route('catalogo.orders.store') }}">
    <p class="cat-eyebrow">TU PRÓXIMA COMPRA</p><h1>Tu selección de productos</h1>
    <p>Enviá tu solicitud sin iniciar sesión. La distribuidora verificará tu identidad, disponibilidad, lista de precios y condiciones antes de registrar una venta. Esta selección no reserva stock.</p>
    <div class="cat-empty" data-cart-rows aria-live="polite">Cargando selección…</div>
    <p data-cart-total></p><p data-cart-message role="status" aria-live="polite"></p>
    <a class="cat-button" href="{{ route('catalogo.index') }}">Seguir explorando</a>
    <form data-cart-checkout class="cat-checkout">
        @csrf
        <h2>¿Cómo te contactamos?</h2>
        <label for="cart-name">Nombre y comercio</label><input id="cart-name" name="nombre" required minlength="3" maxlength="160" autocomplete="name">
        <label for="cart-email">Correo electrónico</label><input id="cart-email" name="email" type="email" required maxlength="160" autocomplete="email">
        <label for="cart-phone">Teléfono</label><input id="cart-phone" name="telefono" required maxlength="30" pattern="\+?[0-9 ()\-]{7,30}" autocomplete="tel">
        <label for="cart-notes">Observaciones</label><textarea id="cart-notes" name="observaciones" maxlength="2000"></textarea>
        <div hidden><label for="cart-website">Dejar vacío</label><input id="cart-website" name="website" tabindex="-1" autocomplete="off"></div>
        <label class="cat-consent"><input name="acepta_contacto" type="checkbox" required value="1"><span> Autorizo usar estos datos para responder esta solicitud. Entiendo que los precios son de referencia y que no se confirma una venta automáticamente.</span></label>
        <button class="cat-button" type="submit">Enviar solicitud</button>
    </form>
    <noscript><p>Para armar y enviar esta selección necesitás JavaScript. Podés seguir consultando productos y sus códigos sin activarlo.</p></noscript>
</section>
@endsection
