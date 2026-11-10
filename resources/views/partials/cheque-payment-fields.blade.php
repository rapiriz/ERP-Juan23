<fieldset data-cheque-fields hidden class="field">
    <legend>Datos del cheque</legend>
    <div class="field"><label>Número de cheque<input data-cheque-input name="datos_medio_pago[numero_cheque]" maxlength="50" value="{{ old('datos_medio_pago.numero_cheque') }}" disabled></label></div>
    <div class="field"><label>Banco emisor<input data-cheque-input name="datos_medio_pago[banco_emisor]" maxlength="100" value="{{ old('datos_medio_pago.banco_emisor') }}" disabled></label></div>
    <div class="field"><label>Fecha de cobro<input data-cheque-input name="datos_medio_pago[fecha_cobro]" type="date" value="{{ old('datos_medio_pago.fecha_cobro') }}" disabled></label></div>
    <small>Se registra en cartera. Su acreditación se verifica desde Conciliación Bancaria.</small>
</fieldset>
@push('scripts')
    <script src="{{ asset('assets/js/cheque-payment.js') }}" defer></script>
@endpush
