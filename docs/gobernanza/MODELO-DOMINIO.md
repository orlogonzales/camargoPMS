# Modelo de dominio inicial

Este documento fija conceptos e invariantes, no tablas definitivas. El diseño físico se realizará por módulo y mediante migraciones revisadas.

## Núcleo inmobiliario

```text
Propiedad (Inmueble Físico Raíz) ──[1:N]──> Nivel/Piso ──[1:N]──> Unidad (Alojamiento Arrendable)
```

El modelo de Camargo PMS fija el **Principio de Separación Inmobiliaria**:

```text
PROPIEDAD (Contenedor Físico) ≠ UNIDAD (Espacio Arrendable Comercializable)
```

Esta separación es vinculante y rige la arquitectura del dominio:

1. **Propiedad (`propiedades` — PROPIEDADES-1):**
   - Modela única y exclusivamente el contenedor físico, edificación, lote o inmueble raíz (ej. "Edificio Ayuda Mutua", "Casona Principal", "Villas del Valle").
   - Atributos ontológicos: `codigo` (alfanumérico único en mayúsculas), `nombre` (denominación del predio), `direccion` (dirección física obligatoria, no vacía), `distrito`, `provincia`, `departamento`, `pais_id` (vinculación obligatoria al catálogo `paises`), `codigo_postal`, `latitud` y `longitud` (coordenadas GPS opcionales con validación estricta de rangos geográficos: `[-90, 90]` y `[-180, 180]`), y canales de contacto (`telefono_contacto`, `email_contacto`).
   - **Principio `PROPIEDAD ≠ REGISTRO DESECHABLE`:** Cero eliminación física (`DELETE FROM propiedades` = 0). Las propiedades no se borran bajo ninguna circunstancia operativa; su ciclo de vida se administra exclusivamente mediante la alternancia operativa `ACTIVO` / `INACTIVO`.
   - **Prohibición ontológica:** Se prohíbe incorporar atributos de unidades arrendables, tipologías de habitación, camas, amenidades específicas, disponibilidad, tarifas, estancias o bloqueos dentro de la entidad `Propiedad`.
   - Trazabilidad integral de operaciones (`CREAR`, `EDITAR`, `DESACTIVAR`, `ACTIVAR`) en `auditoria` bajo D-061.

2. **Unidad Física y Tipología (`unidades`, `tipos_unidad` — UNIDADES-1):**
   - La unidad (`unidades`) modela la división física habitable, arrendable o alojable (departamento, habitación, bungalow, suite) subordinada estrictamente a su inmueble raíz (`propiedad_id NOT NULL`). No existen unidades huérfanas en el sistema.
   - Atributos ontológicos: `codigo` (alfanumérico único por propiedad), `nombre`, `tipo_unidad_id` (`tipos_unidad`), `piso_nivel` (alfanumérico opcional), `capacidad_personas` (entero positivo), `dormitorios` (entero no negativo, admitiendo 0 para monoambiente/estudio), `banos` (decimal para medios baños), `area_m2` (decimal opcional), `descripcion`, `observaciones` y `estado` (`ACTIVO`, `INACTIVO`).
   - **Principio `UNIDAD ≠ REGISTRO DESECHABLE`:** Cero eliminación física (`DELETE FROM unidades` = 0). Preservación histórica mediante alternancia operativa `ACTIVO` ↔ `INACTIVO`.
   - **Unicidad Scoped por Propiedad (`UNIQUE(propiedad_id, codigo)`):** El código técnico es único dentro de cada inmueble, admitiendo códigos idénticos entre propiedades distintas (D-065).
   - **Regla de Inmueble Inactivo:** Se prohíbe registrar nuevas unidades en propiedades inactivas; la propiedad inactiva preserva íntegramente sus unidades históricas.
   - **Principio `UNIDAD ≠ RESERVA / TARIFA / DISPONIBILIDAD`:** Exclusión deliberada de fechas de corte, calendarios, tarifas y precios en esta fase (P-004, P-005, P-006 abiertas).
   - Trazabilidad integral de operaciones (`CREAR`, `EDITAR`, `DESACTIVAR`, `ACTIVAR`) en `auditoria` bajo D-061.

## Identidad, Personas y Personal

El modelo de Camargo PMS establece el **Principio de Separación de Identidad**:

```text
PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL
```

Esta separación es vinculante y rige toda la arquitectura:

- Una **Persona** puede existir en el sistema sin ser colaborador ni tener usuario (ej. huéspedes, contactos, clientes).
- Un **Colaborador** siempre se vincula a una Persona humana preexistente, pero puede existir sin una cuenta de Usuario.
- Un **Usuario** representa una cuenta de acceso a la plataforma vinculada a una Persona humana; no todo colaborador requiere acceso al sistema.
- **Cargo** y **Rol** son conceptos radicalmente distintos:
  - **Cargo:** Define el puesto o función laboral que desempeña un colaborador en la empresa (ej. Recepcionista, Limpieza, Administrador). No confiere permisos de software automáticamente.
  - **Rol:** Agrupa permisos de seguridad para operar funciones dentro de Camargo PMS (ej. Ventas, Operaciones, Superadministrador). Nunca se infieren permisos a partir del cargo.

### Maestro de Personas Naturales (Separación Persona / Documento / Contacto)

Entidad central reutilizable que consolida los datos de identificación y contacto de individuos naturales, normalizada en tres estructuras para evitar redundancia y columnas planas:

1. **Persona Natural (`personas`):**
   - Atributos ontológicos: nombres (obligatorio), apellido paterno, apellido materno (nulabilidad defensiva para extranjeros o personas con un solo apellido/monónimos legales), fecha de nacimiento (nullable, sin fechas futuras), país de nacionalidad (`paises`) y dirección residencial básica.
   - Estado de operación (`ACTIVO`, `INACTIVO`) para soft delete.
   - El nombre completo no se persiste; se computa dinámicamente en capa de aplicación.

2. **Documentos Personales (`personas_documentos`):**
   - Relación 1:N con `tipos_documento` (DNI, Pasaporte, Carné de Extranjería).
   - **Jurisdicción Documental Estructural (PERSONAL-1A):** Modelada mediante atributos ontológicos en `tipos_documento`:
     - `pais_fijo_id`: Documentos de jurisdicción exclusiva fija (DNI y CE fijados a Perú). Si no se provee país emisor, el servicio resuelve automáticamente a dicho país fijo; cualquier intento de asociar otro país es rechazado.
     - `pais_emisor_obligatorio`: Documentos internacionales donde el país emisor es mandatorio (ej. PASAPORTE).
   - `pais_emisor_id` es estrictamente `NOT NULL` con clave foránea referencial íntegra (`fk_documentos_pais_emisor`) hacia `paises(id)`. Se prohíbe el uso de centinelas técnicos artificiales (`COALESCE(pais_emisor_id, 0)`).
   - Unicidad documental natural: `UNIQUE (tipo_documento_id, pais_emisor_id, numero_documento)`. Permite que personas distintas porten el mismo número si fueron emitidos por países diferentes (ej. pasaportes de Chile y Argentina), impidiendo duplicados en una misma jurisdicción.
   - Regla de dominio e integridad DB: exactamente un documento principal activo por persona (`uq_persona_principal`).
   - **Regla vinculante:** El RUC **no** forma parte del catálogo de documentos personales de personas naturales; pertenece al modelado de identidad fiscal y personas jurídicas.

3. **Medios de Contacto (`personas_contactos`):**
   - Relación 1:N con medios normalizados (`TELEFONO`, `EMAIL`).
   - Teléfono móvil con indicador booleano `es_whatsapp` para evitar duplicar el mismo número físico.
   - Regla de dominio e integridad DB: un contacto principal activo por tipo y persona (`uq_contacto_tipo_principal`).
   - Normalización de emails en minúsculas y teléfonos limpios.

### Personal y Colaboradores

Representa la identidad laboral estable de una Persona con Camargo Hostelería:

- Cardinalidad estricta 1:1 lógica con `personas` (`uq_colaboradores_persona`). Una persona física nunca tiene más de un registro de colaborador.
- Código interno único secuencial e inmutable (`COL-XXXX`).
- Estado (`ACTIVO`, `INACTIVO`).
- Desacoplado de usuarios de software y roles.

### Historial Laboral por Episodios y Asignaciones de Cargo

El historial laboral es inmutable y no se sobreescribe cuando un colaborador se reincorpora o cambia de función. Se organiza jerárquicamente en dos niveles relacionales:

1. **Episodios Laborales (`episodios_laborales`):**
   - Representa un período continuo de vinculación laboral desde el ingreso hasta el cese.
   - Atributos: `fecha_inicio`, `fecha_fin` (NULL si está activo), `motivo_cese` (RENUNCIA, DESPIDO, MUTUO_ACUERDO, FIN_CONTRATO, JUBILACION, OTRO), `observaciones`, `estado` (`ACTIVO`, `INACTIVO`).
   - Integridad DB: Columna virtual generada `uq_colaborador_abierto` para forzar a lo sumo un episodio abierto activo por colaborador.
   - Reingreso: Un colaborador cesado reingresa mediante la creación de un nuevo episodio laboral con nueva fecha de inicio, sin crear un nuevo registro en `colaboradores` y reactivando su estado general.
   - Regla temporal: Prohibición estricta de solapamiento de fechas con episodios anteriores.

2. **Asignaciones de Cargo (`episodios_laborales_cargos`):**
   - Mantiene la trazabilidad histórica de los puestos o funciones ocupados dentro de un episodio laboral específico.
   - Atributos: `episodio_laboral_id`, `cargo_id`, `fecha_inicio`, `fecha_fin` (NULL si es el cargo vigente), `observaciones`.
   - Integridad DB: Columna virtual generada `uq_episodio_cargo_abierto` para forzar a lo sumo un cargo vigente activo por episodio.
   - **Invariante Temporal de No Solapamiento (PERSONAL-1A):** Ninguna asignación de cargo puede solaparse cronológicamente con otra dentro del mismo episodio, aplicable tanto a intervalos abiertos como cerrados (ej. Cargo A: 01/01 a 30/06 y Cargo B: 01/04 a 31/05 es rechazado tajantemente).
   - **Límites con el Episodio:** Toda asignación debe iniciar en o después del inicio del episodio y finalizar en o antes del cese del episodio; un episodio cerrado no admite cargos abiertos o indefinidos.
   - Transición de cargo (ascenso o cambio funcional): La asignación anterior se cierra en $D-1$ y la nueva se abre en $D$, garantizando continuidad temporal estricta validada por `ColaboradorServicio::validarSolapamientoAsignacionCargo()`.

```text
Persona X (ID 1)
└── Colaborador (ID 1, COL-0001, ACTIVO)
    ├── Episodio Laboral 1 (01/02/2026 - 30/06/2026 | INACTIVO | Cese: FIN_CONTRATO)
    │   └── Asignación Cargo 1 (01/02/2026 - 30/06/2026 | Cargo: RECEPCIONISTA)
    └── Episodio Laboral 2 (01/08/2026 - Abierto | ACTIVO) [Reingreso]
        ├── Asignación Cargo 2 (01/08/2026 - 30/09/2026 | Cargo: RECEPCIONISTA)
        └── Asignación Cargo 3 (01/10/2026 - Abierto | Cargo: ADMINISTRADOR) [Ascenso]
```

### Catálogo de Cargos

Catálogo dinámico y administrable de funciones laborales (`cargos`). Semillas estructurales iniciales:
- `ADMINISTRADOR`: Gestión general operativa del establecimiento.
- `RECEPCIONISTA`: Atención al huésped, check-in, check-out y soporte en mostrador.
- `RESERVAS`: Gestión comercial de reservas y asignaciones.
- `LIMPIEZA`: Aseo, desinfección y preparación de unidades.
- `MANTENIMIENTO`: Reparaciones técnicas e infraestructura.

Los cargos laborales no confieren permisos en el software; describen exclusivamente funciones dentro de la organización.

### Gestión de Usuarios y Sesiones (AUTH-1)

Cuentas humanas de acceso al software Camargo PMS:

- **Vinculación 1:1 con Persona Humana (`usuarios.persona_id UNIQUE NOT NULL REFERENCES personas(id)`):**
  - Un usuario representa la credencial de acceso de un individuo humano.
  - Totalmente desacoplado de `colaboradores`; la condición laboral es un rol de dominio que emana de `personas`, no una identidad de acceso técnico.
  - Prohibición de `colaborador_id` en usuarios.
- **Nombres de Usuario Canónicos y Normalizados:**
  - `nombre_usuario VARCHAR(50)`: Nombre con formato de presentación visual.
  - `nombre_usuario_normalizado VARCHAR(50) UNIQUE`: Normalizado estrictamente en minúsculas y sin espacios para evitar colisiones y suplantación insensible a mayúsculas.
- **Contraseñas Criptográficamente Seguras (AUTH-1A):**
  - Almacenadas exclusivamente como hash en `contrasena_hash VARCHAR(255) NOT NULL` (algoritmo estándar `PASSWORD_DEFAULT`).
  - Política de longitud: mínimo 12 caracteres, máximo 1024 caracteres evaluada antes del hash. Sin reglas artificiales de composición ni transformaciones destructivas (`trim`, truncamiento); soporte pleno de espacios y caracteres Unicode.
  - Mecanismo transparente de rehash automático (`password_needs_rehash()`) con `PASSWORD_DEFAULT` en inicios de sesión exitosos.
  - Mitigación contra ataques de temporización (timing attacks) y enumeración de usuarios mediante verificación con hash dummy precalculado con `PASSWORD_DEFAULT` y mensajes genéricos uniformes.
- **Estados de Cuenta:**
  - `ACTIVO`: Cuenta habilitada para autenticarse.
  - `INACTIVO`: Cuenta deshabilitada administrativamente (invalida sesiones inmediatamente).
  - `BLOQUEADO`: Cuenta bloqueada por seguridad.
- **Gestión de Sesiones de Usuario (`sesiones_usuario`):**
  - Tokens opacos de 64 caracteres hexadecimales (32 bytes criptográficamente seguros con CSPRNG).
  - En base de datos se almacena únicamente el hash unidireccional `token_hash CHAR(64) UNIQUE` (SHA-256).
  - Política de expiración dual: inactividad (> 30 minutos desde `ultimo_acceso_en`) y vencimiento absoluto (> 12 horas desde `creado_en`).
  - Revocación concurrente: al cambiar de contraseña, se revocan todas las demás sesiones activas en base de datos preservando únicamente la actual.
- **Rate Limiting y Control de Fuerza Bruta (`intentos_autenticacion`):**
  - Registro de intentos fallidos con IP, identificador, timestamp y resultado.
  - Throttling a partir de 5 intentos fallidos en una ventana móvil de 15 minutos, sin alterar el estado del usuario (`usuarios.estado` permanece `ACTIVO`).
- **Seguridad Web:**
  - Protección CSRF obligatoria en peticiones sensibles mediante tokens vinculados a la sesión validados con `hash_equals()`.
  - Regeneración de identificador de sesión PHP (`session_regenerate_id(true)`) al autenticar para mitigar fijación de sesión.
  - Sanitización estricta de rutas de retorno para mitigar redirección abierta (Open Redirect).
  - Cookies configuradas con `HttpOnly = true`, `SameSite = Lax` y `Secure` condicional a HTTPS.

### Catálogo de Roles y Permisos (ROLES-1)

- **Arquitectura de Autorización:** Control de acceso basado en roles (RBAC) estructurado en dos relaciones muchos a muchos normalizadas:
  `Usuario (1) <---> (N) usuarios_roles (N) <---> (1) Rol (1) <---> (N) roles_permisos (N) <---> (1) Permiso`.
- **Roles (`roles`):**
  - Identificados unívocamente por su código técnico (`codigo VARCHAR(50) UNIQUE`, ej. `SUPERADMINISTRADOR`, `RECEPCION`).
  - Nombre representativo (`nombre VARCHAR(100)`), descripción opcional y estado (`ACTIVO`, `INACTIVO`).
  - Banderas estructurales: `es_sistema` (roles base protegidos) y `es_superadministrador` (rol estructural con autoridad absoluta).
- **Permisos Atómicos (`permisos`):**
  - Capacidades atómicas de seguridad expresadas obligatoriamente en formato canónico `recurso.accion` en minúsculas (ej. `usuarios.ver`, `usuarios.crear`, `usuarios.editar`, `usuarios.bloquear`, `roles.ver`, `roles.crear`, `roles.editar`, `roles.asignar`, `roles.revocar`, `permisos.ver`).
  - Cada permiso pertenece a un `modulo` y mantiene estado (`ACTIVO`, `INACTIVO`).
- **Naturaleza Aditiva de Permisos:**
  - Para usuarios normales, los permisos efectivos corresponden estrictamente a la **unión aditiva** de todos los permisos asociados a sus roles asignados activos.
  - Se prohíben sobreescrituras a nivel de usuario (`usuarios_permisos`), jerarquías de herencia de roles y sustracciones de permisos.
- **Autoridad Total del Superadministrador:**
  - El rol estructural `SUPERADMINISTRADOR` confiere autoridad total e incondicional sobre todos los recursos y acciones de la plataforma. Dicha autoridad se reconoce centralmente en `AutorizacionServicio`: si el usuario posee el rol activo, su cuenta está activa y su persona está activa, el método `puede()` retorna `true` inmediatamente, sin necesidad de listar exhaustivamente cada permiso en `roles_permisos` y garantizando inmunidad ante permisos creados en fases futuras.
  - El rol `SUPERADMINISTRADOR` está protegido: se prohíbe renombrar su código, desactivarlo o eliminarlo de la base de datos (`RolProtegidoExcepcion`).
- **Invariante del Último Superadministrador Activo:**
  - El sistema garantiza que siempre exista al menos un Superadministrador humano activo y habilitado para operar la plataforma.
  - Se prohíbe categóricamente revocar el rol `SUPERADMINISTRADOR`, bloquear o desactivar la cuenta de usuario, o desactivar la persona natural asociada, si se trata del único Superadministrador activo restante (`UltimoSuperadministradorExcepcion`).
  - La comprobación se ejecuta bajo transacciones con bloqueo pesimista de filas (`FOR UPDATE`) para asegurar concurrencia estricta sin condiciones de carrera. No se depende de identificadores estáticos hardcodeados.

### Subsistema de Navegación y Menú Dinámico (MENÚ-1)

- **Entidad `OpcionMenu`:**
  - Modela un elemento de navegación jerárquico sujeto a un máximo estricto de dos niveles conforme al contrato de la plantilla Alina:
    - **Nivel 1 (Categoría Principal):** `padre_id = NULL`. Proporciona el punto de entrada horizontal superior (`navbar-menu-list` con `data-target="clave"`), un icono representativo y agrupa opciones funcionales secundarias.
    - **Nivel 2 (Opción Secundaria):** `padre_id != NULL`. Proporciona el enlace navegable final en el menú vertical desplegable (`main-side-menu` con `id="clave"`), con una ruta interna relativa saneada y un permiso RBAC opcional de visibilidad.
- **Invariantes y Reglas de Dominio:**
  - **Principio "MENÚ ≠ AUTORIZACIÓN":** El árbol de navegación dinámico es exclusivamente una interfaz visual de usuario. La visibilidad de un elemento se calcula mediante `AutorizacionServicio::puede()` o el rol `SUPERADMINISTRADOR`, pero el acceso al recurso o endpoint está garantizado de forma independiente en backend por `AutorizacionIntermediario` y la lógica interna de los controladores.
  - **Filtro de Categorías Válidas:** Una categoría principal solo es visible para el usuario si está activa, autorizada y cuenta con al menos una opción secundaria activa y visible. Las categorías vacías se omiten automáticamente para evitar contenedores muertos en la interfaz.
  - **Protección Estructural de Sistema:** Las opciones marcadas con `es_sistema = 1` (ej. `config_menu`) están protegidas a nivel de servicio y no pueden ser eliminadas físicamente ni desactivadas (`OpcionMenuProtegidaExcepcion`), impidiendo que el sistema quede sin acceso a su propia administración.
  - **Reordenamiento Transaccional Atómico:** Las opciones de un mismo nivel y padre se reordenan mediante listas atómicas de pares `[id, orden]`, garantizando consistencia relacional y aplicando rollback total ante cualquier incongruencia.
  - **Rutas Saneadas:** Solo se admiten rutas relativas internas; se rechazan esquemas externos o vectores maliciosos (`javascript:`, `data:`, `vbscript:`).

### Relación entre Personal y Caja

Un colaborador puede ser contraparte, beneficiario o responsable de transacciones financieras:

- Fondos por rendir y dinero entregado para compras operativas.
- Rendiciones de gastos, devoluciones y reembolsos.
- Pago de honorarios, sueldos o remuneraciones.

**Regla de diseño:** Personal **no** es un subsistema financiero. El módulo de Finanzas/Caja mantiene sus propias entidades, saldos y comprobantes, limitándose a referenciar al colaborador/persona correspondiente en cada movimiento.
### Actores y Núcleo Transversal de Auditoría (AUDITORÍA-1)

El modelo de Camargo PMS formaliza el principio:

```text
ACTOR ≠ USUARIO
```

1. **Entidad `ActorAuditoria` (`actores`):**
   - Representa el sujeto o componente que origina una acción en el sistema.
   - Atributos: `id`, `tipo` (`TipoActor`: `USUARIO`, `SISTEMA`, `INTEGRACION`, `PROVEEDOR_PAGO`), `codigo` único de referencia, `nombre` descriptivo, `usuario_id` nullable (para actores humanos), `metadatos` en JSON, `creado_en`.
   - Semilla estructural protegida: `CAMARGO_PMS` (`id = 1`, `tipo = SISTEMA`).
   - Los usuarios humanos tienen un actor sincronizado 1:1 (`tipo = USUARIO`, `codigo = USR_{id}`).

2. **Entidad `RegistroAuditoria` (`auditoria`):**
   - Mantiene la bitácora inmutable de eventos operacionales y de seguridad.
   - Atributos: `id`, `actor_id` (RESTRICT), `usuario_id` nullable (SET NULL), `accion` (`AccionAuditoria`: `LOGIN`, `LOGOUT`, `CREAR`, `EDITAR`, `ACTIVAR`, `DESACTIVAR`, `ASIGNAR`, `REVOCAR`, `REORDENAR`, `ELIMINAR`), `modulo`, `entidad`, `entidad_id` nullable, `valores_anteriores` (JSON sanitizado), `valores_nuevos` (JSON sanitizado), `metadatos` (JSON sanitizado), `ip`, `user_agent`, `metodo_http`, `ruta`, `correlacion_id`, `creado_en`.
   - **Invariante de Inmutabilidad:** Registro append-only sin operaciones de actualización ni borrado en tiempo de ejecución.
   - **Invariante de Privacidad y Seguridad:** Prohibición estricta de contraseñas, hashes, tokens CSRF, cabeceras de autorización o claves API mediante desinfección obligatoria en `SanitizadorAuditoria`.

### Administración Integral de Cuentas Humanas (USUARIOS-1)

- **Principio Vinculante `PERSONA ≠ USUARIO ≠ COLABORADOR ≠ ROL`:**
  - El Usuario representa exclusivamente la credencial humana de acceso a Camargo PMS vinculada 1:1 de forma unívoca a una Persona natural del maestro central (`usuarios.persona_id UNIQUE NOT NULL REFERENCES personas(id)`).
  - Queda categóricamente prohibida la creación de usuarios sintéticos, cuentas de servicio o usuarios comodín para procesos automáticos, cron, WordPress o integraciones externas (los cuales se modelan bajo el principio `ACTOR ≠ USUARIO` como actores de tipo `SISTEMA` o `INTEGRACION`).
- **Política de Ciclo de Vida y Prohibición de Eliminación Física:**
  - Las cuentas de usuario no admiten borrado físico (`DELETE FROM usuarios` está prohibido en servicio y repositorio).
  - El ciclo de vida operacional se administra exclusivamente mediante transiciones de estado explícitas:
    - `ACTIVO`: Cuenta habilitada para autenticarse y operar en la plataforma.
    - `INACTIVO`: Cuenta deshabilitada administrativamente (invalida sus sesiones activas de inmediato).
    - `BLOQUEADO`: Cuenta suspendida por motivos de seguridad o prevención operativa.
- **Invariante del Último Superadministrador Activo:**
  - El sistema garantiza que siempre exista al menos un Superadministrador humano en estado `ACTIVO` con persona en estado `ACTIVO`.
  - Queda prohibido bloquear o desactivar la cuenta del último superadministrador activo (`UltimoSuperadministradorExcepcion`), con verificación protegida mediante bloqueos pesimistas (`FOR UPDATE`) en base de datos.
- **Gestión Segura de Credenciales y Sesiones:**
  - Restablecimiento administrativo de contraseñas gobernado por las políticas criptográficas consolidadas: algoritmo estándar `PASSWORD_DEFAULT`, longitud de 12 a 1024 caracteres, preservación de espacios y caracteres Unicode sin normalizaciones destructivas ni truncamiento.
  - La alteración de credenciales revoca de inmediato todas las sesiones activas concurrentes del usuario en `sesiones_usuario` fijando `revocada_en = NOW()`.
  - Soporte de revocación selectiva de sesiones activas o revocación masiva total.
- **Auditoría Transversal Nativa:**
  - Todas las mutaciones de cuentas (`CREAR`, `CAMBIAR_ESTADO`, `CAMBIAR_CLAVE`) emiten eventos de auditoría a través de `AuditoriaServicio`, garantizando trazabilidad contextual (`ip`, `user_agent`, `ruta`, `correlacion_id`) y desinfección total de contraseñas mediante `SanitizadorAuditoria`.

## Ocupación y disponibilidad

- Reserva: intención comercial o bloqueo con titular y condiciones de pago.
- Estancia: ocupación efectiva de corta duración.
- Arrendamiento: relación contractual normalmente mensual o prolongada.
- Bloqueo: indisponibilidad administrativa o bloqueo de propietario.
- Mantenimiento: indisponibilidad física por reparaciones o acondicionamiento.

### Modelo Temporal Hotelero (D-066 / P-004)

1. **Separación ontológica:** `INSTANTE ≠ FECHA HOTELERA ≠ HORARIO OPERACIONAL`.
   - **Instante técnico:** Representa un punto exacto en la línea de tiempo global (creación, auditoría, sesiones, tokens, webhooks) almacenado normalizado en UTC (`TIMESTAMP`).
   - **Fecha hotelera:** Representa la noche física de alojamiento en la ubicación geográfica de la propiedad (`DATE` local). Una noche no es un timestamp y no se convierte a UTC.
   - **Horario operacional:** Las horas de check-in y check-out son acuerdos operativos administrativos que regulan recepción, entrega de llaves y rotación de limpieza. Serán gobernadas por parámetros configurables del PMS (`operacion.hora_checkin_predeterminada` y `operacion.hora_checkout_predeterminada`), cuyos valores iniciales deberán definirse explícitamente como decisión operativa antes de utilizarlos en producción (sin fijar valores arbitrarios en esta fase). No alteran qué noches están ocupadas.
2. **Identificadores IANA:** Todo identificador de huso horario es una cadena canónica IANA (ej. `America/Lima`). Se prohíben offsets fijos (`UTC-5`).
3. **Zona horaria por propiedad:** Cada propiedad física podrá declarar su propio identificador IANA (`propiedades.zona_horaria`); si es nulo, hereda la zona horaria central predeterminada del PMS (`operacion.zona_horaria_predeterminada`).
4. **Intervalo semiabierto y cálculo de noches:**
   - La estancia se modela matemáticamente como:
     $$\text{Estancia} = [\text{fecha\_entrada}, \text{fecha\_salida})$$
   - El huésped ocupa las noches desde `fecha_entrada` hasta el día anterior a `fecha_salida`. La noche de `fecha_salida` queda libre para el check-in de una nueva reserva ese mismo día.
   - Duración en noches: $\text{noches} = \text{fecha\_salida} - \text{fecha\_entrada}$.
   - Regla del motor ordinario: $\text{noches} \ge 1$ ($\text{fecha\_salida} > \text{fecha\_entrada}$). Estancias de 0 noches o fechas invertidas son inválidas.

### Estrategia de Concurrencia e Inventario Diario (D-067 / P-006)

1. **Modelo Híbrido:** Desacopla la entidad comercial/administrativa (`reservas`, `bloqueos`) del inventario diario físico (`inventario_diario_unidades`), coordinados mediante transacciones ACID.
2. **Inventario Diario Sparse:** La tabla de inventario diario contiene únicamente registros para noches efectivamente ocupadas o bloqueadas; no pregenera calendarios vacíos. La disponibilidad se define formalmente como la **ausencia de fila** para la tupla `(unidad_id, fecha)` en el intervalo $[\text{fecha\_entrada}, \text{fecha\_salida})$.
3. **Restricción UNIQUE como Última Línea Defensiva:**
   - La base de datos impone `UNIQUE(unidad_id, fecha)` en el inventario diario.
   - No se confía en consultas previas (`SELECT ...`) ni en el frontend para evitar sobreventa: la restricción única en InnoDB previene condiciones de carrera concurrentes a nivel de base de datos.
4. **Atomicidad Multinoche y Rollback Completo:** Las reservas multinoche se procesan dentro de una transacción atómica. Si cualquier noche colisiona, se ejecuta `ROLLBACK` total inmediato (cero reservas parcialmente confirmadas, cero noches huérfanas).
5. **Orden Determinista de Bloqueos:** Las reservas procesan sus noches e inventario ordenadas deterministamente por `ORDER BY unidad_id ASC, fecha ASC`, reduciendo sustancialmente el riesgo de bloqueos mutuos (*deadlocks*) y minimizando patrones de adquisición cruzada de locks (sin asumir que un sistema transaccional complejo quede matemáticamente inmune a deadlocks).
6. **Manejo de Excepciones:** Errores de clave duplicada (`1062`), lock wait timeouts (`1205`) o deadlocks (`1213`) se capturan en el servicio y se traducen a `ConflictoDisponibilidadExcepcion` (HTTP 409 Conflict), nunca HTTP 500.
7. **Liberación Atómica:** La cancelación o expiración de un hold temporal elimina de forma atómica sus noches (`DELETE FROM inventario_diario_unidades WHERE reserva_id = ?`), restableciendo la disponibilidad de inmediato.
8. **Fuente Central de Verdad:** Camargo PMS es la única fuente autoritativa. Canales externos (WordPress, App móvil, OTA, API, Webhooks) consumen el mismo servicio de disponibilidad y transacción.

### Motor de Disponibilidad y Bloqueos Operativos (DISPONIBILIDAD-1 / D-068)

1. **Entidades de Dominio:**
   - `BloqueoUnidad`: Modela el bloqueo administrativo o técnico de una unidad física alojable. Propiedades: `id`, `unidadId`, `fechaInicio`, `fechaFin`, `noches`, `motivo`, `tipo` (`BLOQUEO_MANUAL`, `MANTENIMIENTO`), `estado` (`ACTIVO`, `LIBERADO`), `creadoPorActorId`, `liberadoPorActorId`, `creadoEn`, `liberadoEn`. Métodos de conveniencia: `estaActivo()`, `estaLiberado()`, `esMantenimiento()`, `liberar(actorId)`.
   - `InventarioDiario`: Modela la ocupación atómica de una noche física por unidad en el modelo sparse. Propiedades: `id`, `unidadId`, `fecha`, `tipoBloqueo`, `origenTipo`, `origenId`, `creadoEn`.
2. **Fronteras y Delimitaciones del Dominio:**
   - `UNIDAD ≠ DISPONIBILIDAD ≠ RESERVA ≠ TARIFA`: Las unidades físicas existen independientemente de si están ocupadas o libres. La disponibilidad se consulta proyectando el intervalo semiabierto sobre el inventario sparse.
   - `ACTIVO ≠ DISPONIBLE`: Una unidad inactiva o con propiedad inactiva no es comercializable y rechaza bloqueos. Una unidad activa es comercializable siempre que no existan bloqueos para las noches solicitadas.
   - Preservación de P-005: Las entidades de disponibilidad carecen estrictamente de cualquier noción de tarifas, precios, costos o monedas.

## Contratos y documentos

Las plantillas contractuales son editables y versionadas. Un contrato emitido conserva la versión y los datos con que fue generado. El membrete PNG A4 y sus márgenes son configuración versionable cuando su cambio pueda afectar reproducción histórica.

## Servicios, consumos y tarifas

Agua, electricidad, internet y servicios futuros tienen tarifas con vigencia. Un consumo facturado conserva la tarifa aplicada.

Electricidad considera lectura anterior, lectura actual, consumo, tarifa e importe. Correcciones deben conservar trazabilidad, no reescribir silenciosamente el historial.

Los servicios adicionales tienen catálogo y relación muchos-a-muchos con proveedores. Al contratar uno se congela descripción, cantidad, precio de venta, costo, proveedor y estado. Traslados agregan llegada/salida, origen, destino, fecha, hora, pasajeros y observaciones mediante una entidad especializada, no columnas universales de reserva.

## Finanzas

Cobros, ingresos, egresos, gastos, retiros, pagos, impuestos y conciliaciones deben mantener origen y actor. Un pago confirmado puede originar transacción, movimiento de caja y cambio de reserva; el caso de uso debe ser atómico o explícitamente recuperable.

No existen movimientos financieros huérfanos. Los importes históricos no cambian por modificaciones posteriores de precios o catálogos.

## Inventario y mantenimiento

Activos y amenities pueden asignarse a unidades. Se registran estado, ubicación, movimientos, incidencias y mantenimiento. Una incidencia puede afectar disponibilidad mediante una regla explícita.

## Configuración

Datos de empresa, logo, membrete, márgenes PDF, plantillas, tarifas, servicios, precios y parámetros viven en configuración administrada, no dispersos en constantes. Los valores con efecto histórico usan versión o vigencia.

## Integraciones

Camargo PMS es la fuente central. WordPress y futuras aplicaciones consultan y ordenan operaciones mediante API. Los adaptadores de pago implementan una interfaz común y separan sandbox de producción. Los webhooks son autenticados e idempotentes.

## Pendientes de modelado

- Jerarquía física avanzada de niveles/alas independientes (la relación base 1:N Propiedad -> Unidad física alojable quedó establecida en UNIDADES-1).
- Identificadores fiscales y reglas específicas por país.
- Catálogos definitivos de estados y transiciones.
- Contabilidad, impuestos y conciliación requeridos legalmente (P-005, PENDIENTE antes de tarifas/caja).
- Retención y anonimización de datos personales (P-009).
