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

Resuelve método y ruta, extrae parámetros y entrega la petición al pipeline. No contiene reglas de negocio.

### Intermediarios

Aplican preocupaciones transversales como sesión, autenticación, autorización, CSRF, límites de uso o contexto del actor. Deben ser componibles y no realizar lógica propia de un módulo.

### Controladores

Interpretan entrada HTTP, invocan un servicio y producen HTML o JSON. Validan forma y tipos básicos de la petición, pero no ejecutan SQL ni concentran reglas de dominio.

### Servicios

Implementan casos de uso, invariantes, cálculos, coordinación entre repositorios y límites transaccionales. Un mismo servicio puede ser usado por controladores web o API.

### Repositorios

Encapsulan consultas y persistencia. Devuelven entidades, objetos de transferencia o resultados tipados; no generan HTML ni respuestas HTTP.

### Vistas

Renderizan datos ya preparados. No consultan base de datos, no autorizan acciones y no construyen reglas de negocio. Todo valor dinámico se escapa según su contexto.

### JavaScript

Mejora la experiencia, valida de forma complementaria y consume JSON. La autoridad sigue en el servidor.

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
