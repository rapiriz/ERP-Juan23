# Estructura de carpetas — Grupo 2

Módulos: Entregas, Facturación, Caja, Cobros, Conciliación Bancaria, Rendiciones.

⚠️ Estructura **temporal** (bandera roja, no acordada formalmente por los 4 grupos —
ver `02-arquitectura-tecnica.md` del Project de Claude).

## Criterio

Organización: **módulo primero, capa después**. Cada módulo tiene sus propias carpetas
`Controllers/ Services/ Repositories/ Models/`. `Shared/` contiene lo transversal a los
6 módulos (conexión PDO, router, formato de respuesta, validación de sesión).

Los archivos `.gitkeep` son placeholders para que Git trackee carpetas vacías (Git no
versiona carpetas sin contenido) — se pueden borrar a medida que agregues archivos reales
dentro de cada carpeta.

## Cómo integrarla a tu repo local

1. Copiá **el contenido** de esta carpeta (`src/`, `public/`, `config/`) dentro de la raíz
   de tu repo local `ERP-Juan23`, estando parada en tu rama `feature/g2-estefania-gianovich`.
2. Verificá con `git status` que los archivos nuevos aparecen.
3. `git add .`
4. `git commit -m "feat: estructura base de carpetas Grupo 2"`
5. `git push`

Después, cuando el equipo lo apruebe/confirme, se mergea a `develop-g2` como cualquier
otro cambio (Pull Request si así lo definieron).
