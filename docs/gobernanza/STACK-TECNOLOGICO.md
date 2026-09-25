# Stack tecnológico

## Aprobado

| Área | Tecnología | Uso |
|---|---|---|
| Servidor | PHP 8.3 | Aplicación y API |
| Dependencias | Composer 2.x | Autoload PSR-4 (`CamargoPMS\`) y paquetes PHP aprobados |
| Entorno | vlucas/phpdotenv | Carga segura de variables de entorno `.env` |
| Arquitectura | MVC propio por capas | Separación de HTTP, negocio y persistencia |
| Enrutamiento | Enrutador nativo propio | Resolución desacoplada de rutas HTTP en `app/Nucleo/` |
| Persistencia | PDO | Acceso parametrizado centralizado (`BaseDatos.php`) |
| Base de datos | MySQL 8.4.3 LTS | Modelo relacional, InnoDB, utf8mb4 y transacciones |
| Migraciones | Runner CLI nativo | Control incremental determinista (`migrar.php`) |
| Interfaz | Bootstrap 5 + Alina | Lenguaje visual oficial |
| Cliente | JavaScript moderno | Módulos propios (`camargo-layout.js`) e interacción nativa |
| Transporte | Fetch + JSON | Operaciones asíncronas y API |
| Iconos | Tabler Icons | Iconografía base de Alina |
| Scroll | Simplebar | Navegación lateral de Alina |

Alina incluye Bootstrap 5.3.0-alpha1, Simplebar 6.2.4 y Tabler Icons 2.4.0. Se utiliza JavaScript moderno sin dependencias de jQuery para el layout de Camargo PMS.

## Uso condicionado

- PristineJS: candidato aprobado conceptualmente para validación cliente; la validación servidor sigue siendo obligatoria.
- SweetAlert: disponible en Alina y utilizable por módulo para confirmaciones y mensajes.
- jQuery: descartado de la aplicación nueva; preservado intacto únicamente en el catálogo inmutable `admin-dashboard/`.
- Webpack/SCSS de Alina: fuente de compilación de la plantilla; no adoptar su `package.json` completo como dependencias de la aplicación.

## Pendiente de decisión

- Framework de pruebas PHP/JS (P-003).
- Librería PDF compatible con membretes PNG, fondos A4, márgenes configurables y documentos multipágina (P-007).
- Proveedor inicial de pagos y canales de mensajería (P-008).
- Estrategia de concurrencia y fecha hotelera (P-004, P-006).

Una dependencia se incorpora solo por una necesidad concreta, tras revisar mantenimiento, licencia, seguridad, tamaño, compatibilidad y plan de actualización.
