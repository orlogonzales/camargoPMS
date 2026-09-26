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

Interpretan entrada HTTP, invocan un servicio y producen HTML o JSON (`Respuesta::json()`). Validan forma y tipos básicos de la petición, pero no ejecutan SQL ni concentran reglas de dominio.

### Servicios

Implementan casos de uso, invariantes, cálculos, coordinación entre repositorios y límites transaccionales (ej. `MenuServicio`, `AutorizacionServicio`). Un mismo servicio puede ser usado por controladores web o API. `MenuServicio` ensambla el árbol de navegación dinámico autorizando cada opción contra RBAC y depurando categorías vacías.

### Repositorios

Encapsulan consultas y persistencia. Devuelven entidades, objetos de transferencia o resultados tipados; no generan HTML ni respuestas HTTP.

### Vistas

Renderizan datos ya preparados por los controladores o componentes de layout (`navegacion.php`). No consultan base de datos, no autorizan acciones y no construyen reglas de negocio. Todo valor dinámico se escapa según su contexto mediante `e()`.

### JavaScript

Mejora la experiencia, valida de forma complementaria y consume JSON (ej. `gestion-menu.js` con Vanilla JS, Fetch API y SweetAlert2). La autoridad y validación residen incondicionalmente en el servidor.

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
