# Arquitectura

## Objetivo

Camargo PMS será una aplicación modular con MVC propio y arquitectura por capas. El dominio debe ser reutilizable por la interfaz web, la API, WordPress, una futura app y conectores externos sin duplicar reglas.

## Flujo principal

```text
Petición HTTP / Fetch
        ↓
Enrutador
        ↓
Intermediarios
        ↓
Controlador
        ↓
Servicio
        ↓
Repositorio
        ↓
PDO
        ↓
MySQL / MariaDB
```

### Enrutador

Resuelve método y ruta, soporta patrones parametrizados (ej. `/configuracion/menu/{id}`), emulación de verbos HTTP (`_method` o cabecera `X-HTTP-Method-Override` para `PUT`, `PATCH`, `DELETE`), extrae parámetros y entrega la petición al pipeline. No contiene reglas de negocio.

### Intermediarios

Aplican preocupaciones transversales como sesión, autenticación (`AutenticacionIntermediario`), autorización RBAC (`AutorizacionIntermediario`), CSRF, límites de uso o contexto del actor. Deben ser componibles y emitir respuestas HTTP seguras y controladas (redirecciones 302 a `/login` o respuestas 403 Forbidden). No contienen lógica acoplada de un módulo específico.

### Controladores

Interpretan entrada HTTP, invocan un servicio y producen HTML o JSON (`Respuesta::json()`). Validan forma y tipos básicos de la petición, pero no ejecutan SQL ni concentran reglas de dominio. `UsuarioControlador` expone la interfaz administrativa web y los endpoints de consulta/mutación JSON bajo `/usuarios`.

### Servicios

Implementan casos de uso, invariantes, cálculos, coordinación entre repositorios y límites transaccionales (ej. `MenuServicio`, `AutorizacionServicio`, `UsuarioServicio`). Un mismo servicio puede ser usado por controladores web o API. `MenuServicio` ensambla el árbol de navegación dinámico autorizando cada opción contra RBAC y depurando categorías vacías. `UsuarioServicio` centraliza la creación de usuarios humanos sincronizando actores, la mutación de estados (`ACTIVO`, `INACTIVO`, `BLOQUEADO`), la revocación de sesiones y el restablecimiento administrativo de contraseñas garantizando el invariante del último superadministrador activo y la auditoría transversal.

### Repositorios

Encapsulan consultas y persistencia. Devuelven entidades, objetos de transferencia o resultados tipados; no generan HTML ni respuestas HTTP. `UsuarioRepositorio` implementa consultas paginadas con filtros multicriterio, inspección detallada de perfiles, conteos y proyección de personas humanas disponibles no vinculadas.

### Vistas

Renderizan datos ya preparados por los controladores o componentes de layout (`navegacion.php`). No consultan base de datos, no autorizan acciones y no construyen reglas de negocio. Todo valor dinámico se escapa según su contexto mediante `e()`.

### JavaScript

Mejora la experiencia, valida de forma complementaria y consume JSON (ej. `gestion-menu.js`, `gestion-usuarios.js` con Vanilla JS, Fetch API, PristineJS v1.1.0 y SweetAlert2). La autoridad y validación residen incondicionalmente en el servidor.

### Núcleo Transversal de Auditoría (AUDITORÍA-1)

Capa de persistencia y trazabilidad de negocio transversal:
- **`AuditoriaServicio`:** Orquesta la captura de eventos, resuelve el actor en ejecución (`ACTOR ≠ USUARIO`), extrae el contexto HTTP (`ip`, `user_agent`, `metodo_http`, `ruta`, `correlacion_id`), coordina la transacción y delega la desinfección a `SanitizadorAuditoria`.
- **`SanitizadorAuditoria`:** Purga recursivamente secretos, contraseñas, tokens y claves de API de los valores auditados.
- **`AuditoriaRepositorio` & `ActorAuditoriaRepositorio`:** Repositorios append-only con inmutabilidad estricta.

### Gestión Integral de Usuarios (USUARIOS-1)

Capa de administración de cuentas humanas de acceso:
- **`UsuarioControlador`:** Enruta peticiones hacia `/usuarios`, gestiona respuestas HTML y API REST (listado paginado, búsqueda, detalle, cambio de estado, restablecimiento de contraseña, revocación individual y masiva de sesiones, personas disponibles).
- **`UsuarioServicio`:** Aplica invariantes de dominio: vinculación obligatoria y única a Persona humana (`PERSONA ≠ USUARIO ≠ COLABORADOR ≠ ROL`), no eliminación física de usuarios (solo transiciones entre `ACTIVO`, `INACTIVO` y `BLOQUEADO`), protección estricta del último Superadministrador activo, y registro obligatorio de eventos en `AuditoriaServicio`.
- **`UsuarioRepositorio`:** Persiste y consulta entidades de usuario con paginación, filtros dinámicos, búsquedas insensibles a mayúsculas y proyección de personas elegibles.

### Gestión Visual de Roles y Permisos (ROLES-2 / ROLES-2A)

Capa de administración visual de roles de autorización y matriz de permisos por módulo funcional:
- **`RolControlador`:** Enruta peticiones hacia `/configuracion/roles`, sirviendo la vista Alina y los endpoints JSON (`/datos`, `/permisos-catalogo`, `/{id}`, `/{id}/estado`, `/{id}/permisos`).
- **`RolServicio`:** Centraliza las reglas de negocio de roles y permisos: principios `ROL DE AUTORIZACIÓN ≠ CARGO LABORAL` y `ROL ≠ REGISTRO DESECHABLE` (cero eliminación física en operación ordinaria, gestión exclusiva vía `ACTIVO` / `INACTIVO` para preservar historial y auditoría), cálculo de permisos en tiempo real sin relogin, protección incondicional de `SUPERADMINISTRADOR` (código inmutable, no desactivable y revocación de permisos críticos prohibida), invariante pesimista del último Superadministrador humano activo, sincronización matricial transaccional y trazabilidad D-061 con actor humano resuelto.
- **`RolRepositorio` & `PermisoRepositorio`:** Proveen agregaciones de conteos de usuarios y permisos vinculados por rol (`listarRolesConConteos`), listado de usuarios con proyección de personas asociadas (`obtenerUsuariosPorRol`) y catálogo de permisos agrupados por módulo funcional (`listarAgrupadosPorModulo`).
- **`gestion-roles.js`:** Módulo cliente en Vanilla JS con Fetch API, PristineJS v1.1.0 local para validación client-side y SweetAlert2 para confirmaciones defensivas de cambio de estado (`ACTIVO` / `INACTIVO`).

## Dependencias permitidas

```text
Presentación → Aplicación → Dominio/Persistencia
```

Las capas internas no dependen de HTML, Bootstrap o detalles HTTP. Los repositorios pueden depender de PDO; los servicios dependen de contratos de repositorio, no de SQL disperso.

## Transacciones

El servicio define el límite transaccional de casos críticos: reservar disponibilidad, confirmar pagos, registrar caja, versionar contratos o consumir lecturas. Un repositorio no debe confirmar parcialmente una operación coordinada.

## Errores

- Excepciones de dominio: violación esperada de una regla, traducible a respuesta controlada.
- Errores de validación: campos y mensajes seguros para el cliente.
- Fallos técnicos: registrados con identificador de correlación; el cliente recibe un mensaje genérico.
- Nunca exponer traza, SQL, credenciales o estructura interna en producción.

## Integraciones

Las integraciones se aíslan detrás de interfaces propias, por ejemplo `ProveedorPagoInterfaz`. Un adaptador traduce la API externa al modelo interno. WordPress consulta disponibilidad y envía operaciones al PMS; no reproduce el motor de disponibilidad.

## Límites actuales

No se ha elegido contenedor de dependencias, biblioteca de routing, motor de plantillas, librería PDF ni herramienta de migraciones. Elegirlos requiere una decisión registrada y una necesidad de fase.
