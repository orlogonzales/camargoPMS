# Camargo PMS — guía obligatoria para agentes

## Propósito

Camargo PMS es la fuente central de verdad de Camargo Hostelería para propiedades, unidades, personas, reservas, estadías, arrendamientos, contratos, servicios, cobros, caja, mantenimiento, inventario e integraciones. WordPress, aplicaciones y canales externos deben consumir el PMS mediante API; no deben mantener reglas de negocio paralelas.

## Lectura obligatoria

Antes de proponer o modificar código:

1. Leer este archivo.
2. Leer `docs/gobernanza/README.md`, `ARQUITECTURA.md`, `CONVENCIONES.md`, `SEGURIDAD.md`, `PRUEBAS.md` y `DECISIONES.md`.
3. Leer el documento especializado de la tarea y el skill local aplicable de `.skills/`.
4. Para interfaz, leer además `FRONTEND.md` y `PLANTILLA-ALINA.md` y partir de `admin-dashboard/alina/template/blank.html`.
5. Revisar estado Git, cambios del usuario, configuración y pruebas disponibles antes de editar.

La prioridad es: instrucción aprobada actual > este archivo > `DECISIONES.md` > documento especializado > skill procedimental. Ante una contradicción, detener la implementación y registrar o solicitar la decisión; no improvisar.

## Arquitectura oficial

```text
HTTP / Fetch
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

- Controladores: traducen HTTP y coordinan la respuesta.
- Servicios: contienen reglas de negocio y límites transaccionales.
- Repositorios: encapsulan persistencia y consultas.
- Vistas: presentan datos preparados; no consultan BD ni deciden permisos.
- JavaScript: interacción de interfaz y consumo de endpoints JSON; no duplica reglas de negocio.
- No introducir framework PHP, ORM ni otra arquitectura sin una decisión explícita registrada.

## Stack aprobado

- PHP 8.3, MVC propio, Composer y PDO.
- MySQL o MariaDB relacional; la elección y versión exactas siguen pendientes.
- Bootstrap 5 y Alina, HTML5, CSS y JavaScript moderno.
- Fetch y JSON para comunicación asíncrona.
- PristineJS previsto para validación cliente y SweetAlert para interacción cuando corresponda; instalarlos solo en la fase que los necesite.
- La librería PDF se elegirá al validar contratos, recibos, membretes y fondos A4.

## Convenciones vinculantes

- Todo código propio se nombra en español: `ReservaControlador`, `ReservaServicio`, `ReservaRepositorio`, `crearReserva()`.
- Directorios propios: `Controladores`, `Servicios`, `Repositorios`, `Modelos`, `Vistas`, `Intermediarios` y `Nucleo`.
- Vistas: `Vistas/plantillas`, `Vistas/componentes` y módulos como `Vistas/panel`.
- API y nombres impuestos por PHP o dependencias conservan su forma original.
- PHPDoc es obligatorio en clases y métodos relevantes; JSDoc en módulos y funciones relevantes.
- SQL parametrizado, tipos explícitos, importes en `DECIMAL`, fechas normalizadas y transacciones en operaciones críticas.
- No guardar secretos, credenciales, tokens ni datos personales sensibles en Git o logs.

Las reglas completas están en `CONVENCIONES.md` y `BASE-DATOS.md`.

## Interfaz Alina

- Los originales bajo `admin-dashboard/` son referencia inmutable y catálogo; no editarlos ni servir las demos como aplicación final.
- `blank.html` es la base visual obligatoria.
- `navbar-menu-list` selecciona un área mediante `data-target`; `main-side-menu` contiene el `main-menu` cuyo `id` coincide. Esta relación es un único contrato de navegación.
- Las claves de menú se convierten en identificadores DOM controlados; nunca se usa texto arbitrario de BD como `id`.
- Ocultar un menú no autoriza: el backend protege cada ruta y filtra lo visible.
- Crear JavaScript propio de Camargo PMS; no usar `script.js` ni `theme_customizer.js` como núcleo definitivo.
- Cargar assets globales mínimos y assets específicos por módulo.

## Seguridad y datos

- Autenticación, autorización, CSRF, XSS, sesiones, validación servidor y consultas preparadas son obligatorias.
- Integraciones usan credenciales técnicas rotables y revocables, no cuentas humanas.
- Actores: `USER`, `SYSTEM`, `INTEGRATION` y `PAYMENT_PROVIDER`.
- Webhooks requieren autenticidad, idempotencia, trazabilidad y tolerancia a reintentos.
- Históricos financieros, contractuales, de tarifas o ventas no se recalculan por cambios posteriores del catálogo.
- No crear, alterar o eliminar tablas fuera de una migración revisada. Nunca ejecutar migraciones destructivas sin autorización, respaldo y plan de reversión.

## Método de trabajo

Cada incremento sigue:

```text
PRECHECK → análisis → propuesta → implementación limitada → pruebas
→ revisión de diff → documentación → micro-baseline Git
```

- Mantener un solo objetivo coherente por incremento.
- Preservar cambios ajenos y evitar refactorizaciones oportunistas.
- No cerrar con pruebas fallidas, errores de consola conocidos o documentación desactualizada.
- No hacer commit salvo que la fase o el usuario lo autorice.
- No iniciar una fase posterior por iniciativa propia.

## Inicio de una fase

1. Confirmar alcance, exclusiones y criterios de aceptación.
2. Ejecutar precheck de repositorio, entorno, dependencias y cambios existentes.
3. Leer fuentes de verdad y decisiones aplicables.
4. Identificar riesgos, migraciones, interfaces y pruebas.
5. Proponer una microfase reversible; pedir decisión solo si una ambigüedad cambia materialmente el resultado.

## Cierre de una fase

1. Ejecutar pruebas proporcionales al riesgo y registrar resultados.
2. Revisar el diff completo y confirmar que no incluye archivos ajenos.
3. Actualizar documentación, decisiones y `CHANGELOG.md` cuando corresponda.
4. Informar archivos modificados, riesgos y asuntos pendientes.
5. Crear micro-baseline únicamente con autorización y working tree controlado.

## Prohibiciones

- No desarrollar módulos fuera de la fase aprobada.
- No duplicar reglas entre controladores, JavaScript, WordPress o integraciones.
- No acceder a PDO desde vistas o controladores.
- No confiar en validación cliente ni visibilidad de menús como controles de seguridad.
- No modificar vendors ni originales de Alina.
- No copiar indiscriminadamente todos los assets o demos.
- No inventar requisitos de dominio; registrar la decisión pendiente.
- No ocultar fallos de pruebas, migraciones o consola para cerrar una fase.
