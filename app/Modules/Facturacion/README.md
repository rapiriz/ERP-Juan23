# Preparación fiscal

El alcance autorizado es local y con comprobantes internos. Este módulo NO emite comprobantes fiscales, no genera CAE y no comunica con ARCA.

`Contracts/FiscalIssuer` define el punto de extensión. `InternalDocumentsOnly` es la implementación registrada y rechaza la emisión. `FiscalReadiness` informa qué configuración falta, sin publicar CUIT, claves, certificados ni rutas privadas. `config/fiscal.php` mantiene la emisión deshabilitada incluso si se completan variables de entorno.

La pantalla `/operaciones/fiscal` y `GET /api/v1/fiscal/estado` admiten administración/contador y las reglas existentes de autenticación de lectura. Los ejemplos de variables de homologación están vacíos en `.env.example`; `.env` no se modificó.

Para habilitar emisión en otra etapa harán falta autorización del emisor, punto de venta, certificados privados, adaptador WSAA/WSFE, reglas impositivas, persistencia y recuperación de solicitudes inciertas, pruebas de homologación y habilitación expresa. Tener un archivo de certificado no demuestra que sea válido ni autorizado.

Documentación oficial: [WSFE](https://www.arca.gob.ar/ws/documentacion/ws-factura-electronica.asp), [WSAA](https://arca.gob.ar/ws/documentacion/wsaa.asp), [homologación](https://www.arca.gob.ar/ws/documentacion/homologacion-externa.asp).
