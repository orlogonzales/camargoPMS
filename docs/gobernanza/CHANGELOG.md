# Changelog

Los cambios se agrupan por micro-baseline. Este archivo no reemplaza el historial Git.

## Sin publicar

### Microfase UI-3A — Fidelidad Visual Exacta de Formularios Nativos Alina (D-075)

- **Corrección Geométrica y Fidelidad Visual Exacta de Formularios Alina:**
  - Diagnóstico empírico mediante Headless Edge CDP de los componentes originales de Alina (`admin-dashboard/alina/template/default_forms.html` y `select.html`).
  - Eliminación absoluta de overrides artificiales destructivos en `camargo.css` que forzaban `border-radius: 0.375rem !important` y alturas achatadas.
- **Vertical Form With Icon de Alina:**
  - Adopción transversal de la clase canónica `<form class="app-form app-icon-form">` en todas las vistas y modales del PMS.
  - Implementación nativa del separador vertical `|` de 1px entre el icono y el texto mediante el pseudo-elemento `.icon-control::after` (`left: 40px`, `height: 20px`, `background: rgba(var(--dark), 0.6)`).
  - Geometría tipo píldora nativa con `border-radius: var(--app-border-radius)` (20px), padding de `0.8rem 0.75rem 0.8rem 3rem` (48px) e icono centrado con `pointer-events: none`.
- **Select 2 de Alina:**
  - Estandarización de la caja de selección a píldora redondeada `border-radius: var(--app-border-radius)` (20px), borde `1px solid rgba(var(--secondary), 0.4)` y altura nativa de 42px (`calc(2.5rem + var(--bs-border-width) * 2)`).
  - Flecha chevron de Font Awesome 6 `\f078` y botón de limpieza redondeado a 14px (`.select2-selection__clear`) con fondo tenue rojo `rgba(var(--danger), 0.2)`.
  - Erradicación de clases `form-control-sm` y `form-select-sm` en campos dentro de `.icon-control` o con `.basic-select2`.
- **Verificación Automatizada e Inmutabilidad:**
  - Suite automatizada `tests/test_ui3a_fidelidad_alina.php` con 70/70 aserciones PASS.
  - Regresión consolidada completa de fases anteriores: 363/363 PASS.
  - Gran total verificado: 433/433 PASS (100%).
  - Cero dependencias externas / CDN, cero alteraciones funcionales de backend y cero migraciones SQL.

### Microfase UI-3 — Estandarización de Formularios mediante Componentes Nativos Alina

- **Vertical Form With Icon de Alina (D-075):**
  - Implementación sistemática del patrón nativo de Alina en todas las vistas y modales operativos: contenedor `.icon-control.position-relative`, icono centrado verticalmente con clase `ms-3`, e input identado `.ps-5`.
  - Regla CSS defensiva en `camargo.css`: `.icon-control > i { pointer-events: none; }` para asegurar transferencia inmediata del foco al campo al hacer clic en el icono.
  - Estandarización integral en 11 vistas: `/login`, `/caja` (7 modales financieros), `/servicios` (3 pestañas operativas y modales), `/estadias`, `/reservas`, `/disponibilidad`, `/unidades`, `/propiedades`, `/usuarios`, `/configuracion/roles` y `/configuracion/menu`.
- **Select2 4.0.13 y jQuery 3.7.1 100% Locales (Cero CDN):**
  - Copia de librerías locales desde `admin-dashboard/alina/` hacia `public/assets/vendor/jquery/jquery.min.js`, `public/assets/vendor/select/select2.min.css` y `public/assets/vendor/select/select2.min.js`.
  - Cero dependencias externas / CDN en toda la infraestructura de carga (`head.php` y `scripts.php`).
  - Confinamiento estricto de jQuery a inicializar Select2: 0 llamadas `$.ajax()`, 0 manipulaciones DOM en lógica de negocio, 100% Vanilla JS + Fetch API.
- **Controlador Reactivo `camargo-select.js`:**
  - Creación de wrapper global modular `window.CamargoSelect` con métodos defensivos (`init`, `initElement`, `reinit`, `setValue`).
  - Configuración mandatoria de `dropdownParent: $el.closest('.modal')` para prevenir recortes de dropdown y trampas de foco en modales Bootstrap 5.
  - Sincronización automática con PristineJS mediante redespacho de eventos nativos `input` y `change` (`bubbles: true`).
  - Detección de mutaciones dinámicas en el DOM mediante `MutationObserver` y soporte reactivo en eventos `shown.bs.modal`.
- **Estilos Visuales de Select2 en `camargo.css`:**
  - Sustitución de Tabler Icons por flecha Font Awesome 6 (`\f078` fa-chevron-down).
  - Altura estandarizada a 2.35rem alineada con inputs nativos Alina.
  - Eliminación de bordes punteados (`dashed`/`dotted`) en selecciones simples y múltiples.
  - Integración de bordes de error con PristineJS (`.has-danger .select2-selection`).
- **Radios, Checkboxes y Switches Nativos:**
  - Homogeneización de controles mediante clases nativas Alina: `.form-check.d-flex.align-items-center.gap-1` y `.form-check-input.f-s-18.mb-1` en matrices de permisos, interruptores de configuración del sistema y filtros.
- **Erradicación Absoluta de Degradados:**
  - Eliminación del 100% de clases `btn-gradient-*` y `bg-gradient-*` en todo `app/Vistas/` (0 coincidencias en auditoría de código).
  - Reemplazo por estilos canónicos sólidos / outline de Bootstrap 5 / Alina e insignias suaves `bg-light-*` con texto `f-w-500` / `f-w-600`.
- **Verificación Automatizada y Preservación de Negocio:**
  - Suite de verificación automatizada `test_ui3_matriz_25.php` (25/25 PASS).
  - Regresión consolidada multi-fase completa: 363/363 PASS.
  - Gran total verificado: 388/388 PASS (100%).

### Microfase FINANCIERO-2 — Cuentas de Folios, Cargos, Pagos, Aplicaciones, Devoluciones y Caja Física

- **Tríada Financiera y Desacoplamiento de Cobros (D-074):**
  - Consagración del principio rector inviolable: `CARGO ≠ PAGO ≠ MOVIMIENTO DE CAJA` y `PAGO ≠ APLICACIÓN DE PAGO`.
  - Cero banderas booleanas (`pagado = 1`): el balance del folio, saldo pendiente del cargo y saldo no aplicado del pago se derivan mediante cálculo aritmético exacto con `BCMath` en `DECIMAL(15,2)`.
  - Exclusiones estrictas respetadas: CERO facturación electrónica / SUNAT, CERO IGV inventado (0.00 por defecto salvo alícuota explícita), CERO cuentas por pagar a proveedores externos, CERO eliminación física (`DELETE = 0`).
- **Folios Comerciales 1:1 por Reserva:**
  - Cada reserva comercial dispone de exactamente una cuenta/folio financiero principal (`cuentas_folios.reserva_id UNIQUE`).
  - Cargos imputados a la cuenta con puntero opcional `estadia_id NULL`, permitiendo estados de cuenta consolidados por reserva y desglosados por habitación.
- **Sincronización Transaccional Automática:**
  - Cargos de alojamiento devengados automáticamente al confirmar reservas comerciales.
  - Cargos de servicios sincronizados atómicamente con el ciclo operativo: `SOLICITADO`/`CONFIRMADO` $\rightarrow$ `PROVISIONAL`, `EJECUTADO` $\rightarrow$ `DEVENGADO`, y `CANCELADO` $\rightarrow$ `ANULADO`.
- **Caja Física, Turnos y Arqueo Determinista:**
  - Exigencia estricta de sesión de caja física abierta para cobros o devoluciones en efectivo (`EFECTIVO`).
  - Arqueo determinista de cierre computando la diferencia entre el dinero contado declarado y el esperado por el sistema:
    - $\Delta = 0.00$ $\rightarrow$ `CUADRADA`.
    - $\Delta > 0.00$ $\rightarrow$ `SOBRANTE` (justificación obligatoria de 10 a 500 caracteres).
    - $\Delta < 0.00$ $\rightarrow$ `FALTANTE` (justificación obligatoria de 10 a 500 caracteres).
  - Movimientos manuales de caja tipados: `INGRESO_AJUSTE` y `EGRESO_GASTO_MENOR` con motivo obligatorio y actor responsable.
- **Cuentas Bancarias vs Medios Electrónicos:**
  - Depósitos y transferencias exigen cuenta bancaria activa. Tarjetas y billeteras digitales operan con referencia externa y cuenta opcional nullable.
- **Reversiones y Devoluciones Formalizadas:**
  - Reversión compensatoria de aplicaciones de pago (`reversada = 1`) restaurando saldos pendientes sin alterar los registros originales.
  - Devoluciones formalizadas (`devoluciones_cuenta`) reduciendo saldo del pago con motivo justificado y egreso de caja física si es en efectivo.
- **Interfaz Alina y Experiencia de Usuario (D-071):**
  - Módulo completo de Tesorería en `/caja` con KPIs en tiempo real, pestañas de Cuentas/Folios y Turno de Recepción, y 7 modales operativos.
  - Font Awesome 6.3.0 exclusivo, Flatpickr, Variants of badge de Alina (`bg-light-*`), 0 dotted, 0 dashed, Vanilla JS modular (`gestion-caja.js`), PristineJS y SweetAlert2.
- **Persistencia Relacional (Migración 017):**
  - 11 nuevas tablas: `cajas_fisicas`, `cuentas_bancarias`, `metodos_pago`, `sesiones_caja`, `movimientos_caja`, `movimientos_bancarios`, `cuentas_folios`, `cargos_cuenta`, `pagos_cuenta`, `aplicaciones_pago`, `devoluciones_cuenta`.
  - 8 nuevos permisos RBAC: `caja.ver`, `caja.aperturar`, `caja.cerrar`, `caja.movimientos`, `caja.cobrar`, `caja.aplicar`, `caja.devolver`, `caja.reversar`.
  - Opción de menú de nivel 2: `caja` con ruta `/caja` bajo categoría principal `finanzas`.

### Microfase SERVICIOS-1 — Catálogo de Servicios, Proveedores, Consumos y Traslados

- **Separación Ontológica Estricta (D-073):**
  - Principio rector inviolable: `PROVEEDOR ≠ SERVICIO ≠ SERVICIO CONTRATADO ≠ RESERVA ≠ ESTADÍA`.
  - Exclusión taxativa: cero pagos, cero caja, cero facturación electrónica SUNAT, cero cálculo financiero prematuro y cero eliminación física (`DELETE = 0`).
- **Maestro Independiente de Proveedores:**
  - Tabla `proveedores` administrable para prestadores externos, clasificados en `EMPRESA` (RUC / razón social) y `PERSONA_NATURAL` (vinculación opcional al maestro central de `personas` para no duplicar identidad).
  - Principio `PROVEEDOR ≠ OPERACIÓN INTERNA`: no se crea proveedor ficticio "Interno". Cuando un servicio es ejecutado con personal o recursos propios de Camargo Hostelería, `es_operacion_interna = 1` y `proveedor_id` permanece obligatoriamente en `NULL`.
- **Integridad Estructural en Base de Datos (CHECK Constraint):**
  - Regla formal implementada a nivel de motor MySQL 8.4 InnoDB (`chk_sc_coherencia_operacion_interna`):
    `((es_operacion_interna = 1 AND proveedor_id IS NULL) OR (es_operacion_interna = 0 AND proveedor_id IS NOT NULL))`.
- **Matriz de Homologación de Proveedores y Unicidad de Preferente:**
  - Tabla asociativa `servicio_proveedores` con costo pactado (`costo_pactado DECIMAL(15,2)`), plazo de pago en días, código de referencia del proveedor y condición de preferente (`es_preferente`).
  - Restricción de unicidad estricta en base de datos para proveedor preferente: columna virtual generada `uq_preferente` indexada por `uq_sp_servicio_preferente`, impidiendo más de un preferente activo por servicio tanto en concurrencia como transaccionalmente.
- **Catálogo Maestro de Servicios:**
  - Catálogo normalizado con `categorias_servicio` (8 categorías seeded) y `modalidades_cobro_servicio` (6 modalidades seeded, incluyendo explícitamente `POR_UNIDAD`).
  - Configuración con código canónico autogenerado (`SERV-CAT-XXX`), precio de venta referencial y flags operativas (`requiere_proveedor_externo`, `es_traslado`).
- **Contratación e Imputación de Consumos:**
  - Reserva comercial obligatoria (`reserva_id NOT NULL`): no se admiten consumos desanclados.
  - Imputación a estadía opcional pero coherente: si se proporciona `estadia_id`, el sistema verifica bajo transacciones con bloqueo pesimista (`FOR UPDATE`) que la estadía pertenezca a la misma reserva y no se encuentre `ANULADA` (emitiendo HTTP 422 si no coincide).
  - Snapshot económico y descriptivo inmutable (D-010 / D-069): se congelan `concepto_servicio`, `cantidad`, `precio_unitario`, `costo_unitario`, `subtotal`, `impuesto_total = 0.00` y `total = subtotal` (`moneda_codigo = 'PEN'`). Modificaciones posteriores del catálogo o costos de proveedor no alteran operaciones emitidas.
- **Ciclo Operativo y Prohibición Estricta en Servicios Ejecutados:**
  - Estados: `SOLICITADO`, `CONFIRMADO`, `EJECUTADO`, `CANCELADO`.
  - **Bloqueo Vinculante:** PROHIBIDO cancelar un servicio que ya ha sido `EJECUTADO` físicamente (rechazo riguroso con `EstadoServicioInvalidoExcepcion` / HTTP 422).
  - Transición a `EJECUTADO` registra instante UTC técnico (`ejecutado_en`) y actor ejecutor (`ejecutado_por_actor_id`).
  - Cancelación justificada requiere motivo explícito, registrando instante UTC técnico y actor cancelador.
- **Extensión Especializada 1:1 de Traslados (Transfers):**
  - Tabla `servicio_traslados` vinculada 1:1 a `servicios_contratados` con `UNIQUE(servicio_contratado_id)`.
  - Soporta traslados de `LLEGADA` y `SALIDA`, capturando origen y destino generalizados (aeropuerto, terminal, estación, propiedad, centro u otra dirección libre), número de vuelo/transporte, cantidad de pasajeros y equipaje, conductor asignado y vehículo.
  - Rollback transaccional atómico: cualquier fallo en los datos logísticos revierte integralmente la cabecera del servicio contratado.
- **Interfaz Alina y Experiencia de Usuario (D-071):**
  - Módulo completo bajo `/servicios` con 4 pestañas operativas (Consumos Imputados, Catálogo de Conceptos, Directorio de Proveedores, Logística de Traslados).
  - Conformidad estricta con D-071: Font Awesome 6.3.0 exclusivo, Flatpickr con formato visual d/m/Y y envío canónico Y-m-d, Badges oficiales de Alina (`bg-light-success`, `bg-light-info`, `bg-light-primary`, `bg-light-warning`, `bg-light-danger`, `bg-light-secondary`), 0 dotted, 0 dashed, Vanilla JS nativo modular, PristineJS y SweetAlert2.
- **Persistencia Relacional (Migración 016):**
  - 7 nuevas tablas: `categorias_servicio`, `modalidades_cobro_servicio`, `proveedores`, `servicios`, `servicio_proveedores`, `servicios_contratados`, `servicio_traslados`.
  - 5 nuevos permisos RBAC: `servicios.ver`, `servicios.gestionar`, `servicios.contratar`, `servicios.ejecutar`, `servicios.cancelar`.
  - Opción de menú dinámica de nivel 2: `servicios_catalogo` bajo `reservas`.

### Microfase ESTADÍAS-1 — Check-in, Registro de Huéspedes y Ciclo Operativo de Estancias

- **Separación Ontológica Estricta (D-072):**
  - Principio rector inviolable: `RESERVA ≠ ESTADÍA ≠ ARRENDAMIENTO`.
  - La reserva formaliza el acuerdo comercial; la estadía formaliza la ocupación física real de la unidad habitacional; el arrendamiento se mantiene reservado a contratos de largo plazo.
  - Exclusión taxativa de esta fase: pagos, caja, facturación electrónica, consumos, servicios adicionales y arrendamientos.
- **Multiunidad Operativa (1 Reserva : N Estadías Físicas Independientes):**
  - Cada unidad de una reserva confirmada genera una estadía física independiente con su propio check-in, llaves, lista de ocupantes y check-out.
  - Restricción única en base de datos: `UNIQUE KEY uq_estadias_reserva_unidad (reserva_unidad_id)`.
  - El agregado comercial `reservas` permanece en estado `CONFIRMADA`: el estado operativo de la reserva es derivado (`SIN_CHECKIN`, `PARCIAL_EN_CURSO`, `COMPLETA_EN_CURSO`, `FINALIZADA`).
- **Validación de Walk-in Diferido:**
  - El check-in requiere indispensablemente una reserva comercial confirmada previa. Se rechaza check-in sobre reservas `PENDIENTE`, `CANCELADA` o `EXPIRADA` con `EstadoReservaInvalidoExcepcion` (HTTP 422).
- **Control Estricto de Capacidad y Huésped Responsable:**
  - Bloqueo estricto de capacidad física: $1 \le \text{huéspedes} \le \text{capacidad\_personas}$ (HTTP 422 si se supera, sin sobrecapacidad autorizada).
  - Designación obligatoria de exactamente 1 huésped responsable (`es_responsable = 1`) perteneciente a la lista de ocupantes.
  - Todos los ocupantes vinculados al maestro central de `personas` (`estadia_huespedes.persona_id`), con prevención de duplicados (`UNIQUE(estadia_id, persona_id)`).
- **Ciclo de Vida Operativo e Inmutabilidad Histórica:**
  - Estados: `EN_CURSO`, `FINALIZADA`, `ANULADA`.
  - Principio `CHECK-OUT ≠ DELETE` y `ANULACIÓN ≠ DELETE`: cero eliminación física (`DELETE = 0`) en tablas `estadias` y `estadia_huespedes`; claves foráneas con `ON DELETE RESTRICT` (cero borrado en cascada).
  - Anulación excepcional con justificación obligatoria (1-255 caracteres) y preservación histórica para auditoría.
  - Check-out anticipado o tardío con registro del instante real técnico UTC (`DATETIME` con `gmdate`) sin recalcular noches comerciales (D-066).
- **Identificación de Llaves y Accesos:**
  - Campo plano `identificador_llave VARCHAR(50) NULL`, registrando código de tarjeta magnética o número de llave física, sin acoplamiento a domótica en esta fase.
- **Interfaz Alina y Experiencia de Usuario (D-071):**
  - Tablero de recepción en `/estadias` con KPIs en tiempo real, tabla interactiva, filtros combinados y modales de Check-in, Check-out, Anular y Detalle con gestión de huéspedes.
  - Vanilla JS nativo, validación PristineJS, notificaciones SweetAlert2 y badges oficiales Alina (`Variants of badge` con `bg-light-success`, `bg-light-secondary`, `bg-light-danger`).
- **Persistencia Relacional (Migración 015):**
  - Tablas `estadias` y `estadia_huespedes`, 5 permisos RBAC (`estadias.*`) asignados a `SUPERADMINISTRADOR` y opción de menú dinámico bajo 'reservas'.


### Microfase UI-2A — Estandarización obligatoria de Badges y Chips de Alina

- **Estandarización de Badges y Chips Oficiales de Alina (D-071):**
  - **Componentes Permitidos:** Adopción obligatoria de *Variants of badge* de Alina (`badge bg-light-*`, `badge text-bg-*`) para estados compactos y contadores, y *Variants of chip* de Alina (`chip bg-light-*`, `chip text-bg-*`) para categorías, clasificaciones, tipos de unidad física, atributos y tags.
  - **Prohibición Estricta de Dotted y Dashed:** Conteo absoluto de badges punteados o discontinuos = 0 (`dotted` = 0, `dashed` = 0) en toda la aplicación.
  - **Erradicación de Clases Bootstrap Crudas:** Eliminadas todas las ocurrencias directas de `bg-*-subtle` en vistas y módulos JavaScript propios, reemplazándolas por las variantes oficiales de Alina (`bg-light-*`).
  - **Iconografía Unificada Font Awesome 6:** Todos los iconos embebidos dentro de badges y chips provienen exclusivamente de Font Awesome 6 Free (`fa-solid fa-*`), con 0 Tabler Icons.
  - **Mapa Semántico Vinculante:**
    - `SUCCESS` (`bg-light-success`): `ACTIVO`, `CONFIRMADA`, `DISPONIBLE`.
    - `WARNING` (`bg-light-warning`): `PENDIENTE`, `MANTENIMIENTO`, `SUPERADMIN`.
    - `DANGER` (`bg-light-danger`): `CANCELADA`, `BLOQUEADO`, `OCUPADO`, `SUSPENDIDO`.
    - `INFO` (`bg-light-info`): informativo neutral, sistema, manual, tipo de unidad.
    - `SECONDARY` (`bg-light-secondary`): `INACTIVO`, `EXPIRADA`, `LIBERADO`, contadores y códigos auxiliares.
    - `LIGHT` / `DARK`: contraste o códigos destacados de propiedad/unidad.
  - **Infraestructura Centralizada:**
    - Backend: Clase `\CamargoPMS\Nucleo\Insignia` y funciones globales `insignia_badge()`, `insignia_chip()` e `insignia_estado()`.
    - Frontend: Módulo `window.CamargoInsignia` en `camargo-layout.js` con métodos `badge()`, `chip()`, `estado()` y `resolverClase()` en Vanilla JS puro (0 jQuery).
  - **Gobernanza e Invariantes:**
    - Registro de decisión D-071 sección 6 en `DECISIONES.md`, `FRONTEND.md`, `PLANTILLA-ALINA.md`, `CONVENCIONES.md` y `.skills/camargo-ui-alina/SKILL.md`.
    - Invariables intactos: `admin-dashboard/` intacto (100% inmutable), `.env` intacto, 14 migraciones de BD (0 añadidas).

### Microfase UI-2 — Estandarización transversal obligatoria de recursos de interfaz

- **Estandarización de Iconografía Oficial Font Awesome 6 (D-071):**
  - Adopción exclusiva de Font Awesome 6 Free (v6.3.0) en todo el código propio del sistema.
  - Erradicación total (0 ocurrencias) de Tabler Icons (`ti ti-*`, `ti-*`) en todas las vistas PHP (`app/Vistas/`), layouts, controladores y módulos JavaScript propios (`public/assets/js/`).
  - Actualización de catálogo de iconos de menú en base de datos (`opciones_menu.icono`) y esquema maestro `SQL/camargo_pms.sql` a formato `fa-solid fa-*`.
  - Despliegue de assets locales: `public/assets/vendor/fontawesome/css/all.css` y 8 archivos de fuentes web en `public/assets/fonts/fontawesome/` con política estricta de *Local Assets First* (0 CDNs).
- **Selectores de Fecha Alina Transversales (Flatpickr / D-071):**
  - Implementación del componente oficial Date Picker de Alina (`.camargo-datepicker`) para fechas individuales.
  - Implementación del componente oficial Range Picker de Alina (`.camargo-rangepicker`) para selección de intervalos y períodos en Disponibilidad y Reservas.
  - Controlador modular `public/assets/js/camargo-pickers.js` en Vanilla JS (0 jQuery) con sincronización atómica de inputs canónicos ocultos, despacho de eventos nativos `input` y `change` compatibles con PristineJS y soporte modal/mobile (`disableMobile: true`).
  - **Preservación Inviolable de D-066:** El Range Picker opera puramente como experiencia de usuario (UX); la arquitectura backend conserva `fecha_entrada` y `fecha_salida` independientes como `DATE` (`YYYY-MM-DD`) e intervalo semiabierto $[ \text{entrada}, \text{salida} )$.
- **Cero Dependencia de jQuery:**
  - Código propio 100% en Vanilla JS (ES6+) moderno y nativo.
- **Gobernanza y Documentación:**
  - Registro de decisión vinculante `D-071` en `docs/gobernanza/DECISIONES.md`.
  - Actualización integral de `docs/gobernanza/FRONTEND.md`, `PLANTILLA-ALINA.md`, `CONVENCIONES.md` y `.skills/camargo-ui-alina/SKILL.md`.
  - Preservación íntegra de `admin-dashboard/` (0 modificaciones) y `.env` (0 modificaciones).
  - 0 migraciones de base de datos añadidas (permanece en 001–014).

### Microfase RESERVAS-1A — Corrección Fiscal, Configuración de Hold y Semántica D-061

- **Corrección de Política Fiscal y Snapshot Tributario (D-069 / D-070):**
  - **Eliminación de Asunción Impositiva:** Suprimida cualquier referencia o regla que asuma que toda reserva aplica universalmente 18% IGV.
  - **Contrato Fiscal Provisorio:** Al no existir aún fuente impositiva formal ni categorización tributaria en el PMS, se establece provisoriamente `impuesto = 0.00` (ningún impuesto aplicado por el PMS, sin calificar la operación como exonerada o inafecta) y `total = subtotal`.
  - **Aritmética Exacta con BCMath:** Implementación del método canónico `redondearBc()` sobre strings con `ROUND_HALF_UP`, eliminando cualquier casting o conversión intermedia a tipos de coma flotante binaria (`(float)` o `round()` nativo).
  - **Snapshot Inmutable:** El snapshot de la reserva (`subtotal`, `impuesto`, `total`) y de las unidades asociadas conserva las columnas tributarias preparadas para cuando se integre un motor impositivo formal, manteniendo los valores congelados en el instante de emisión.
- **Corrección de Parámetro Operacional de Hold:**
  - **Eliminación de Default Arbitrario:** Eliminado el valor por defecto de 30 minutos del código, migraciones y seeds (`SQL/migraciones/014_reservas.sql`, `SQL/camargo_pms.sql` y tabla `configuraciones` en base de datos real quedan con `valor = NULL` y `valor_predeterminado = NULL`).
  - **Comportamiento sin Configuración:** Creación de la excepción de dominio `ConfiguracionFaltanteExcepcion` (HTTP 422). Si se intenta crear una reserva en estado `PENDIENTE` y el parámetro `reservas.duracion_hold_minutos` no ha sido definido explícitamente por el negocio, la operación es rechazada limpiamente sin recurrir a fallbacks inventados ni generar errores 500 genéricos.
  - **Reservas Confirmadas:** Las reservas creadas directamente en estado `CONFIRMADA` no requieren hold (`expira_en = NULL`), operando sin depender del parámetro de expiración.
- **Corrección de Gobernanza y Semántica D-061:**
  - **Desacoplamiento de Preservación Histórica:** Separado el principio de ciclo de vida de Reserva (`CANCELACIÓN / EXPIRACIÓN ≠ DELETE`) del contrato D-061.
  - **Contrato Canónico D-061 (`ACTOR ≠ USUARIO`):** D-061 se restringe y formaliza con precisión para la resolución obligatoria del actor ejecutor (`USR_X` para usuario humano autenticado o `CAMARGO_PMS` para procesos de sistema), garantizando de forma inviolable que jamás se asigne un `usuario_id` directamente como `actor_id` por coincidencia numérica.
- **Actualización de Documentación de Gobernanza:**
  - `DECISIONES.md`: Rectificada la decisión D-070 en sus cláusulas fiscal, de hold y de autoría D-061.
  - `BASE-DATOS.md`: Documentado el estado del parámetro de hold sin default inventado y el contrato fiscal provisional.
  - `ROADMAP.md`: Actualizada la descripción del módulo de reservas directas.

### Fase RESERVAS-1 — Núcleo Transaccional de Reservas Directas

- **Dominio Transaccional y Persistencia Relacional (D-070):**
  - **Entidades de Dominio:** Implementación de `Reserva` y `ReservaUnidad` con tipado estricto, métodos de estado (`retieneInventario()`, `haExpirado()`, `estaActiva()`), normalización y serialización desacoplada de la presentación.
  - **Esquema de Base de Datos:** Migración `014_reservas.sql` aplicada exitosamente, creando las tablas `reservas` (cabecera comercial con 4 Foreign Keys y 4 índices optimizados) y `reserva_unidades` (detalle multiunidad con 2 Foreign Keys y restricción única `(reserva_id, unidad_id)`). Esquema general consolidado en 28 tablas físicas y 35 Foreign Keys.
  - **Ampliación de Inventario Diario:** Extensión de `inventario_diario_unidades.tipo_bloqueo` incorporando el valor `RESERVA`.
  - **Parámetro de Configuración:** Semilla `reservas.duracion_hold_minutos` (30 minutos) registrada en `configuraciones` para el control de holds temporales.
  - **Permisos RBAC:** Semillas `reservas.ver`, `reservas.crear`, `reservas.confirmar`, `reservas.cancelar` y `reservas.expirar` asignadas al rol `SUPERADMINISTRADOR` (catálogo ampliado a 30 permisos).
  - **Navegación Alina:** Registrada opción de menú `reservas_directas` bajo el área de operaciones con ruta `/reservas`.
- **Soporte Multiunidad Nativo (1 Reserva : N Unidades):**
  - Capacidad de reservar una o múltiples unidades en una misma operación atómica.
  - Desglose individual de precio por noche, noches, subtotal e impuesto en `reserva_unidades`.
  - Asignación ordenada deterministamente: `ORDER BY unidad_id ASC, fecha ASC` en `inventario_diario_unidades`.
- **Snapshot Financiero Inmutable (D-069):**
  - Cálculo centralizado exclusivamente en el backend; rechazo estricto de totales enviados por clientes o APIs externas.
  - Aritmética de precisión arbitraria mediante `BCMath` en PHP 8.3 y persistencia en tipos `DECIMAL(15,2)` en MySQL 8.4.
  - Moneda canónica `PEN` (ISO 4217), subtotal, 18% IGV y total congelados en el instante de emisión; inmutabilidad histórica garantizada frente a cambios futuros de tarifas maestras o alícuotas tributarias.
  - Redondeo mercantil `ROUND_HALF_UP` en el límite contractual final preservando precisión intermedia sin redondeo prematuro acumulativo.
- **Ciclo de Estados y Expiración Automática de Holds:**
  - Ciclo de estados formalizado: `PENDIENTE`, `CONFIRMADA`, `CANCELADA`, `EXPIRADA`.
  - Retención de inventario en `PENDIENTE` y `CONFIRMADA`; liberación atómica e inmediata en `CANCELADA` y `EXPIRADA`.
  - Expiración de holds vencidos: método `expirarReservasPendientes()` en `ReservaServicio` con procesamiento en lote, reversión atómica de inventario diario y trazabilidad en auditoría.
  - Endpoint web seguro `POST /reservas/expirar` y comando de consola CLI `bin/expirar-reservas.php` para integración en cron o tareas programadas del sistema.
- **Concurrencia, Locking y Rollback Integral (D-067):**
  - Transacciones ACID sobre motor MySQL 8.4.3 LTS InnoDB.
  - Captura explícita de errores 1062 (clave duplicada), 1205 (lock wait timeout) y 1213 (deadlock) con `ROLLBACK` total y emisión de `ConflictoDisponibilidadExcepcion` (HTTP 409 Conflict).
  - Rollback multiunidad completo: si la unidad $N$ de una reserva multinoche colisiona, revierte íntegramente las unidades previas sin dejar noches huérfanas en el inventario.
- **Trazabilidad y Cero Eliminación Física (D-061):**
  - Principio `CANCELACIÓN / EXPIRACIÓN ≠ DELETE`: cero eliminación física en `reservas` y `reserva_unidades`.
  - Cancelación exige motivo obligatorio (1-255 caracteres).
  - Auditoría transversal registrando eventos de dominio con autoría humana resuelta (`USR_X`) o actor de sistema.
- **Interfaz Alina y Controladores:**
  - Controlador `ReservaControlador` protegiendo cada acción con RBAC (`reservas.ver`, `reservas.crear`, etc.) y validación CSRF.
  - Vista Alina responsive en `app/Vistas/reservas/index.php` con KPIs operativos, filtros en tiempo real, modales de reserva multiunidad reactivo, detalle con desglose de snapshot y modal de cancelación con validación.
  - JavaScript moderno `public/assets/js/gestion-reservas.js` en Vanilla JS (0 jQuery), Fetch API, PristineJS y SweetAlert2.
- **Verificación Automatizada Completa:**
  - Matriz de dominio e integración: 50/50 PASS (`test_reservas_matriz_50.php`).
  - Suite de concurrencia y locking: 6/6 PASS (`test_reservas_concurrencia.php`).
  - Suite HTTP E2E Real Apache HTTPS: 15/15 PASS (`test_e2e_reservas.php`).
  - Regresiones históricas: 205/205 PASS.
  - Total acumulado del sistema: 725 casos únicos / 731 ejecuciones brutas (100% PASS).
- **Invariantes del Incremento:**
  - `admin-dashboard/` y `.env` intactos.
  - Paridad 100% entre `SQL/camargo_pms.sql` y `SQL/migraciones/001_...` a `014_reservas.sql`.

### Microfase GATE FINANCIERO-1 — Definición del Contrato Monetario y Cierre de P-005

- **Formalización de la Decisión D-069 (Cierre Definitivo de P-005):**
  - **Moneda Canónica ISO 4217:** Adopción oficial del Sol peruano (`PEN`) como moneda canónica operativa de Camargo PMS.
  - **Desacoplamiento de Símbolo:** Principio `MONEDA ≠ SÍMBOLO`. El símbolo comercial `'S/'` queda desacoplado del dominio y reservado exclusivamente a la capa visual de interfaz (`Vistas/`), impidiendo su almacenamiento o concatenación en base de datos.
  - **Prohibición Terminante de Coma Flotante:** Demostradas empíricamente las fallas de precisión binaria IEEE 754 de `FLOAT`/`DOUBLE` (`0.1 + 0.2 = 0.30000000000000004`, pérdida de redondeo en `1.005`). Se prohíben en DDL, consultas SQL y modelos PHP.
  - **Escala y Precisión Numérica Diferenciada:**
    - `DECIMAL(15,2)` en MySQL para importes comerciales y saldos finales (céntimos).
    - `DECIMAL(15,4)` en MySQL para tarifas unitarias base, consumos de suministros y tasas de impuestos (ej. `0.1800` para 18% IGV).
  - **Aritmética Exacta en PHP:** Implementación mediante la extensión nativa `BCMath` y cadenas numéricas (`bcadd`, `bcsub`, `bcmul`, `bcdiv`, `bccomp`) sobre PHP 8.3.30.
  - **Estándar de Redondeo ROUND_HALF_UP:** Adopción del redondeo mercantil estándar hacia arriba en el límite exacto `.005` para importes positivos.
  - **Precisión Intermedia sin Redondeo Prematuro:** Demostrado que redondear cada ítem individualmente a 2 decimales versus redondear sobre la suma agregada genera descuadres de céntimos. Se establece como regla preservar precisión ($\ge 4$ decimales) en cálculos intermedios y aplicar `ROUND_HALF_UP` en el límite contractual final.
  - **Autoridad Financiera Centralizada en Backend:** La capa de `Servicios` es la única autoridad de cálculo. El frontend es puramente estimativo y se rechazan categóricamente totales o precios enviados a ciegas por el cliente.
  - **Preparación Multimoneda:** Exigencia obligatoria de la columna `moneda_codigo VARCHAR(3)` en toda tabla o entidad con importes financieros.
  - **Inmutabilidad Histórica y Snapshots:** Las transacciones emitidas congelan snapshots de tarifas pactadas e impuestos vigentes. Cero recálculo ante variaciones posteriores de catálogo o de alícuotas fiscales.
  - **Determinismo del Saldo:** Soporte de pagos parciales donde $\text{Saldo Pendiente} = \text{Total Contratado} - \sum(\text{Pagos Válidos})$.
  - **Preservación Contable:** Principio `ANULACIÓN / REVERSO ≠ DELETE`. Cero borrado físico de pagos o movimientos financieros; reversos mediante estados de anulación o contra-asientos con trazabilidad transversal bajo D-061.
- **Verificación Técnica Automatizada (FIN-01 a FIN-20):**
  - Implementación y ejecución de la suite técnica `test_finanzas_p005.php` verificando los 20 requisitos monetarios y fiscales sobre MySQL 8.4.3 LTS y PHP 8.3.30 (20/20 PASS).
- **Reconciliación Matemática Canónica:**
  - 16 suites ejecutadas en verde (0 fallos).
  - 560 casos independientes de dominio e integración (540 previos + 20 FIN-01..20).
  - 94 casos HTTP E2E reales contra servidor Apache HTTPS.
  - 654 casos tabulados / 660 ejecuciones brutas acumuladas (100% PASS).
- **Invariantes del Incremento:**
  - Cero código de producción prematuro (no se implementaron servicios de reservas ni caja).
  - Cero migración 014 (migraciones permanecen en 001–013).
  - `admin-dashboard/` y `.env` intactos.

### Microfase DISPONIBILIDAD-1A — Verificación final de concurrencia, motor SQL y reconciliación

- **Confirmación Empírica del Motor y Versión de Base de Datos:**
  - Comprobado mediante `SELECT VERSION()` y `SELECT @@version_comment` que el motor en ejecución es **MySQL Community Server 8.4.3 LTS (GPL)**.
  - Rectificadas referencias documentales a fin de reflejar con exactitud la base instalada.
- **Verificación Rigurosa de Contratos de Concurrencia 1205 y 1213:**
  - **G-1205 (Lock Wait Timeout Exceeded):** Inducido en MySQL 8.4.3 configurando `innodb_lock_wait_timeout = 1` y contención por clave de inventario. Confirmada captura de error nativo `["HY000", 1205]`, rollback integral en servicio (`inTransaction = false`), y traducción a `ConflictoDisponibilidadExcepcion` (HTTP 409).
  - **G-1213 (Deadlock Detected):** Inducido ciclo real de interbloqueo circular entre dos procesos concurrentes en InnoDB. Confirmada detección inmediata por el Deadlock Detector arrojando `["40001", 1213]`. Captura en `DisponibilidadServicio`, reversión atómica y traducción a `ConflictoDisponibilidadExcepcion` (HTTP 409).
- **Ejecución y Reconciliación Integral Suite por Suite:**
  - Ejecución en verde de 15 suites de prueba cubriendo todo el histórico del PMS: Identidad (27/27), Usuarios (40/40), Actor (6/6), Roles (40/40), Rol-Hist (10/10), Menú (9/9), Auditoría (40/40), Sanitizador (62/62), Configuración (40/40), Propiedades (40/40), Prop-Hist (10/10), Unidades (40/40), Uni-Hist (10/10), Disponibilidad (40/40), Concurrencia Productiva (5/5), Contratos 1205/1213 (2/2) y E2E Apache HTTPS (94/94).
- **Reconciliación Matemática Canónica:**
  - 540 casos independientes de dominio e integración (493 previos + 40 DISP + 5 CONC-PROD + 2 G-1205/G-1213).
  - 94 casos HTTP E2E reales contra servidor Apache HTTPS.
  - 640 ejecuciones acumuladas con 0 fallos (100% PASS).

### Fase DISPONIBILIDAD-1 — Motor Central de Disponibilidad e Inventario Diario

- **Arquitectura e Integridad Transaccional (D-066, D-067 y D-068):**
  - **Inventario Diario Sparse:** Implementada la tabla `inventario_diario_unidades` en motor InnoDB con restricción inviolable `UNIQUE KEY uq_inventario_unidad_fecha (unidad_id, fecha)` como última línea de defensa ante sobreventas y carreras concurrentes.
  - **Inserción Determinista y Rollback Íntegro:** Operaciones de ocupación multinoche procesadas en orden `ORDER BY unidad_id ASC, fecha ASC`, con captura de errores de clave duplicada (1062), lock wait timeout (1205) y deadlock (1213), rollback completo y traducción a `ConflictoDisponibilidadExcepcion` (HTTP 409 Conflict).
  - **Registro Maestro de Bloqueos:** Tabla `bloqueos_unidad` para la gestión de indisponibilidades técnicas (`MANTENIMIENTO`) y administrativas (`BLOQUEO_MANUAL`), con trazabilidad de actores (`creado_por_actor_id`, `liberado_por_actor_id`), marcas temporales y ciclo de vida histórico (`ACTIVO` ↔ `LIBERADO`) sin eliminación física (`DELETE` = 0 en registro maestro).
  - **Liberación Atómica de Inventario:** El método `liberar()` actualiza el estado del bloqueo y elimina atómicamente todas sus filas asociadas en `inventario_diario_unidades` (`DELETE ... WHERE origen_tipo = 'BLOQUEO_MANUAL' AND origen_id = ?`), restableciendo la disponibilidad de forma inmediata.
  - **Semántica Temporal Hotelero:** Intervalo semiabierto $[\text{fecha\_entrada}, \text{fecha\_salida})$ donde la noche de salida queda liberada para check-in simultáneo sin falso conflicto. Noches: $\text{salida} - \text{entrada} \ge 1$.
  - **Resolución de Zona Horaria IANA:** Campo `propiedades.zona_horaria` evaluado prioritariamente, con fallback al parámetro central `operacion.zona_horaria_predeterminada` (`America/Lima`) y validación segura contra identificadores no canónicos.
  - **Preservación Estricta de Gobernanza P-005:** Cero columnas o nociones de tarifas, precios, costos, monedas, redondeos o impuestos en las tablas o entidades de disponibilidad.
- **Base de Datos y Migración 013:**
  - Migración `SQL/migraciones/013_disponibilidad.sql` ejecutada con paridad exacta al 100% en `SQL/camargo_pms.sql` (26 tablas, 29 Foreign Keys).
  - Permisos RBAC sembrados y asignados a `SUPERADMINISTRADOR`: `disponibilidad.ver`, `disponibilidad.bloquear`, `disponibilidad.liberar`.
  - Opción de menú sembrada: `disponibilidad_calendario` ('Disponibilidad', ruta `/disponibilidad`, icono `ti ti-calendar-event`, orden 3) bajo la categoría `propiedades`.
  - Parámetro de sistema sembrado: `'operacion.zona_horaria_predeterminada'` = `'America/Lima'`.
- **Capa de Backend (MVC):**
  - Modelos de dominio: `BloqueoUnidad` e `InventarioDiario` con tipado estricto y métodos de serialización.
  - Modelo `Propiedad`: soporte de `zonaHoraria` preservando compatibilidad posicional del constructor.
  - Excepciones de dominio: `ConflictoDisponibilidadExcepcion` (409), `IntervaloInvalidoExcepcion` (422), `BloqueoNoEncontradoExcepcion` (404).
  - Repositorio `DisponibilidadRepositorio`: consultas de disponibilidad sparse, inserción determinista atómica, eliminación atómica por origen, CRUD de bloqueos y matriz de inventario.
  - Servicio `DisponibilidadServicio`: orquestador de lógica de negocio, resolución de huso horario, validación de intervalos, cálculo de noches, transacciones ACID con captura de 1062/1205/1213, auditoría transversal D-061 y matriz mensual.
  - Controlador `DisponibilidadControlador`: 7 endpoints HTTP asegurados con `AutorizacionIntermediario` (`index`, `consultar`, `bloquear`, `liberar`, `bloqueosJson`, `matrizJson`).
- **Interfaz Alina y Experiencia de Usuario:**
  - Vista Alina responsiva en `app/Vistas/disponibilidad/index.php` con navegación por pestañas (Consulta, Matriz/Rack mensual, Bloqueos activos), KPIs en tiempo real (Totales, Disponibles, Bloqueadas, Tasa) y modales operativos de bloqueo y liberación.
  - Módulo JavaScript Vanilla moderno en `public/assets/js/gestion-disponibilidad.js` (0 dependencias jQuery), consumo asíncrono con Fetch API, protección CSRF, PristineJS v1.1.0 para validación cliente y diálogos interactivos con SweetAlert2.
- **Batería de Pruebas Automatizadas:**
  - Suite de Concurrencia Productiva sobre BD Real: CONC-PROD-01..05 (5/5 PASS).
  - Matriz Formal de Disponibilidad e Inventario: DISP-01..40 (40/40 PASS).
  - Suite HTTP E2E Real contra servidor Apache HTTPS: E2E-DISP-01..12 (12/12 PASS).

### Gate Operativo-1 / Gate Operativo-1A — Definición del Tiempo Hotelero y Concurrencia de Disponibilidad (P-004 + P-006) [HOMOLOGADO — ef6a806]

- **Cierre Formal de Decisión P-004 (D-066 — Modelo Temporal Hotelero y Zonas Horarias IANA):**
  - Formalizada la separación ontológica tripartita: `INSTANTE ≠ FECHA HOTELERA ≠ HORARIO OPERACIONAL`.
  - Instantes técnicos (creación, auditoría, sesiones, tokens, webhooks) se almacenan normalizados en UTC (`TIMESTAMP`).
  - Fechas hoteleras (noches de estancia) se modelan como `DATE` local de la propiedad; no son timestamps ni se convierten a UTC.
  - Horarios de check-in y check-out se gobernarán mediante parámetros configurables del PMS (`operacion.hora_checkin_predeterminada` y `operacion.hora_checkout_predeterminada`), cuyos valores iniciales se definirán operativamente antes de producción; no alteran qué noches están ocupadas.
  - Prohibidos los offsets fijos (`UTC-5`). Se adoptan identificadores canónicos IANA con zona predeterminada del PMS en `America/Lima` (`operacion.zona_horaria_predeterminada`).
  - Preparada la tabla `propiedades` para incorporar la columna nullable `zona_horaria VARCHAR(50)` en `DISPONIBILIDAD-1`, heredando la zona del PMS si es nula.
  - Intervalo de estancia modelado matemáticamente como semiabierto: $[\text{fecha\_entrada}, \text{fecha\_salida})$. El día de salida queda libre para check-in simultáneo sin conflicto.
  - Contrato de noches: $\text{noches} = \text{fecha\_salida} - \text{fecha\_entrada}$ con $\text{noches} \ge 1$ en el motor ordinario.
- **Cierre Formal de Decisión P-006 (D-067 — Concurrencia de Disponibilidad e Inventario Diario):**
  - Aprobado el **Modelo Híbrido**: desacoplamiento entre contrato comercial (`reservas`/`bloqueos`) e inventario diario físico (`inventario_diario_unidades`).
  - Semántica *sparse*: inventario diario registra únicamente noches ocupadas/bloqueadas (cero pregeneración de años vacíos); disponibilidad formalizada como ausencia de registro para `(unidad_id, fecha)` en el rango semiabierto.
  - Barrera absoluta de concurrencia: restricción `UNIQUE (unidad_id, fecha)` en InnoDB que previene condiciones de carrera y sobreventa (cero dependencia de lógica en frontend o checks previos desfasados).
  - Atomicidad transaccional y rollback completo ante colisiones (cero reservas parcialmente confirmadas, cero noches huérfanas).
  - Orden determinista de bloqueo: `ORDER BY unidad_id ASC, fecha ASC`, reduciendo sustancialmente el riesgo de deadlocks y adquisiciones cruzadas.
  - Captura y traducción de errores de clave duplicada (1062), lock wait timeouts (1205) y deadlocks (1213) a `ConflictoDisponibilidadExcepcion` (HTTP 409 Conflict), nunca HTTP 500.
  - Liberación atómica de noches por cancelación o expiración de hold temporal (`DELETE FROM inventario_diario_unidades WHERE reserva_id = ?`).
  - Centralización multicanal: Camargo PMS como única fuente de verdad autoritativa para PMS, WordPress, App móvil, OTAs y Webhooks.
- **Micro-fase GATE-OPERATIVO-1A (Corrección de Gobernanza):**
  - Retiradas las horas fijas (15:00 y 11:00) estableciendo parámetros configurables sin valores arbitrarios preasignados.
  - Sustituida la afirmación absoluta sobre deadlocks por la formulación técnica precisa de mitigación mediante orden determinista y captura obligatoria de códigos MySQL 1062, 1205 y 1213 con rollback.
- **Preservación de Decisión P-005:**
  - P-005 (moneda, redondeo e impuestos) se mantiene formal y estrictamente **PENDIENTE** para antes de la fase de tarifas y caja.
- **Prueba Técnica Aislada de Concurrencia:**
  - Harness automatizado (`harness_concurrencia_p006.php`) con 5/5 verificaciones superadas en base de datos efímera aislada (`camargo_pms_concurrencia_p006`): concurrencia directa multiconexión PDO, atomicidad multinoche con rollback, intervalo semiabierto, liberación atómica y cero locks residuales en InnoDB (`innodb_lock_wait_timeout = 2`).
- **Esquema de Base de Datos y Código Productivo:**
  - Cero código productivo nuevo; cero migración 013 (migraciones permanecen en 001–012).

### Fase UNIDADES-1 — Maestro Central de Unidades Físicas y Alojables por Propiedad

- **Arquitectura y Principios de Delimitación de Dominio:**
  - **`PROPIEDAD ≠ UNIDAD`:** La propiedad es el contenedor físico raíz (`propiedades`); la unidad (`unidades`) modela la división física, departamento, habitación o espacio divisible e individualizable con destino de alojamiento. Toda unidad está subordinada obligatoriamente a una propiedad física existente y activa (`propiedad_id INT NOT NULL`, `fk_unidades_propiedad`). Cero unidades huérfanas en el sistema.
  - **`UNIDAD ≠ REGISTRO DESECHABLE`:** Cero eliminación física en toda la arquitectura (`DELETE FROM unidades` = 0). Ciclo de vida operacional gobernado exclusivamente por la alternancia de estados `ACTIVO` e `INACTIVO`. Ausencia absoluta de métodos `eliminar()` en repositorio y servicio, y de acciones de eliminación en controlador. Rutas HTTP DELETE no registradas responden `404 Not Found`.
  - **`UNIDAD ≠ RESERVA / TARIFA / DISPONIBILIDAD`:** Respeto estricto a las decisiones de gobernanza P-004 (zona horaria y corte hotelero), P-005 (moneda, redondeo e impuestos) y P-006 (concurrencia de disponibilidad). Cero atributos o lógica de fechas, check-in/out, calendarios, tarifas monetarias o contratos de arrendamiento en esta fase.
  - **Unicidad de Código Acotada a la Propiedad (D-065):** Restricción de unicidad compuesta `UNIQUE KEY uq_unidades_propiedad_codigo (propiedad_id, codigo)`. Se permite el uso de códigos idénticos (ej. "101") en propiedades distintas, garantizando unicidad estricta al interior de una misma propiedad.
  - **Regla de Negocio de Propiedad Inactiva:** Una propiedad inactiva preserva intactas sus unidades históricas para consulta y trazabilidad, pero bloquea incondicionalmente la creación de nuevas unidades con `DominioReglaExcepcion` (HTTP 422).
- **Base de Datos y Migración 012:**
  - Migración `SQL/migraciones/012_unidades.sql` aplicada con paridad canónica al 100% en `SQL/camargo_pms.sql` (24 tablas en total, 25 foreign keys).
  - Tabla `tipos_unidad`: catálogo clasificador de tipologías físicas (`DEPARTAMENTO`, `HABITACION`, `CASA`, `SUITE`, `BUNGALOW`).
  - Tabla `unidades`: campos físicos `propiedad_id`, `tipo_unidad_id`, `codigo`, `nombre`, `nivel` (piso/planta), `capacidad_estandar`, `capacidad_maxima`, `numero_camas`, `numero_banos`, `descripcion`, `estado`, `creado_en`, `actualizado_en`.
  - Permisos RBAC sembrados: `unidades.ver`, `unidades.crear`, `unidades.editar`, `unidades.cambiar_estado` asignados al rol `SUPERADMINISTRADOR`.
  - Menú de navegación sembrado: submenú `unidades_catalogo` ('Catálogo de Unidades', ruta `/unidades`, orden 2) bajo la categoría principal `propiedades`.
- **Capa de Dominio y Validaciones:**
  - Modelos de dominio `TipoUnidad` y `Unidad` con tipado estricto, métodos de utilidad `estaActiva()`, cálculo de ocupación total `capacidadTotal()` (`capacidad_estandar + capacidad_maxima`), y serialización consistente `haciaArreglo()`, `aArreglo()`, `aArray()`, y recreación `desdeArreglo()`.
  - Excepciones de dominio `UnidadDuplicadaExcepcion` (HTTP 409) y `UnidadNoEncontradaExcepcion` (HTTP 404).
  - Validaciones de dominio en `UnidadServicio`: validación de propiedad activa existente, tipo de unidad existente, unicidad de código por propiedad, longitudes de texto, capacidades no negativas con `capacidad_maxima >= capacidad_estandar`.
- **Servicio y Trazabilidad Transversal D-061:**
  - `UnidadServicio`: punto único de acceso para operaciones sobre unidades físicas.
  - Actualizaciones con cálculo diferencial exacto (`diff`) que previene la emisión de auditoría redundante cuando no hay cambios.
  - Trazabilidad integral de operaciones (`CREAR`, `EDITAR`, `DESACTIVAR`, `ACTIVAR`) en `auditoria` bajo D-061, resolviendo el actor humano (`USR_x`) mediante `resolverActorEjecutor()` y reservando `CAMARGO_PMS` (`id = 1`) para ejecuciones de sistema.
  - Enlace bidireccional en `AuditoriaServicio::listarPorEntidad()` para consultar el historial de auditoría de cualquier entidad del sistema.
- **Controlador, Rutas y Permisos RBAC:**
  - Rutas registradas en `public/index.php`: `GET /unidades`, `GET /unidades/datos`, `GET /unidades/{id}`, `GET /unidades/{id}/perfil`, `POST /unidades`, `PUT /unidades/{id}`, `PATCH /unidades/{id}/estado`, y variantes POST compatibles.
  - Integración en `PropiedadControlador` y vista de perfil `app/Vistas/propiedades/detalle.php`: tabla de unidades asociadas al inmueble y conteos consolidados.
- **Interfaz Alina y Ficha Técnica:**
  - Vista general `app/Vistas/unidades/index.php` con maquetación de tarjetas Alina, tabla dinámica, filtros de búsqueda textual, selector por propiedad, por tipo y por estado, y modal interactivo para creación y edición.
  - Vista de perfil `app/Vistas/unidades/detalle.php` con ficha técnica de la unidad, especificaciones físicas (camas, baños, capacidad estándar y máxima, nivel), tarjeta del inmueble contenedor, trazabilidad de auditoría e indicador visual del principio `PROPIEDAD ≠ UNIDAD`.
  - Script Vanilla JS modular `public/assets/js/gestion-unidades.js` con debounce de búsqueda, paginación dinámica, validación cliente mediante PristineJS v1.1.0 y confirmaciones con SweetAlert2.
- **Pruebas y Verificaciones:**
  - Matriz formal unitaria y de integración `UNI-01` a `UNI-40` (40/40 PASS).
  - Prueba específica de persistencia histórica `UNI-HIST-01` de 10 pasos (10/10 PASS).
  - Suite HTTP E2E Real contra servidor Apache HTTPS `E2E-UNI-01` a `E2E-UNI-12` (12/12 PASS).
  - Regresión integral sin fallos sobre todos los módulos previos del sistema (421/421 verificaciones directas PASS).

### Fase PROPIEDADES-1 — Maestro Central de Propiedades e Inmuebles Físicos

- **Arquitectura y Principio Ontológico de Delimitación Física (`PROPIEDAD ≠ UNIDAD`):**
  - Implementación de la tabla `propiedades` como raíz inmobiliaria física mediante migración `011_propiedades.sql` con paridad exacta al 100% en `SQL/camargo_pms.sql` (22 tablas, 23 FKs).
  - Delimitación ontológica estricta: una propiedad modela única y exclusivamente el contenedor físico, edificación o inmueble raíz (ej. "Edificio Ayuda Mutua"). Se prohíbe taxativamente modelar unidades, tipologías de alojamiento, habitaciones o camas en esta fase, reservando dicha modelación para `UNIDADES-1`.
  - Respeto riguroso de gobernanza: Decisiones P-004 (zona horaria y corte hotelero), P-005 (moneda, redondeo e impuestos) y P-006 (estrategia de concurrencia para disponibilidad) permanecen estrictamente pendientes, sin introducir atributos temporales, tarifarios o de inventario prematuros.
- **Preservación Histórica del Ciclo de Vida (`PROPIEDAD ≠ REGISTRO DESECHABLE`):**
  - Cero eliminación física en toda la arquitectura (`DELETE FROM propiedades` = 0).
  - Ciclo de vida operacional gobernado exclusivamente por la alternancia de estados `ACTIVO` e `INACTIVO`.
  - Ausencia absoluta de métodos `eliminar()` en repositorio y servicio, y de acciones de eliminación en controlador. Rutas HTTP DELETE no registradas responden `404 Not Found`.
- **Capa de Dominio y Validaciones Geográficas:**
  - Modelo de dominio `Propiedad` con tipado estricto, métodos de utilidad `estaActiva()`, `obtenerUbicacionCompleta()`, `obtenerCoordenadas(): array`, y serialización consistente `haciaArreglo()`, `aArreglo()`, `aArray()`, y recreación `desdeArreglo()`.
  - Excepciones de dominio `PropiedadDuplicadaExcepcion` (HTTP 409) y `PropiedadNoEncontradaExcepcion` (HTTP 404).
  - Validaciones de dominio en `PropiedadServicio`: código alfanumérico único (3-30 caracteres, mayúsculas normalizadas), nombre obligatorio (mínimo 3 caracteres), dirección física obligatoria y no vacía, vinculación a país válido del catálogo (`paises`), y validación defensiva de coordenadas geográficas en rangos válidos (latitud en `[-90.0, 90.0]`, longitud en `[-180.0, 180.0]`).
- **Servicio y Trazabilidad Transversal D-061:**
  - `PropiedadServicio`: punto de acceso único para gestión de propiedades.
  - Actualización funcional con cálculo diferencial exacto (`diff`) que previene la emisión de eventos de auditoría redundantes cuando no hay cambios.
  - Trazabilidad integral de operaciones (`CREAR`, `EDITAR`, `DESACTIVAR`, `ACTIVAR`) en `auditoria` bajo D-061, resolviendo el actor humano (`USR_x`) mediante `resolverActorEjecutor()` y reservando `CAMARGO_PMS` (`id = 1`) para ejecuciones de sistema.
- **Controlador, Rutas y Permisos RBAC:**
  - Nuevos permisos de sistema: `propiedades.ver`, `propiedades.crear`, `propiedades.editar`, `propiedades.cambiar_estado`.
  - Rutas registradas en `public/index.php`: `GET /propiedades`, `GET /propiedades/datos`, `GET /propiedades/{id}`, `GET /propiedades/{id}/perfil`, `POST /propiedades`, `PUT /propiedades/{id}`, `PATCH /propiedades/{id}/estado`, y variantes POST compatibles.
  - Nueva opción de menú autorizada: Nivel 1 `propiedades` ('Propiedades', icono `ti ti-building`, orden 2) y Nivel 2 `propiedades_catalogo` ('Catálogo de Inmuebles', ruta `/propiedades`, permiso `propiedades.ver`).
- **Interfaz Alina y Ficha Técnica:**
  - Vista general `app/Vistas/propiedades/index.php` con maquetación de tarjetas Alina, tabla dinámica, filtros de búsqueda, selector de estado y modal interactivo para creación y edición.
  - Vista de perfil `app/Vistas/propiedades/detalle.php` con ficha técnica del inmueble, tarjeta de georreferenciación con enlace a Google Maps, trazabilidad de auditoría y bloque reservado con advertencia arquitectónica del principio `PROPIEDAD ≠ UNIDAD` hacia `UNIDADES-1`.
  - Script Vanilla JS modular `public/assets/js/gestion-propiedades.js` con debounce de búsqueda, paginación dinámica, validación cliente mediante PristineJS v1.1.0 y confirmaciones con SweetAlert2.
- **Pruebas y Verificaciones:**
  - Matriz formal unitaria y de integración `PROP-01` a `PROP-40` (40/40 PASS).
  - Prueba específica de persistencia histórica `PROP-HIST-01` de 10 pasos (10/10 PASS).
  - Suite HTTP E2E Real contra servidor Apache HTTPS `E2E-PROP-01` a `E2E-PROP-12` (12/12 PASS).
  - Regresión integral sin fallos sobre todos los módulos previos del sistema.

### Fase CONFIGURACIÓN-1 — Núcleo Central de Configuración y Parámetros del Sistema

- **Arquitectura y Separación Canónica (`CONFIGURACIÓN FUNCIONAL (BD) ≠ ENTORNO TÉCNICO (.env)`):**
  - Implementación de la tabla `configuraciones` para el catálogo de parámetros funcionales del PMS mediante migración `010_configuracion_sistema.sql` con paridad exacta al 100% en `SQL/camargo_pms.sql` (21 tablas, 22 FKs).
  - Separación estricta: parámetros operativos residen en BD; secretos de infraestructura y credenciales de servicios permanecen inmutables en `.env` (cero modificaciones a `.env` desde UI o backend).
  - Parámetros sensibles (`es_sensible = 1`) son automáticamente ofuscados (`***`) en los registros de auditoría y salidas operativas.
  - Respeto riguroso de gobernanza: Decisiones P-004 (zona horaria y fecha hotelera) y P-005 (moneda, redondeo e impuestos) se mantienen formalmente pendientes, sin sembrar parámetros especulativos.
- **Contrato de Tipado Fuerte y Capa de Dominio:**
  - Enumeración `TipoConfiguracion` soportando `TEXTO`, `ENTERO`, `DECIMAL`, `BOOLEANO`, `FECHA`, `HORA` y `JSON`, con validación sintáctica, casting a tipos nativos PHP y serialización canónica.
  - Modelo de dominio `ConfiguracionParametro` con métodos `obtenerValorTipado()`, `obtenerValorPredeterminadoTipado()`, `esEditable()`, `esSensible()` y serialización `haciaArreglo()`.
  - Repositorio `ConfiguracionRepositorio` con consultas estructuradas por clave, ID, grupo y soporte de transacciones PDO.
  - Excepciones de dominio `ConfiguracionNoEncontradaExcepcion` (404) y `ConfiguracionNoEditableExcepcion` (422).
- **Servicio Canónico y Trazabilidad D-061:**
  - `ConfiguracionServicio`: punto de acceso único para la lectura y escritura de parámetros.
  - Caché de memoria en tiempo de petición (`request-scoped`) para optimizar lecturas consecutivas de alta frecuencia.
  - Actualización atómica en lote (`actualizarMultiples()`) bajo una única transacción con reversión completa ante fallos y `correlacion_id` unificado.
  - Parámetros protegidos (`editable = 0`, ej. `sistema.version_instalada`) protegidos incondicionalmente contra mutación.
  - Capacidad de reversión o restablecimiento a valores de fábrica (`restaurarPredeterminado()`).
  - Auditoría transversal imputando la autoría al actor humano correspondiente (`USR_x`) mediante `resolverActorEjecutor()`, cumpliendo D-061.
- **Controlador, Rutas y Permisos RBAC:**
  - Nuevos permisos de sistema: `configuracion.ver` y `configuracion.editar`.
  - Rutas registradas en `public/index.php`: `GET /configuracion/sistema`, `GET /configuracion/sistema/datos`, `POST /configuracion/sistema`, `PUT /configuracion/sistema`, `POST /configuracion/sistema/restaurar` y `POST /configuracion/sistema/{clave}/restaurar`.
  - Nueva opción de menú autorizada: `config_sistema` ('Configuración General', `/configuracion/sistema`, orden 1 bajo `configuracion`).
- **Interfaz Alina y Assets Propios:**
  - Vista `app/Vistas/configuracion/sistema/index.php` estructurada en pestañas temáticas (`General`, `Localización`, `Operación`), badges de claves técnicas y controles adaptados por tipo de dato (switches, inputs numéricos, selectores, badges protegidos).
  - Script Vanilla JS modular `public/assets/js/gestion-configuracion.js` con soporte de validación cliente (PristineJS v1.1.0) y diálogos de confirmación defensivos con SweetAlert2.
- **Pruebas y Verificaciones:**
  - Matriz formal unitaria y de integración `CFG-01` a `CFG-40` (40/40 PASS).
  - Suite HTTP E2E Real contra servidor Apache HTTPS `E2E-CFG-01` a `E2E-CFG-12` (12/12 PASS).
  - Cero regresiones en toda la batería histórica de pruebas (406 pruebas independientes unitarias/integración + 58 pruebas E2E reales 100% PASS).

### Micro-fase ROLES-2A — Preservación Histórica del Ciclo de Vida de Roles

- **Preservación Histórica del Ciclo de Vida (`ROL ≠ REGISTRO DESECHABLE`):**
  - Reemplazado el criterio provisional de eliminación física de roles introducido en ROLES-2 antes de la homologación definitiva.
  - Formalizado el principio de que los roles participan en autorizaciones, auditoría, asignaciones y relaciones históricas; su ciclo de vida operacional se administra exclusivamente mediante la alternancia `ACTIVO` / `INACTIVO`.
  - Retiradas del enrutador las rutas de eliminación física (`DELETE /configuracion/roles/{id}` y `POST /configuracion/roles/{id}/eliminar`).
  - Retirada la acción productiva `eliminar()` de `RolControlador` y la operación `eliminarRol()` de `RolServicio`.
  - Retirado el botón de eliminar, diálogo SweetAlert2 de borrado y llamada Fetch de la interfaz Alina (`public/assets/js/gestion-roles.js`).
  - La UI comunica el estado de forma clara y ofrece exclusivamente alternancia de estado: *Desactivar* para roles activos y *Activar* para roles inactivos.
  - Se mantiene en `RolRepositorio::eliminar()` su presencia de bajo nivel heredada (originada en commit `25018c4`, `fase-roles-1`), sin invocación en servicios, controladores ni interfaz.
- **Protección y Trazabilidad:**
  - Preservada la protección incondicional de `SUPERADMINISTRADOR` (no desactivable, código inmutable, permisos críticos protegidos).
  - Preservada la protección de roles de sistema (`es_sistema = 1`).
  - La bitácora de auditoría no genera eventos `ELIMINAR` sobre roles; registra de forma normalizada las acciones `DESACTIVAR` y `ACTIVAR` con autoría humana resuelta conforme a D-061.
- **Pruebas y Verificaciones:**
  - Matriz formal `ROL2-01` a `ROL2-40` adaptada al contrato de ciclo de vida histórico (40/40 PASS).
  - Implementada la prueba específica de persistencia histórica `ROL-HIST-01` (10/10 pasos PASS).
  - Suite HTTP E2E Real contra Apache HTTPS adaptada (`E2E-ROL2-01` a `E2E-ROL2-12`), verificando respuesta HTTP 404 ante intentos de DELETE físico (12/12 PASS).
  - Batería completa de regresiones aprobada al 100%.

### Fase ROLES-2 — Administración Visual de Roles y Permisos

- **Administración Visual de Roles y Permisos (`/configuracion/roles`):**
  - Implementada la interfaz administrativa completa con maquetación Alina y Bootstrap 5 bajo `/configuracion/roles` y opción de menú autorizada (`config_roles`, permiso `roles.ver`).
  - Catálogo de roles con búsqueda en tiempo real (nombre, clave, descripción), filtros por estado (`ACTIVO`/`INACTIVO`), conteo agregado de usuarios vinculados y permisos asociados.
  - Cuatro modales Bootstrap 5 orquestados de forma asíncrona:
    - *Crear Rol:* Formulario con validación en cliente mediante PristineJS v1.1.0 local, clave técnica normalizada en mayúsculas (alfanumérico y guión bajo, 3 a 50 caracteres), nombre (2 a 100 caracteres), descripción opcional y estado inicial. Prohibición de crear roles con la clave reservada `SUPERADMINISTRADOR`.
    - *Editar Rol:* Modificación segura de nombre, descripción y estado operativo. Clave técnica protegida en modo solo lectura para roles del sistema. Prohibición de desactivar el rol `SUPERADMINISTRADOR`.
    - *Matriz de Permisos:* Visualización jerárquica agrupada por módulo funcional (`usuarios`, `roles`, `permisos`, `menu`) con checkboxes interactivos, botones de selección masiva global y por módulo, contador reactivo de permisos seleccionados y protección explícita de `SUPERADMINISTRADOR` que bloquea la revocación de permisos críticos de administración.
    - *Usuarios Vinculados:* Consulta en solo lectura de las cuentas humanas que ostentan el rol, con detalle de la `Persona` vinculada, estado de la cuenta y fecha de asignación.
- **Autorización Dinámica en Tiempo Real:**
  - La actualización matricial de permisos de un rol impacta de inmediato en `puede()` y `obtenerPermisosEfectivos()` para los usuarios activos sin requerir re-login ni revocación forzada de sesiones.
- **Protección Inviolable de `SUPERADMINISTRADOR` y Roles de Sistema:**
  - El rol estructural `SUPERADMINISTRADOR` no puede ser renombrado, desactivado ni eliminado físicamente (`RolProtegidoExcepcion`).
  - La sincronización matricial impide revocar los permisos críticos de administración del rol `SUPERADMINISTRADOR` (`roles.ver`, `roles.editar`, `permisos.ver`, `usuarios.ver`, `usuarios.editar`).
  - Se preserva el invariante pesimista del último Superadministrador humano activo (`UltimoSuperadministradorExcepcion`).
  - Roles con `es_sistema = 1` no permiten modificación de clave técnica ni eliminación física.
- **Trazabilidad y Auditoría Transversal bajo D-061:**
  - Todas las operaciones de creación (`CREAR`), edición (`EDITAR`), cambio de estado (`ACTIVAR`/`DESACTIVAR`), eliminación (`ELIMINAR`) y sincronización matricial (`ASIGNAR`/`REVOCAR`) resuelven el `ActorAuditoria` del ejecutor mediante `resolverActorEjecutor(?int $usuarioId)`.
  - La sincronización matricial agrupa todos los eventos atómicos de asignación y revocación bajo un identificador de correlación unificado (`correlacion_id`).
  - Cero fugas de credenciales, tokens o hashes en auditoría.
- **Base de Datos y Esquema:**
  - Esquema completamente cubierto por las migraciones existentes `001..009`. Cero migraciones nuevas creadas (001-009 aplicadas, 0 pendientes).
  - Archivos bajo `SQL/` y catálogo `admin-dashboard/` intactos.
- **Pruebas y Verificaciones:**
  - Matriz formal `ROL2-01` a `ROL2-40` aprobada al 100% (40/40 PASS).
  - Suite HTTP E2E Real contra servidor Apache HTTPS (`test_e2e_roles2.php`) aprobada al 100% (12/12 PASS).
  - Cero regresiones en todo el árbol histórico de pruebas (ACTOR 6/6, USR 40/40, E2E-USR 10/10, AUD 40/40, Sanitizador 62/62, E2E-AUD 8/8, Menú 40/40).

### Micro-fase USUARIOS-1A — Corrección de Identidad del Actor de Auditoría

- **Resolución Rigurosa de Identidad de Actor (`ACTOR ≠ USUARIO`):**
  - Subsanada la colisión conceptual en `UsuarioServicio` donde se transfería directamente `$creadoPorUsuarioId` / `$ejecutadoPorUsuarioId` (entero de `usuarios.id`) al noveno argumento (`$actor`) de `AuditoriaServicio::registrar()`, el cual esperaba `ActorAuditoria|int|string|null` interpretando enteros como `actores.id`.
  - Esta divergencia generaba excepciones `ActorNoEncontradoExcepcion` (HTTP 500) cuando mutaciones eran ejecutadas por administradores distintos al usuario inicial (`usuario_id != actor_id`), o provocaba la falsa atribución de autoría al sistema `CAMARGO_PMS` (`actor_id = 1`) cuando ejecutaba el usuario 1 (`orlando`) en vez de su actor humano correspondiente `USR_1` (`actores.id = 2`).
  - Implementado el método auxiliar `resolverActorEjecutor(?int $usuarioId): ?ActorAuditoria` en `UsuarioServicio`, asegurando la resolución mediante `AuditoriaServicio::obtenerOAsegurarActorUsuario($usuarioId, $this->pdo)`.
  - Corregidas las 6 invocaciones a `registrar()` en `UsuarioServicio`: `crearUsuario()`, `cambiarEstado()`, `cambiarContrasenaPropia()`, `restablecerContrasena()`, `cerrarSesion()` y `cerrarTodasSesiones()`.
  - Actualizado `RolServicio::asignarRolAUsuario()` y `RolServicio::revocarRolDeUsuario()` para resolver el actor ejecutor explícito (`$asignadoPor` / `$revocadoPor`) participando en la conexión transaccional.
  - Actualizado `UsuarioControlador::revocarRol()` para extraer el usuario activo en sesión y transferirlo como `$revocadoPor` a `RolServicio::revocarRolDeUsuario()`.
- **Verificación, Gates y Pruebas Automatizadas:**
  - Creada y aprobada la matriz específica de resolución de identidad de actor `ACTOR-01` a `ACTOR-06` (6 PASS / 0 FAIL), comprobando desacople de IDs, autoría correcta de Orlando (`USR_1`), preservación de `CAMARGO_PMS` para procesos de sistema, auto-aseguramiento `USR_{id}` sin duplicados y atomicidad transaccional con rollback.
  - Aprobada al 100% la suite HTTP E2E Real contra servidor Apache (`test_e2e_usuarios.php`) con 10/10 PASS (E2E-USR-01 a E2E-USR-10), verificando que las mutaciones HTTP de creación, asignación de rol, cambio de estado y restablecimiento de contraseña operan con éxito (HTTP 201/200) y registran autoría íntegra con cero fugas.
  - Matriz de usuarios `USR-01` a `USR-40` aprobada al 100% (40/40 PASS).
  - Regresiones de todo el árbol histórico aprobadas al 100% (Auditoría 40/40, Sanitizador 62/62, Auditoría E2E 8/8, Menú 57/57, Identidad 27/27, Personal 26/26, Auth 60/60).
  - Esquema de base de datos intacto (migraciones 001-009, 0 migraciones nuevas, `SQL/` intacto).
  - Catálogo `admin-dashboard/` intacto.

### Fase USUARIOS-1 — Administración Integral de Cuentas Humanas

- **Administración Integral de Cuentas Humanas (`/usuarios`):**
  - Implementada la interfaz administrativa completa bajo `/usuarios` con diseño nativo Alina y Bootstrap 5, paginación server-side, búsqueda multicriterio (nombre de usuario, nombre y apellidos de la persona vinculada) y filtros dinámicos por estado y rol RBAC.
  - Cuatro modales Bootstrap 5 orquestados de forma asíncrona:
    - *Crear Usuario:* Búsqueda y vinculación de Personas humanas elegibles (no vinculadas a otros usuarios), definición de nombre de usuario con normalización en tiempo real, asignación de rol inicial RBAC y contraseña segura con validación en cliente.
    - *Detalle de Usuario:* Inspección completa de perfil (datos personales, rol, estado, fechas) y tabla de sesiones activas con revocación puntual o masiva.
    - *Cambiar Estado:* Transiciones operacionales (`ACTIVO`, `INACTIVO`, `BLOQUEADO`) con confirmación SweetAlert2 y protección defensiva del último superadministrador activo.
    - *Restablecer Contraseña:* Asignación de nuevas credenciales criptográficamente seguras con validación en cliente, confirmación y revocación forzada de sesiones concurrentes.
- **Principio Vinculante `PERSONA ≠ USUARIO ≠ COLABORADOR ≠ ROL` y Cero Cuentas Sintéticas:**
  - El Usuario representa exclusivamente la credencial de acceso de un individuo humano vinculado unívocamente a una Persona natural (`usuarios.persona_id UNIQUE NOT NULL REFERENCES personas(id)`).
  - Prohibición categórica de crear usuarios sintéticos para integraciones, WordPress o procesos del sistema.
- **Prohibición Estricta de Eliminación Física (Zero Physical Delete):**
  - Las cuentas de usuario son inmutables en su existencia histórica (`DELETE FROM usuarios` = 0). Las bajas y suspensiones se gestionan únicamente mediante transiciones de estado a `INACTIVO` o `BLOQUEADO`.
- **Invariante Concurrente del Último Superadministrador Activo:**
  - Implementada protección pesimista (`FOR UPDATE`) en `UsuarioServicio::cambiarEstado()` para asegurar que el último superadministrador humano en estado activo con persona activa no pueda ser bloqueado o desactivado (`UltimoSuperadministradorExcepcion`), impidiendo condiciones de carrera concurrentes.
- **Políticas Criptográficas y de Sesiones Consolidadas:**
  - Preservación estricta de `PASSWORD_DEFAULT`, política de 12 a 1024 caracteres, preservación total de espacios y caracteres Unicode sin normalizaciones destructivas ni truncamiento. Cero usos de algoritmos obsoletos o no aprobados.
  - La alteración o restablecimiento de contraseña revoca de forma concurrente todas las demás sesiones activas en `sesiones_usuario` marcando `revocada_en = NOW()`.
- **Validación Frontend Modular con PristineJS v1.1.0 y SweetAlert2:**
  - Integrada la biblioteca local PristineJS v1.1.0 en `public/assets/js/gestion-usuarios.js` para validación interactiva inmediata en modales (longitud, caracteres permitidos, confirmación de contraseña coincidente).
  - Limpieza defensiva del ciclo de vida de modales en `hidden.bs.modal` con `validador.reset()`, garantizando ausencia de fugas de memoria o errores residuales al reabrir modales.
  - Comunicación asíncrona mediante Fetch API con token CSRF transmitido por cabecera `X-CSRF-TOKEN` y soporte de respuestas 403 JSON en `AutorizacionIntermediario`.
- **Auditoría Transversal Nativa con Cero Fuga de Secretos:**
  - Integración nativa de `AuditoriaServicio` en todos los flujos de `UsuarioServicio`: eventos `CREAR`, `CAMBIAR_ESTADO` y `CAMBIAR_CLAVE` con resolución de actor (`ACTOR ≠ USUARIO`), contexto HTTP y sanitización total de credenciales y contraseñas vía `SanitizadorAuditoria`.
- **Base de Datos y Migraciones:**
  - Esquema completamente cubierto por las migraciones oficiales `001` a `009`. Cero migraciones nuevas creadas (001-009 aplicadas, 0 pendientes).
- **Puertas de Calidad y Pruebas:**
  - Matriz Formal de Usuarios `USR-01` a `USR-40` aprobada al 100% (40 PASS / 0 FAIL).
  - Suite HTTP E2E Real contra servidor Apache bajo HTTPS `E2E-USR-01` a `E2E-USR-10` aprobada al 100% (10 PASS / 0 FAIL).
  - Regresiones históricas completas aprobadas al 100%: IDENTIDAD (27/27), PERSONAL (26/26), AUTH-1/1A (60/60), MENÚ-1/1A (40/40), SANITIZADOR AUDITORIA-1A (62/62), AUDITORIA-1 (40/40).
  - Total consolidado: 268 pruebas unitarias/integración estrictamente independientes (272 brutas) + 24 pruebas E2E HTTP reales (100% PASS).

### Micro-fase AUDITORÍA-1A — Sanitización Multibyte y Corrección de Trazabilidad Documental

- **Completitud y Normalización en `SanitizadorAuditoria`:**
  - Incorporadas explícitamente las claves técnicas y sensibles `'contraseña'`, `'session'` y `'client_secret'` a la lista canónica `CLAVES_SENSIBLES`.
  - Implementada normalización insensible a mayúsculas y minúsculas con soporte estricto de codificación multibyte (`mb_strtolower(..., 'UTF-8')`) en `esClaveSensible()` y en `sanitizarTexto()`, neutralizando variantes como `CONTRASEÑA`, `Contraseña`, `SESSION` y `Client_Secret`.
  - Verificada la eliminación recursiva total (14/14 variantes sensibles evaluadas: `password`, `contrasena`, `contraseña`, `password_hash`, `csrf`, `csrf_token`, `authorization`, `cookie`, `session`, `session_id`, `token`, `api_key`, `secret`, `client_secret` en mayúsculas, minúsculas, arreglos anidados y JSON embebido).
- **Corrección Documental de Integridad Referencial:**
  - Rectificada la errata descriptiva en la documentación de `fk_actores_usuario` hacia `usuarios(id)`: el DDL canónico real de la migración `009` y `camargo_pms.sql` es `ON DELETE SET NULL ON UPDATE CASCADE` (no `CASCADE`).
  - Formalizada la política vinculante de ciclo de vida de usuarios: las cuentas humanas no se eliminan físicamente en operación ordinaria (`DELETE FROM usuarios` está prohibido a nivel de servicio y repositorio; solo existen estados `ACTIVO`, `INACTIVO` y `BLOQUEADO`). La configuración de FKs (`SET NULL` en usuario y `RESTRICT` en actor) garantiza de forma absoluta la supervivencia inmutable de los registros de auditoría.
- **Reconciliación y Trazabilidad del Conteo de Pruebas:**
  - Registrado el conteo canónico exacto: 222 ejecuciones brutas en el árbol de suites (222 PASS / 0 FAIL), que representan 218 pruebas estrictamente independientes (debido a 4 verificaciones de comprobación rápida en `test_regresion_menu1a.php` que solapan cobertura ya existente en `probar_menu_completo.php` y contratos previos).

### Fase AUDITORÍA-1 — Núcleo Transversal de Auditoría y Trazabilidad

- **Principio Arquitectónico Vinculante `ACTOR ≠ USUARIO`:**
  - Desacople ontológico entre el sujeto de una acción y la cuenta humana de acceso: un actor puede ser `USUARIO` (humano), `SISTEMA` (procesos de background / cron), `INTEGRACION` (clientes técnicos de API como WordPress o app móvil) o `PROVEEDOR_PAGO` (webhooks seguros).
  - Tabla `actores` con tipo, código único, nombre descriptivo, referencia opcional a `usuarios(id)` (`ON DELETE SET NULL ON UPDATE CASCADE`) y metadatos en JSON.
  - Semilla estructural protegida `CAMARGO_PMS` (`id = 1`, `tipo = 'SISTEMA'`).
  - Sincronización automática 1:1 de actores humanos para usuarios registrados en el sistema (`USR_{id}`).
- **Inmutabilidad Estricta de la Bitácora (`auditoria`):**
  - Tabla `auditoria` diseñada para registro append-only de solo inserción. Clave foránea `fk_auditoria_actor` con `ON DELETE RESTRICT` (impide borrar actores con histórico) y `fk_auditoria_usuario` con `ON DELETE SET NULL` (garantiza que la baja de un usuario jamás degrade la autoría histórica).
  - Repositorio `AuditoriaRepositorio` implementado sin métodos `actualizar()` o `eliminar()`. Prohibición de mutaciones o borrado en caliente.
- **Sanitizador Recursivo de Secretos (`SanitizadorAuditoria`):**
  - Algoritmo recursivo que inspecciona campos `valores_anteriores`, `valores_nuevos` y `metadatos`, eliminando o redactando (`[REDACTADO]`) contraseñas, hashes criptográficos, tokens CSRF, cabeceras de autorización, tokens de sesión y claves de API en cualquier nivel de anidamiento.
  - Verificación formal de CERO fugas de credenciales en base de datos real y en respuestas HTTP.
- **Trazabilidad Contextual y Correlación:**
  - Captura estandarizada de contexto HTTP (`ip`, `user_agent`, `metodo_http`, `ruta`) y generación/propagación de identificador único de trazabilidad `correlacion_id` (UUIDv4 / CSPRNG 32 hex chars).
  - Modelo polimórfico de excepciones de dominio: `AuditoriaExcepcion`, `ActorNoEncontradoExcepcion` y `ActorDuplicadoExcepcion`.
- **Integración Transversal en Servicios de Dominio:**
  - `AutenticacionServicio`: Audita eventos de `LOGIN` y `LOGOUT` vinculados al actor correspondiente.
  - `RolServicio`: Audita atómicamente la asignación (`ASIGNAR`) y revocación (`REVOCAR`) de roles a usuarios bajo transacción.
  - `MenuServicio`: Audita operaciones de `CREAR`, `EDITAR`, `ACTIVAR`, `DESACTIVAR`, `REORDENAR` y `ELIMINAR` de opciones de menú.
  - `UsuarioServicio`: Audita `CREAR`, `CAMBIAR_ESTADO` y `CAMBIAR_CLAVE` garantizando trazabilidad sin filtrar contraseñas o hashes.
- **Base de Datos y Migración 009:**
  - Creada la migración `SQL/migraciones/009_auditoria_actores.sql` (lote 9) creando las tablas `actores` y `auditoria`, con restricciones CHECK, claves foráneas e índices optimizados por actor, usuario, acción, módulo, entidad, correlación y fecha.
  - Sincronizado `SQL/camargo_pms.sql` alcanzando 20 tablas operativas y paridad estructural del 100%.
- **Puertas de Calidad y Pruebas:**
  - Matriz Formal AUD-01 a AUD-40 aprobada al 100% (40 PASS / 0 FAIL).
  - Suite HTTP E2E Real contra Apache bajo HTTPS aprobada al 100% (8 PASS / 0 FAIL).
  - Auditoría de Paridad SQL 100% entre migraciones 001..009 y `SQL/camargo_pms.sql`.
  - Inspección de base de datos productiva: 0 fugas de credenciales detectadas.
  - Regresiones históricas completas aprobadas (174/174 PASS).

### Micro-lote MENÚ-1A — Integración de PristineJS y Verificación de Contrato

- **Integración de PristineJS (Frontend UX):**
  - Incorporada la librería `pristine.min.js` (v1.1.0, distribución oficial de código abierto) en `public/assets/vendor/pristine/` bajo carga modular exclusiva para `/configuracion/menu`.
  - Configurada validación interactiva sobre el formulario y modales de `Gestión de Menú`: validación de clave técnica con regex, nombre visible, selección obligatoria de categoría padre en opciones secundarias y restricción estricta de rutas locales sin esquemas externos ni scripts.
  - Implementado ciclo de vida limpio para modales Bootstrap (`hidden.bs.modal`) con `validador.reset()`, garantizando ausencia de duplicación de mensajes de error o listeners al cerrar y reabrir.
  - Sincronización dinámica de visibilidad y reglas aplicables al alternar entre nivel principal y nivel secundario.
  - Bloqueo preventivo de envíos asíncronos (`Fetch`) cuando la validación en cliente falla.
- **Principio "VALIDACIÓN FRONTEND ≠ VALIDACIÓN DE SEGURIDAD":**
  - Preservada la autoridad canónica e independiente del backend en `MenuControlador` y `MenuServicio`: cualquier petición directa que evada el cliente continúa siendo rechazada con `HTTP 422 Unprocessable Entity`.
- **Verificación del Contrato de Contraseñas AUTH-1A:**
  - Inspección exhaustiva de código productivo: confirmado que `AutenticacionServicio` y `UsuarioServicio` utilizan estrictamente `PASSWORD_DEFAULT` conforme a AUTH-1A. (Se rectifica el error descriptivo de la auditoría anterior que mencionó erróneamente un uso general de ARGON2ID en el sistema).
- **Puertas de Calidad:**
  - Matriz de pruebas automatizada `PRISTINE-01` a `PRISTINE-12` aprobada al 100% (12 PASS / 0 FAIL).
  - Regresión acotada PASS (`MENU-28`, `MENU-39`, `MENU-40`, `AUTH-PASS-01`).

### Fase MENÚ-1 — Menú Dinámico, Navegación Autorizada y Gestión de Menú

- **Navegación Dinámica y Contrato Visual Alina:**
  - Sustituida la navegación estática de prueba por una estructura jerárquica de dos niveles persistida en la tabla `opciones_menu`, respetando estrictamente el contrato Alina (`navbar-menu-list` con `data-target="clave"` <-> `main-side-menu` con `id="clave"`).
  - Categorías principales (`padre_id IS NULL`, Nivel 1) representadas como iconos horizontales en la cabecera; opciones secundarias (`padre_id IS NOT NULL`, Nivel 2) renderizadas en el menú lateral desplegable.
  - Sincronización automática de elementos activos (`.active`) para nivel 1 y nivel 2 en base a la ruta actual (`$rutaActual`).
- **Principio "MENÚ ≠ AUTORIZACIÓN" y Filtrado Aditivo:**
  - Separación total entre visibilidad en cliente y autorización real en backend: el menú guía la experiencia visual del usuario, mientras que `AutorizacionIntermediario` protege de forma independiente cada endpoint HTTP.
  - Filtro aditivo RBAC: las opciones con permiso asociado (`permiso_id`) solo se muestran si el usuario cuenta con la capacidad activa (`AutorizacionServicio::puede()`) o posee el rol `SUPERADMINISTRADOR`.
  - Depuración automática de categorías principales vacías: una categoría solo se renderiza si tiene al menos una opción secundaria activa y visible.
- **Base de Datos y Migración 008:**
  - Creada la migración `SQL/migraciones/008_menu_dinamico.sql` (lote 8) definiendo la tabla `opciones_menu` con claves foráneas autorreferenciales (`padre_id` RESTRICT) y hacia permisos (`permiso_id` SET NULL).
  - Semillas base de permisos: `menu.ver` ("Ver menú de navegación") y `menu.gestionar` ("Gestionar opciones de menú").
  - Semillas base de opciones: Nivel 1 (`inicio`, `configuracion`) y Nivel 2 (`inicio_panel`, `config_menu`, `config_usuarios`).
  - Sincronizado `SQL/camargo_pms.sql` con las 17 tablas operativas y paridad estructural 100%.
- **Módulo de Administración de Menú (`/configuracion/menu`):**
  - Desarrollada vista reactiva `app/Vistas/configuracion/menu/index.php` con tabla jerárquica, modales de creación/edición, alternancia rápida de estado (activo/inactivo) y controles de ordenamiento.
  - Controlador `MenuControlador`: endpoints para CRUD completo, alternancia de estado y reordenamiento transaccional atómico por pares `[id, orden]`.
  - Enrutador ampliado: soporte de patrones parametrizados (`{id}`), verbos REST (`put`, `patch`, `delete`) y emulación por `_method` o cabecera `X-HTTP-Method-Override`.
  - Frontend interactivo: `public/assets/js/gestion-menu.js` desarrollado con Vanilla JS nativo y Fetch API (sin dependencias de jQuery), con SweetAlert2 para alertas y confirmaciones.
- **Seguridad y Protección Estructural:**
  - Protección de opciones del sistema (`es_sistema = 1`): `config_menu` no puede ser eliminada físicamente ni desactivada (`OpcionMenuProtegidaExcepcion`), impidiendo que el sistema quede sin acceso a su propia gestión.
  - Sanitización de rutas: las rutas deben ser relativas internas locales comenzando con `/`; rechazo categórico de esquemas maliciosos (`javascript:`, `data:`, `vbscript:`, URLs externas con `http:`, `https:`).
  - Protección CSRF estricta en todas las operaciones de mutación mediante token en payload o cabecera `X-CSRF-TOKEN`.
- **Pruebas y Verificación:**
  - Suite de pruebas de dominio `scratch/probar_menu_completo.php` con 40 gates formales aprobados (40 PASS / 0 FAIL, `MENU-01` a `MENU-40`) en base temporal aislada con timeouts de seguridad (`innodb_lock_wait_timeout = 2`, `lock_wait_timeout = 3`) y cleanup defensivo.
  - Auditoría de paridad SQL estricta `scratch/verificar_paridad_sql.php`: 100% idéntico entre migraciones (`001` a `008`) y `SQL/camargo_pms.sql`.
  - Suite E2E HTTP real contra Apache bajo HTTPS `scratch/e2e_http_menu.php`: 10/10 PASS (`E2E-MENU-01` a `E2E-MENU-10`).
  - Regresiones de suites previas: AUTH-1/AUTH-1A (60 PASS / 0 FAIL), HTTP Apache autenticado (6/6 PASS).

### Fase ROLES-1 — Roles, Permisos y Autorización RBAC de Backend

- **Arquitectura de Autorización RBAC:**
  - Implementada la infraestructura de autorización de backend bajo el principio de separación: `Usuario <---> usuarios_roles <---> Rol <---> roles_permisos <---> Permiso`.
  - Permisos modelados como capacidades atómicas granulares con convención `recurso.accion` en minúsculas (D-047).
  - Permisos puramente aditivos: la autorización efectiva para usuarios ordinarios resulta de la unión de todos los permisos de sus roles asignados activos.
- **Rol Estructural Protegido SUPERADMINISTRADOR:**
  - Creado el rol `SUPERADMINISTRADOR` (`es_sistema = 1`, `es_superadministrador = 1`) con autoridad total e incondicional centralizada en `AutorizacionServicio` (D-048).
  - Cualquier comprobación de permisos para un Superadministrador activo devuelve `true` de inmediato, sin requerir asignación exhaustiva en `roles_permisos` y garantizando inmunidad ante permisos futuros.
  - Protección absoluta del rol contra cambio de código, desactivación o eliminación física (`RolProtegidoExcepcion`).
- **Invariante del Último Superadministrador Activo:**
  - Bloqueo pesimista de filas (`FOR UPDATE`) en transacciones para garantizar la permanencia de al menos un Superadministrador activo en el sistema (D-049).
  - Se prohíbe revocar el rol, bloquear o desactivar la cuenta del usuario, o desactivar la persona asociada si es el último Superadministrador activo (`UltimoSuperadministradorExcepcion`).
  - Lógica libre de hardcoding de IDs estáticos o nombres de usuario específicos.
- **Base de Datos y Migración 007:**
  - Creada la migración `SQL/migraciones/007_roles_permisos_autorizacion.sql` (lote 7) definiendo las tablas `roles`, `permisos`, `roles_permisos` y `usuarios_roles`.
  - Semillas estructurales: rol `SUPERADMINISTRADOR` y catálogo de 10 permisos atómicos iniciales (`usuarios.ver`, `usuarios.crear`, `usuarios.editar`, `usuarios.bloquear`, `roles.ver`, `roles.crear`, `roles.editar`, `roles.asignar`, `roles.revocar`, `permisos.ver`).
  - Transición determinista: vinculación automática de `SUPERADMINISTRADOR` al usuario inicial existente solo si `COUNT(usuarios) = 1` y no cuenta con el rol.
  - Sincronizado `SQL/camargo_pms.sql` con las 16 tablas del sistema y semillas RBAC (paridad 100%).
- **Intermediario de Autorización y Manejo HTTP 403:**
  - Implementado `AutorizacionIntermediario`: redirige a `/login` a usuarios sin sesión y emite `HTTP 403 Forbidden` controlado con plantilla segura de Alina (`Vistas/errores/error.php`) ante permisos insuficientes (D-050).
  - Registrada ruta protegida de demostración `/usuarios` con permiso `usuarios.ver`.
- **Modelos, Repositorios y Servicios:**
  - Modelos puros: `Rol` y `Permiso` en `app/Modelos/`.
  - Repositorios con PDO: `RolRepositorio`, `PermisoRepositorio` y `AutorizacionRepositorio` en `app/Repositorios/`.
  - Servicios de dominio: `AutorizacionServicio` y `RolServicio` en `app/Servicios/`.
  - Actualizados `UsuarioServicio` y `PersonaServicio` para defender la invariante del último Superadministrador.
  - Actualizado `bin/crear-usuario-inicial.php` para asignar atómicamente el rol `SUPERADMINISTRADOR` al usuario inicial (D-051).
- **Pruebas y Verificación:**
  - Suite de pruebas de dominio `scratch/probar_roles_completo.php` con 55 pruebas aprobadas (55 PASS / 0 FAIL) en base de datos temporal aislada.
  - Regresiones completas aprobadas: IDENTIDAD-1 (27 PASS), PERSONAL-1 (26 PASS), AUTH-1/AUTH-1A (60 PASS). Total pruebas automatizadas: 168 PASS / 0 FAIL.
  - Validación HTTP real end-to-end contra servidor Apache (`https://app.camargo-pms.test`): 6/6 PASS (redirección 302 sin sesión, HTTP 200 para Superadmin en `/usuarios`, y HTTP 403 Forbidden con layout Alina para usuario sin permiso).

### Hotfix Post-AUTH-1 — Corrección de Renderizado Autenticado Post-Login y Seguridad 500

- **Síntoma:** Al autenticarse exitosamente en `/login` y ser redirigido a la ruta protegida `/`, la aplicación arrojaba una pantalla de error HTTP 500 controlada ("Error del Servidor — Camargo PMS").
- **Causa raíz:** En el componente visual `app/Vistas/componentes/cabecera.php`, la función auxiliar `usuario_autenticado()` devuelve una instancia del modelo de dominio puro `\CamargoPMS\Modelos\Usuario`. El componente intentaba acceder a sus propiedades mediante sintaxis de arreglo (`$usuarioActual['nombre_usuario']`), lo que en PHP 8.3 arrojaba un `TypeError` fatal (`Cannot use object of type CamargoPMS\Modelos\Usuario as array`). En solicitudes no autenticadas dicho bloque condicional no se ejecutaba, razón por la cual las pruebas previas sin renderizado completo de vistas bajo sesión activa no detectaron la falla.
- **Corrección:**
  - `app/Vistas/componentes/cabecera.php`: Reemplazado el acceso por arreglo con la llamada al método de dominio `$usuarioActual->obtenerNombreUsuario()`.
  - `public/index.php`: Establecido `ini_set('display_errors', '0')`, añadido registro seguro de excepciones mediante `error_log((string) $error)` en el bloque `catch (\Throwable $error)` global, y eliminado el volcado de diagnósticos técnicos hacia el navegador para garantizar que las páginas de error 500 nunca filtren rutas internas, trazas ni datos de depuración.
- **Pruebas de regresión agregadas:**
  - Incorporada de forma permanente la prueba `T-AUTH-45` (sub-pruebas A, B y C) en la suite automatizada: valida que un usuario con sesión persistente acceda a `GET /`, el intermediario permita el paso sin redirigir a `/login`, responda con código HTTP 200, y renderice el layout Alina completo mostrando el nombre de usuario, el menú de perfil, el formulario POST de logout y el token CSRF sin arrojar excepciones ni página 500.
  - Validación HTTP real end-to-end con 6 gates contra servidor Apache (`https://app.camargo-pms.test`):
    1. `GET /login` sin sesión → HTTP 200.
    2. `GET /` sin sesión → HTTP 302 Redirige a `/login`.
    3. `GET /` con sesión activa → HTTP 200 Dashboard renderizado con éxito.
    4. `POST /logout` con token CSRF válido → HTTP 302 a `/login` y sesión revocada en BD.
    5. `GET /` tras logout con cookie revocada → HTTP 302 Redirige a `/login`.
    6. `GET /ruta-inexistente` → HTTP 404 Not Found.
  - Suite de regresión ejecutada sobre base de datos aislada temporal (`camargo_pms_test`) con 60/60 pruebas aprobadas (PASS), preservando intacto el usuario real y datos del propietario en `camargo_pms`.

### Micro-lote AUTH-1A — Cierre de Contrato Criptográfico y Bootstrap Inicial

- Implementada la migración evolutiva `SQL/migraciones/006_ajustes_auth_credenciales.sql` (lote 6):
  - Modificación formal de la columna `contrasena_hash` en la tabla `usuarios` a `VARCHAR(255) NOT NULL` con comentario explícito de soporte para `PASSWORD_DEFAULT` y futuros algoritmos.
  - Sincronización de comentarios descriptivos en tablas `usuarios`, `sesiones_usuario` e `intentos_autenticacion`.
- Eliminación de la fijación rígida a `PASSWORD_BCRYPT` y `cost=12` en el dominio y gobernanza:
  - Adopción de `PASSWORD_DEFAULT` estándar en `UsuarioServicio` y `AutenticacionServicio`.
  - Rehash automático y dinámico (`password_needs_rehash()`) con `PASSWORD_DEFAULT` tras autenticaciones exitosas.
  - Mitigación dinámica contra timing attacks y enumeración mediante hash dummy precalculado dinámicamente con `PASSWORD_DEFAULT`.
- Formalización definitiva de la política de contraseñas (D-036):
  - Longitud mínima de 12 caracteres y máxima de 1024 caracteres evaluada estrictamente antes del hash.
  - Ausencia absoluta de transformaciones destructivas (`trim`, `lowercase`, truncamiento); soporte pleno de espacios internos/externos y caracteres Unicode.
- Endurecimiento del contrato de bootstrap inicial en `bin/crear-usuario-inicial.php` (D-046):
  - Eliminación categórica de la bandera `--forzar` y de cualquier mecanismo de bypass de línea de comandos.
  - Regla estricta: `usuarios = 0` permite bootstrap inicial; `usuarios >= 1` rechaza incondicionalmente la ejecución. La creación posterior de usuarios queda reservada exclusivamente a las funciones administrativas del sistema bajo autorización.
- Corrección de decisiones en gobernanza:
  - D-036 actualizada para formalizar `PASSWORD_DEFAULT`, `VARCHAR(255)`, 12/1024 chars y rehash sin asunciones rígidas.
  - D-046 actualizada eliminando toda referencia a `--forzar` y estableciendo el carácter de uso estrictamente único del bootstrap.
- Paridad estructural 100% verificada entre las migraciones 001 a 006 y el consolidado `SQL/camargo_pms.sql` mediante recreación aislada en base de datos temporal `camargo_pms_test_parity_auth1a`.
- Suite automatizada ampliada a 57 pruebas (57 PASS / 0 FAIL), reteniendo las 47 de AUTH-1 e incorporando las pruebas específicas A a L para validación de longitudes (11 rechazada, 12 aceptada, 500 aceptada, 1025 rechazada), preservación de espacios y no trim, compatibilidad `PASSWORD_DEFAULT`, soporte `VARCHAR(255)`, rehash dinámico y rechazo incondicional de `--forzar` en bootstrap con cuentas existentes.
- Confirmada la intangibilidad total del catálogo `admin-dashboard/` (0 archivos modificados).

### Fase AUTH-1 — Autenticación, Cuentas Humanas y Sesiones

- Implementada la migración evolutiva `SQL/migraciones/005_auth_usuarios_sesiones.sql` (lote 5):
  - Creación de la tabla `usuarios`: vinculación directa 1:1 con `personas` (`persona_id UNIQUE NOT NULL`), clave foránea `fk_usuarios_persona` con restricción de borrado `ON DELETE RESTRICT`. Prohibición absoluta de relación con `colaboradores` (`colaborador_id`).
  - Nombres de usuario canónicos y normalizados: `nombre_usuario` para presentación y `nombre_usuario_normalizado` con restricción `UNIQUE` para colisión insensible a mayúsculas (D-035).
  - Almacenamiento seguro de credenciales: `contrasena_hash CHAR(60) NOT NULL` con algoritmo `PASSWORD_BCRYPT` (factor de costo 12).
  - Creación de la tabla `sesiones_usuario`: gestión de sesiones con tokens opacos (32 bytes CSPRNG / 64 caracteres hex) persistiendo exclusivamente su hash SHA-256 (`token_hash UNIQUE`) (D-038).
  - Creación de la tabla `intentos_autenticacion`: auditoría de intentos de acceso para control de fuerza bruta y rate limiting por IP e identificador (D-045).
- Sincronizado el archivo consolidado oficial `SQL/camargo_pms.sql` conteniendo las 13 tablas del sistema y finalizando explícitamente con `SET FOREIGN_KEY_CHECKS = 1;`. Paridad 100% verificada entre migración y consolidado.
- Implementados los modelos de dominio puros: `Usuario` y `SesionUsuario` en `app/Modelos/`.
- Implementadas las excepciones de dominio especializadas en `app/Excepciones/`: `CredencialesInvalidasExcepcion`, `UsuarioDuplicadoExcepcion`, `UsuarioBloqueadoExcepcion`, `SesionInvalidaExcepcion`, `CsrfInvalidoExcepcion` y `DemasiadosIntentosExcepcion`.
- Implementados los repositorios con PDO parametrizado: `UsuarioRepositorio`, `SesionUsuarioRepositorio` e `IntentoAutenticacionRepositorio` en `app/Repositorios/`.
- Implementados los servicios de dominio en `app/Servicios/`:
  - `UsuarioServicio`: creación de usuario para persona activa, validación de contraseñas (10-128 chars), normalización de username, cambio de contraseña con revocación concurrente de sesiones previas (D-040).
  - `AutenticacionServicio`: autenticación segura con timing-attack dummy hash (D-037), migración automática de costo bcrypt con `password_needs_rehash()`, respuestas genéricas indistinguibles (`'Credenciales de acceso inválidas.'`), rate limiting de 5 intentos en 15 minutos sin alterar el estado del usuario.
  - `SesionServicio`: creación de sesiones en base de datos con expiración dual (30 min inactividad, 12 h absoluta), validación con verificación de vigencia de usuario/persona, revocación forzada y destrucción de cookies.
  - `CsrfServicio`: generación de tokens por sesión y verificación de tiempo constante mediante `hash_equals()` (D-041).
- Implementado el pipeline web de autenticación:
  - `AutenticacionIntermediario`: intermediario de protección de rutas privadas, redirección a `/login` con sanitización estricta del parámetro `return` contra Open Redirect (D-044).
  - `AutenticacionControlador`: controlador web siguiendo el patrón PRG (Post-Redirect-Get) para `GET /login`, `POST /login` y `POST /logout`.
  - Integración en `public/index.php`: ruta protegida `GET /` mediante `AutenticacionIntermediario`, soporte transparente del método HTTP `HEAD` en `Enrutador`, y métodos de consulta en `Respuesta`.
  - Ayudantes globales `usuario_autenticado()` y `csrf_campo()` en `app/Nucleo/Funciones.php`.
- Interfaz y vistas:
  - Creada la vista de autenticación `app/Vistas/auth/login.php` adaptada al diseño Alina (`sign-in.html` / `sign-in-2.html`) con campos flotantes, mensajes flash de error y token CSRF.
  - Añadido el asset visual oficial `public/assets/images/login/01.jpg`.
  - Actualizado el componente `cabecera.php` para renderizar el perfil del usuario autenticado y el formulario POST de cierre de sesión seguro con CSRF.
- Script de utilidad administrativa CLI:
  - Desarrollado `bin/crear-usuario-inicial.php` para inicialización idempotente del sistema, con restricción estricta a entornos de terminal (`PHP_SAPI === 'cli'`), creación atómica de persona si no existe, y sin exposición de contraseñas en consola ni logs (D-046).
- Suite integral de pruebas automatizadas:
  - Desarrollada y ejecutada la suite de 47 pruebas exhaustivas (47 PASS / 0 FAIL) cubriendo creación de usuarios, validaciones de dominio, ciclo de vida de sesiones, expiración por inactividad y absoluta, revocación concurrente, timing attacks, mitigación de session fixation, open redirect, cookies seguras, CSRF, rate limiting, restricción CLI y regresión de Identidad y Personal.
  - Verificada la limpieza completa de la base de datos (0 registros residuales en tablas operativas).
- Confirmada la intangibilidad total del catálogo `admin-dashboard/` (0 archivos modificados).

### Micro-lote PERSONAL-1A — Cierre de Invariantes Documentales y Temporales

- Implementada la migración evolutiva no destructiva `SQL/migraciones/004_ajustes_identidad_personal.sql` (lote 4):
  - Modelado estructural de jurisdicción en `tipos_documento`: incorporación de `pais_fijo_id` (FK a `paises`) y `pais_emisor_obligatorio`. DNI y Carné de Extranjería configurados con `pais_fijo_id = 1` (Perú) y `pais_emisor_obligatorio = 0`; Pasaporte configurado con `pais_fijo_id = NULL` y `pais_emisor_obligatorio = 1` (D-031).
  - Eliminación definitiva del centinela técnico `COALESCE(pais_emisor_id, 0)` y de la columna virtual `pais_emisor_efectivo` en `personas_documentos`.
  - `personas_documentos.pais_emisor_id` pasa a ser estrictamente `INT UNSIGNED NOT NULL`, respaldado por la clave foránea íntegra `fk_documentos_pais_emisor` e indexado con `UNIQUE (tipo_documento_id, pais_emisor_id, numero_documento)` (`uq_documentos_tipo_pais_numero`).
- Actualizados los modelos de dominio, repositorios y servicios de identidad:
  - `TipoDocumento` incorpora `paisFijoId`, `paisEmisorObligatorio` y métodos semánticos (`tienePaisFijo()`, `requierePaisEmisor()`).
  - `DocumentoPersonaRepositorio` y `PersonaServicio` consultan y validan directamente la jurisdicción real sin centinelas: resolución automática al país fijo cuando se omite, rechazo tajante de países no autorizados en documentos fijos y exigencia obligatoria de país emisor en pasaportes.
- Implementado el blindaje exhaustivo de la invariante temporal de no solapamiento de asignaciones de cargo dentro de un episodio laboral (D-032):
  - Métodos incorporados en `ColaboradorServicio`: `validarSolapamientoAsignacionCargo()` y `asignarCargoAEpisodio()`.
  - Rechazo de solapamiento cronológico entre asignaciones del mismo episodio, cubriendo tanto intervalos abiertos como cerrados (ej. Cargo A 01/01 a 30/06 y Cargo B 01/04 a 31/05 rechazado por `SolapamientoLaboralExcepcion`).
  - Verificación estricta de límites temporales respecto al episodio laboral padre (prohibición de iniciar antes de la contratación o iniciar/finalizar después del cese).
  - Protección de concurrencia mediante transacciones y bloqueos pesimistas (`SELECT ... FOR UPDATE`).
  - Integración de la validación en `cambiarCargo()`, preservando la contigüidad matemática estricta $D-1$ / $D$.
- Sincronizado el archivo consolidado oficial `SQL/camargo_pms.sql` con el nuevo esquema limpio de 10 tablas, finalizando con `SET FOREIGN_KEY_CHECKS = 1;`.
- Validada la paridad 100% de columnas e índices entre `SQL/camargo_pms.sql` y la base de datos `camargo_pms` mediante recreación aislada en base de datos temporal `camargo_pms_prueba_p1a`.
- Ampliada la suite automatizada a 26 pruebas (26 PASS / 0 FAIL), reteniendo las 14 pruebas de PERSONAL-1 e incorporando 7 pruebas de invariante documental estructural (T-DOC-A a T-DOC-G) y 5 pruebas de invariante temporal de cargos (T-CARGO-A a T-CARGO-E).
- Confirmada la intangibilidad total del catálogo `admin-dashboard/` (0 cambios).

### Fase PERSONAL-1 — Colaboradores, Cargos, Episodios Laborales y Ajuste Evolutivo de Identidad

- Consolidado el Principio de Separación Integral: `PERSONA ≠ COLABORADOR ≠ EPISODIO LABORAL ≠ CARGO ≠ USUARIO ≠ ROL` (D-026).
- Implementado el ajuste evolutivo no destructivo de Identidad mediante la migración `SQL/migraciones/003_personal_colaboradores.sql`:
  - Eliminada la restricción que exigía al menos un apellido (`chk_personas_al_menos_un_apellido`), habilitando soporte para monónimos legales y personas extranjeras.
  - Evolucionada la unicidad documental incorporando la jurisdicción de emisión mediante la columna virtual generada `pais_emisor_efectivo = COALESCE(pais_emisor_id, 0)` y la restricción `UNIQUE (tipo_documento_id, pais_emisor_efectivo, numero_documento)`, resolviendo colisiones entre pasaportes de distintos países con igual número y previniendo duplicados dentro del mismo país emisor (D-030).
- Creada la entidad y tabla `cargos` como catálogo administrable de funciones laborales (`ADMINISTRADOR`, `RECEPCIONISTA`, `RESERVAS`, `LIMPIEZA`, `MANTENIMIENTO`), totalmente desacoplado de permisos de seguridad de software.
- Creada la entidad y tabla `colaboradores` con código interno estable secuencial (`COL-XXXX`), estado (`ACTIVO`, `INACTIVO`) y restricción `UNIQUE (persona_id)` para garantizar la cardinalidad 1:1 lógica estricta (D-027).
- Creada la entidad y tabla `episodios_laborales` para registrar la vinculación laboral continua, con columna virtual generada `uq_colaborador_abierto` y restricción `UNIQUE` para forzar a nivel de base de datos a lo sumo un episodio abierto activo por colaborador (D-028).
- Creada la entidad y tabla `episodios_laborales_cargos` para registrar el historial inmutable de funciones por episodio, con columna virtual generada `uq_episodio_cargo_abierto` y restricción `UNIQUE` para forzar a lo sumo un cargo vigente por episodio.
- Implementada la convención temporal de continuidad en transiciones de cargo: cierre de la asignación previa en $D-1$ y apertura de la nueva asignación en $D$, con prohibición de solapamiento de fechas (D-029).
- Implementados los modelos puros de dominio: `Cargo`, `Colaborador`, `EpisodioLaboral` y `AsignacionCargo` en `app/Modelos/`.
- Implementados los repositorios con PDO parametrizado: `CargoRepositorio`, `ColaboradorRepositorio`, `EpisodioLaboralRepositorio` y `AsignacionCargoRepositorio` en `app/Repositorios/`.
- Implementadas las excepciones de dominio: `ColaboradorDuplicadoExcepcion`, `EpisodioLaboralActivoExcepcion` y `SolapamientoLaboralExcepcion` en `app/Excepciones/`.
- Implementado el servicio de dominio `app/Servicios/ColaboradorServicio.php` con métodos atómicos transaccionales: `crearColaborador`, `reingresarColaborador`, `cambiarCargo`, `cesarColaborador`, `obtenerSituacionActual` y bloqueos pesimistas `SELECT ... FOR UPDATE`.
- Sincronizado el archivo consolidado oficial `SQL/camargo_pms.sql` conteniendo las 10 tablas del sistema y finalizando explícitamente con `SET FOREIGN_KEY_CHECKS = 1;`.
- Verificada la reconstrucción estructural limpia desde cero en base de datos temporal aislada `camargo_pms_prueba_personal` con 100% de paridad con `camargo_pms`.
- Ejecutada la suite completa de 14 pruebas automatizadas (14 PASS / 0 FAIL), incluyendo monónimos, pasaportes internacionales con igual número, unicidad 1:1 Persona-Colaborador, transiciones de cargo, cese sin borrado físico, reingreso sin duplicar colaborador, rechazo de solapamiento temporal, atomicidad transaccional y regresión completa del núcleo de identidad.
- Verificada la intangibilidad total del catálogo `admin-dashboard/` (0 archivos modificados).

### Fase IDENTIDAD-1 — Núcleo de Identidad Humana (Personas Naturales)

- Diseñado e implementado el modelo relacional del maestro de Personas Naturales bajo el principio `PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL` y la separación de entidades normalizadas `Persona`, `DocumentoPersona` y `ContactoPersona`.
- Creados los catálogos relacionales `paises` (ISO-3166-1 alpha-2 y alpha-3 únicos) con semilla estructural de Perú ('PE', 'PER', 'Perú', 'Peruana'), y `tipos_documento` con semillas estructurales para DNI (8 dígitos exactos), Pasaporte y Carné de Extranjería (CE).
- Excluido formalmente el RUC como tipo de documento personal de personas naturales, reservándolo para personas jurídicas / fiscalidad (D-022).
- Creada la migración oficial `SQL/migraciones/002_identidad_personas.sql` y sincronizado el esquema consolidado `SQL/camargo_pms.sql` finalizando explícitamente con `SET FOREIGN_KEY_CHECKS = 1;`.
- Implementadas restricciones de integridad a nivel de base de datos: `UNIQUE (tipo_documento_id, numero_documento)` para unicidad documental estricta, y columnas virtuales generadas `uq_persona_principal` y `uq_contacto_tipo_principal` para garantizar que no existan múltiples documentos principales o múltiples contactos principales del mismo tipo activos para una persona.
- Implementado el indicador booleano `es_whatsapp` en contactos telefónicos para evitar la duplicación innecesaria de números físicos.
- Implementada la política de soft delete mediante el campo `estado` (`ACTIVO`, `INACTIVO`) en personas, documentos y contactos, preservando registros históricos y de auditoría sin borrados físicos destructivos.
- Implementadas las entidades de dominio puras en `app/Modelos/` (`Persona`, `DocumentoPersona`, `ContactoPersona`, `Pais`, `TipoDocumento`) totalmente desacopladas de PDO.
- Implementados los repositorios en `app/Repositorios/` (`PersonaRepositorio`, `DocumentoPersonaRepositorio`, `ContactoPersonaRepositorio`, `PaisRepositorio`, `TipoDocumentoRepositorio`) con consultas SQL estrictamente preparadas y parametrizadas.
- Implementadas las excepciones de dominio en `app/Excepciones/` (`ValidacionExcepcion`, `DocumentoDuplicadoExcepcion`, `EntidadNoEncontradaExcepcion`).
- Implementado el servicio de dominio `app/Servicios/PersonaServicio.php` con validación sintáctica, normalización, control de fechas futuras, gestión de documentos/contactos principales y límites transaccionales atómicos (rollback probado ante fallos).
- Verificado el banco completo de pruebas técnicas de dominio y persistencia (27 PASS / 0 FAIL), incluyendo idempotencia de migraciones, validación sintáctica DNI, detección de duplicados, transaccionalidad, extranjeros sin segundo apellido, soft delete y reconstrucción aislada desde cero en base de datos temporal `camargo_pms_prueba_identidad`.
- Verificada la intangibilidad total del catálogo `admin-dashboard/`.

### Fase INFRA-1 — Infraestructura de configuración, base de datos, PDO y migraciones

- Ejecutada auditoría estricta de solo lectura sobre la base de datos local `camargo_pms`, confirmando el motor MySQL Community Server 8.4.3 LTS (GPL), almacenamiento InnoDB, charset `utf8mb4`, collation `utf8mb4_0900_ai_ci`, `sql_mode` estricto y esquema inicial completamente vacío (Clasificación A).
- Resuelto formalmente P-001 registrando la decisión D-018 que oficializa MySQL 8.4.3 LTS como motor de base de datos del proyecto.
- Resuelto formalmente P-002 registrando la decisión D-019 que adopta Composer 2.x para dependencias mínimas (`vlucas/phpdotenv`) y autoloading PSR-4 (`CamargoPMS\` -> `app/`), preservando el enrutador nativo `app/Nucleo/Enrutador.php`.
- Formalizada la decisión D-020 estableciendo el principio de persistencia sincronizada: `SQL/camargo_pms.sql` como esquema consolidado oficial y versionado, y `SQL/migraciones/` para la evolución incremental determinista.
- Creada la migración inicial `SQL/migraciones/001_infraestructura.sql` para la tabla técnica de control `migraciones` (`id`, `migracion`, `lote`, `ejecutado_en`).
- Implementada la plantilla versionada de entorno `.env.example` y la configuración local privada `.env` (debidamente ignorada por Git).
- Implementada la capa centralizada de configuración `app/Nucleo/Configuracion.php` con soporte para variables de entorno seguras.
- Implementada la fábrica centralizada de conexiones `app/Nucleo/BaseDatos.php` con opciones estrictas de PDO, emulación de prepares desactivada y manejo seguro de excepciones sin filtrar credenciales.
- Implementado el runner CLI de migraciones `app/Nucleo/Migrador.php` y el comando de consola `migrar.php` protegido contra accesos web.
- Validadas exitosamente la conexión PDO, la ejecución de la migración inicial y la completa idempotencia en la segunda ejecución (0 migraciones pendientes).
- Validada la reconstrucción completa del esquema desde cero en una base de datos temporal aislada (`camargo_pms_prueba_infra`), eliminada inmediatamente tras la verificación.
- Confirmada la ausencia total de tablas funcionales de negocio y la inmutabilidad íntegra de `admin-dashboard/`.

### Fase UI-1 — Validación técnica/visual y consolidación documental

- Ejecutada la validación técnica y visual real bajo Apache/Laragon (PID 25016, puertos 80 y 443) tanto en VirtualHost (`https://app.camargo-pms.test/`) como en subcarpeta (`http://localhost/app.camargo-pms/`).
- Verificada la entrega con HTTP 200 de los 18 assets vinculados (CSS, JS, fuentes Tabler e imágenes), confirmando 0 errores 404 en assets.
- Verificado el renderizado del DOM en navegador Chrome/Edge headless, confirmando remoción automática del cargador (`.loader-wrapper`), aplicación de clase de tema en `<body>` y 0 excepciones de JavaScript.
- Validadas las respuestas HTTP reales ante rutas no encontradas (HTTP 404 real) e invocación defensiva de errores 500 con plantilla centrada `.error-container`.
- Consolidado en gobernanza el Principio de Separación de Identidad (`PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL`).
- Formalizado el diseño documental del maestro central de Personas (documentos DNI/Pasaporte, nacionalidad inicial Perú por defecto y datos de contacto).
- Documentada la Gestión de Personal, catálogo administrable de Cargos laborales e Historial Laboral inmutable por episodios cronológicos.
- Formalizada la distinción entre Cargo laboral y Rol de seguridad, catálogo dinámico de Roles y permisos atómicos `recurso.accion`.
- Incorporada la regla de seguridad vinculante `OCULTAR EL MENÚ NO ES AUTORIZACIÓN` con intermediarios de backend y respuesta HTTP 403 real.
- Formalizado el diseño del módulo de Gestión de Menú Dinámico con soporte drag & drop, vinculación opción ↔ permiso y contrato Alina `data-target` ↔ `id`.
- Establecida la frontera conceptual entre Personal y Caja (Personal como contraparte de gastos/sueldos, sin convertir Personal en subsistema contable).
- Aprobada y registrada la decisión D-017 de integración externa con APIsPERU (DNI de 8 dígitos y RUC de 11 dígitos) desacoplada mediante `ServicioConsultaIdentidad` y adaptadores.
- Reafirmada la auditoría transversal y la diferenciación estricta de actores (`USER`, `SYSTEM`, `INTEGRATION`, `PAYMENT_PROVIDER`).
- Verificada la inmutabilidad total de `admin-dashboard/`.

### Fase UI-0 — Esqueleto MVC y plantilla Alina reutilizable

- Creado el Front Controller en `public/index.php` con manejo defensivo de errores y resolución dinámica de URL base.
- Implementado el motor de renderizado seguro `Vista` y la clase `Respuesta` con cabeceras de seguridad.
- Implementado el `Autocargador` PSR-4 propio para el espacio de nombres `CamargoPMS\` y `Enrutador` mínimo reversible.
- Formalizadas las referencias visuales oficiales de Alina: `blank.html` (layout general), `sign_in.html` (login), `index.html` (dashboard) y `error_*.html` (errores HTTP).
- Adaptada la plantilla principal `principal.php` preservando el árbol DOM de `blank.html`.
- Extraídos 9 componentes reutilizables: `head`, `cargador`, `navegacion`, `menu-principal`, `menu-secundario`, `cabecera`, `migas-pan`, `pie` y `scripts`.
- Desarrollado el script propio de layout `camargo-layout.js` en JavaScript moderno nativo (sin jQuery ni scripts demo) asegurando el contrato `data-target` ↔ `id`, responsive, tema claro/oscuro y scroll.
- Copiados selectivamente los assets esenciales de Bootstrap 5, Tabler Icons, Simplebar, CSS de Alina e ilustraciones de error; eliminadas rutas relativas frágiles mediante ayudantes de URL absoluta.
- Creada la vista neutra de comprobación en `app/Vistas/panel/inicio.php`.
- Implementada la arquitectura de errores con plantilla aislada `plantillas/error.php` (.error-container) y vista reutilizable `errores/error.php` para 400, 403, 404, 500 y 503.
- Creado `camargo.css` para estilos propios sin alterar los originales de Alina.
- Verificada la inmutabilidad íntegra de `admin-dashboard/`.

### Fase G-1 — Git y baseline documental

- Definida `main` como rama oficial y el repositorio GitHub de Camargo PMS como `origin`.
- Creado `.gitignore` para secretos, dependencias, runtime, IDE, artefactos, respaldos y bases locales.
- Aprobado versionar intactos Alina y su documentación como referencia reproducible.
- Mantenido el Figma local e intacto fuera de Git, sin incorporar Git LFS.
- Incorporado el control de SHA, working tree y ahead/behind a los gates de micro-baseline.
- Preparado el primer baseline documental sin código funcional, Composer ni base de datos.

### Fase G-0 — Gobernanza

- Creada la entrada obligatoria `AGENTS.md`.
- Definidas arquitectura por capas y nomenclatura propia en español.
- Documentados dominio, seguridad, base de datos, API, auditoría, pruebas, Git y roadmap.
- Registrado el contrato acoplado de menús Alina y la estrategia de assets mínimos.
- Documentada la preservación íntegra de los originales Alina.
- Preparados skills locales especializados.

No se ha iniciado Git, PHP, base de datos ni implementación funcional.
