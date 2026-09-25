# Estructura del proyecto

## Estructura objetivo

La siguiente estructura queda aprobada como guía. Se creará incrementalmente; G-0 no autoriza generar el esqueleto funcional.

```text
app/
├── Controladores/
├── Servicios/
├── Repositorios/
├── Modelos/
├── Interfaces/
├── Intermediarios/
├── Nucleo/
└── Vistas/
    ├── plantillas/
    ├── componentes/
    └── panel/
config/
database/
├── migraciones/
└── semillas/
docs/
└── gobernanza/
public/
├── index.php
└── assets/
    ├── css/
    ├── js/
    ├── imagenes/
    └── vendor/
routes/
storage/
├── cache/
├── logs/
└── privados/
tests/
├── Unitarias/
├── Integracion/
└── Funcionales/
.skills/
admin-dashboard/
```

## Responsabilidades

- `public/`: único document root; no contiene secretos ni archivos privados.
- `app/`: código propio de aplicación.
- `config/`: configuración versionable sin credenciales; secretos llegan por entorno.
- `database/`: migraciones y datos mínimos reproducibles, nunca volcados productivos.
- `storage/`: archivos generados y logs fuera de exposición pública.
- `tests/`: pruebas alineadas con las capas.
- `.skills/`: procedimientos especializados para agentes; no duplica gobernanza.
- `admin-dashboard/`: fuente original inmutable de Alina, documentación y demos.

## Autoload

Composer administrará PSR-4 cuando se inicialice PHP. El namespace raíz exacto se decidirá en la microfase de infraestructura y se registrará antes de crear clases.

## Exposición web

Laragon deberá apuntar a `public/`, no a la raíz del repositorio. Hasta que exista el Front Controller, no se debe simular una estructura pública copiando demos de Alina.
