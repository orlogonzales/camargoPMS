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
    ├── errores/
    └── panel/
SQL/
├── camargo_pms.sql
└── migraciones/
docs/
└── gobernanza/
public/
├── index.php
└── assets/
    ├── css/
    ├── js/
    ├── imagenes/
    └── vendor/
storage/
├── cache/
├── logs/
└── privados/
tests/
.skills/
admin-dashboard/
.env.example
composer.json
composer.lock
migrar.php
```

## Responsabilidades

- `public/`: único document root; no contiene secretos ni archivos privados.
- `app/`: código propio de aplicación.
- `SQL/`: `camargo_pms.sql` como esquema consolidado oficial y `migraciones/` para cambios incrementales versionados.
- `migrar.php`: ejecutor CLI para aplicar migraciones en la base de datos `camargo_pms`.
- `.env.example`: plantilla versionada de variables de entorno sin credenciales reales.
- `storage/`: archivos generados y logs fuera de exposición pública.
- `tests/`: pruebas automatizadas de infraestructura y dominio.
- `.skills/`: procedimientos especializados para agentes; no duplica gobernanza.
- `admin-dashboard/`: fuente original inmutable de Alina, documentación y demos.

## Autoload

Composer administra el autocargador PSR-4 bajo el espacio de nombres raíz `CamargoPMS\` mapeado directamente al directorio `app/`.

## Exposición web

Laragon deberá apuntar a `public/`, no a la raíz del repositorio. Hasta que exista el Front Controller, no se debe simular una estructura pública copiando demos de Alina.
