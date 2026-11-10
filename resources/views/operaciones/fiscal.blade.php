@extends('layouts.app')
@section('title', 'Preparación de facturación fiscal')
@section('content')
<section class="page-container"><h1>Preparación de facturación fiscal</h1><p>Actualmente se emiten únicamente comprobantes internos, sin CAE ni validez fiscal. No se realizaron solicitudes de emisión a ARCA.</p>
<section class="section-block"><h2>Configuración para homologación</h2><dl>@foreach(['cuenta_fiscal_configurada'=>'CUIT configurado (formato)','punto_venta_configurado'=>'Punto de venta configurado','certificado_privado_presente'=>'Certificado fuera de public','clave_privada_presente'=>'Clave privada fuera de public','adaptador_arca_implementado'=>'Adaptador de emisión real'] as $key=>$label)<dt>{{ $label }}</dt><dd>{{ $status[$key] ? 'Configurado: requiere verificación real' : 'Pendiente' }}</dd>@endforeach</dl></section>
<section class="section-block"><h2>Antes de habilitar emisión</h2><ul>@foreach($status['pendientes'] as $pending)<li>{{ $pending }}</li>@endforeach</ul><p>Los certificados y contraseñas se configuran localmente y nunca se suben al repositorio. Tener archivos presentes no certifica su validez ni habilita la emisión.</p></section></section>
@endsection
