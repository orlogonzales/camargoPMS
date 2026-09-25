# Stack tecnológico

## Aprobado

| Área | Tecnología | Uso |
|---|---|---|
| Servidor | PHP 8.3 | Aplicación y API |
| Dependencias | Composer | Autoload y paquetes PHP aprobados |
| Arquitectura | MVC propio por capas | Separación de HTTP, negocio y persistencia |
| Persistencia | PDO | Acceso parametrizado a datos |
| Base de datos | MySQL/MariaDB | Modelo relacional y transacciones |
| Interfaz | Bootstrap 5 + Alina | Lenguaje visual oficial |
| Cliente | JavaScript moderno | Módulos propios e interacción |
| Transporte | Fetch + JSON | Operaciones asíncronas y API |
| Iconos | Tabler Icons | Iconografía base de Alina |
| Scroll | Simplebar | Navegación lateral de Alina |

Alina incluye Bootstrap 5.3.0-alpha1, jQuery 3.6.3, Simplebar 6.2.4 y Tabler Icons 2.4.0. Esas versiones describen la fuente entregada, no una aprobación automática para actualizarlas o sustituirlas.

## Uso condicionado

- PristineJS: candidato aprobado conceptualmente para validación cliente; la validación servidor sigue siendo obligatoria.
- SweetAlert: disponible en Alina y utilizable por módulo para confirmaciones y mensajes.
- jQuery: no será dependencia arquitectónica nueva. Puede mantenerse temporalmente donde Alina lo requiera hasta reemplazar la conducta correspondiente.
- Webpack/SCSS de Alina: fuente de compilación de la plantilla; no adoptar su `package.json` completo como dependencias de la aplicación.

## Pendiente de decisión

- Motor de routing y composición de vistas.
- Herramienta de migraciones y pruebas.
- Librería PDF compatible con membretes PNG, fondos A4, márgenes configurables y documentos multipágina.
- Proveedor inicial de pagos y canales de mensajería.
- Versiones exactas de MySQL/MariaDB y estrategia de despliegue.

Una dependencia se incorpora solo por una necesidad concreta, tras revisar mantenimiento, licencia, seguridad, tamaño, compatibilidad y plan de actualización.
