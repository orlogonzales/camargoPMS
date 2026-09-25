# Registro de decisiones

Las decisiones aprobadas se modifican mediante una nueva entrada o una actualización explícita que conserve el motivo. Los asuntos pendientes no autorizan una elección automática.

## Decisiones aprobadas

### D-001 — PMS como fuente central

Camargo PMS concentra disponibilidad y operaciones. WordPress, apps y futuras OTA consumen API y no mantienen reglas paralelas.

### D-002 — MVC propio por capas

PHP 8.3 con Enrutador, Intermediarios, Controladores, Servicios, Repositorios, PDO y MySQL/MariaDB. Sin framework PHP ni ORM hasta decisión posterior.

### D-003 — Código propio en español

Clases, métodos, variables, carpetas, vistas, rutas y esquema propios usan español. APIs externas y keywords mantienen su nombre.

### D-004 — Alina como interfaz oficial e inmutable

`blank.html` es la fuente visual. Originales, demos, assets, documentación, Figma, SCSS/Webpack y vendors se conservan intactos.

### D-005 — Navegación Alina como contrato único

`navbar-menu-list[data-target]` y `main-side-menu .main-menu[id]` forman una navegación acoplada por clave estable. El backend filtra visibilidad y protege rutas.

### D-006 — JavaScript propio del layout

No se adopta `script.js` ni `theme_customizer.js` como núcleo definitivo. Se creará `camargo-layout.js` con funciones necesarias y componentes opcionales seguros.

### D-007 — Assets mínimos

Bootstrap, Tabler Icons, Simplebar y CSS esencial de Alina son globales; plugins especializados se cargan por módulo.

### D-008 — Vistas pasivas

El layout recibe información preparada. Las vistas no consultan BD ni deciden permisos.

### D-009 — Identidad y actores separados

Una persona puede asumir roles de dominio; cuenta de acceso y cliente técnico son responsabilidades distintas. Auditoría distingue `USER`, `SYSTEM`, `INTEGRATION` y `PAYMENT_PROVIDER`.

### D-010 — Históricos preservados

Contratos, tarifas, servicios vendidos, impuestos y movimientos conservan versiones o snapshots necesarios. Un cambio de catálogo no altera operaciones pasadas.

### D-011 — Desarrollo por fases y micro-baselines

Cada incremento ejecuta precheck, análisis, implementación limitada, pruebas, diff, documentación y baseline autorizada. No se inicia una fase posterior automáticamente.

### D-012 — Gobernanza antes de implementación

G-0 precede a Git, infraestructura PHP y conversión de Alina.

### D-013 — Repositorio y baseline inicial

La rama oficial es `main` y el remoto oficial es `origin` en `https://github.com/orlogonzales/camargoPMS.git`. La distribución original de Alina y su documentación se versionan intactas. El Figma de 22,62 MiB permanece local, se ignora y no se elimina; no se adopta Git LFS en G-1. Cada micro-baseline exige verificar el SHA, working tree y relación entre `main` y `origin/main`.

### D-014 — Autocargador PSR-4 y Enrutador Reversible para UI-0

Se implementan `Autocargador` PSR-4 propio para el espacio de nombres `CamargoPMS\` y un `Enrutador` mínimo en `app/Nucleo/` para soportar la fase de interfaz sin incorporar dependencias externas tempranas. La decisión P-002 (adopción del motor definitivo de enrutamiento y dependencias vía Composer) se mantiene formalmente abierta para la fase de infraestructura.
### D-015 — Asignación de referencias visuales Alina y arquitectura de errores

Se formalizan las páginas de Alina como referencias inmutables oficiales: `blank.html` (layout general autenticado), `sign_in.html` (referencia visual de login sin implementar autenticación funcional en UI-0), `index.html` (referencia visual de dashboard sin datos simulados en UI-0) y las cinco páginas `error_*.html` (400, 403, 404, 500 y 503). Se implementa una plantilla aislada `plantillas/error.php` (`.error-container`) y la vista reutilizable `errores/error.php` que desacopla el diagnóstico técnico interno de la visualización segura del usuario.

### D-016 — Separación de Identidad, Personal, Usuarios, Roles y Permisos

Se formaliza el principio arquitectónico vinculante `PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL`. Un Colaborador es la vinculación laboral de una Persona y mantiene un historial de episodios laborales inmutable; un Cargo describe la función laboral y no confiere privilegios de software; un Usuario es una cuenta de acceso humano con contraseñas seguras y roles dinámicos; y los Permisos son capacidades atómicas `recurso.accion` evaluadas estrictamente en backend. La ocultación de menús es solo cosmética y nunca sustituye la autorización del servidor (devolviendo HTTP 403 real ante accesos no autorizados). Personal no es un subsistema financiero; Caja gestiona sus movimientos referenciando a personas. El menú dinámico preserva el contrato Alina `data-target` ↔ `id`.

### D-017 — Integración Externa con APIsPERU (DNI y RUC) mediante Abstracción

Se aprueba la integración con APIsPERU para la consulta y autocompletado ágil de datos de identidad en Perú mediante dos endpoints desacoplados: `GET /api/v1/dni/{dni}` (8 dígitos) y `GET /api/v1/ruc/{ruc}` (11 dígitos, reutilizable para proveedores y empresas). La integración debe orquestarse mediante una capa de abstracción en el dominio (`ServicioConsultaIdentidad` e interfaces de proveedor) que impida el acoplamiento directo y permita cambiar de proveedor en el futuro. Es vinculante no asumir campos no documentados en contrato y permitir la edición o complemento manual en la interfaz. El token técnico se administra exclusivamente en el entorno del servidor, fuera de Git y del cliente.

### D-018 — Motor de base de datos oficial: MySQL 8.4.3 LTS

Se resuelve formalmente **P-001** con base en la evidencia de la auditoría del entorno local. Se adopta oficialmente **MySQL Community Server 8.4.3 LTS (GPL)** con almacenamiento transaccional `InnoDB`, codificación `utf8mb4`, collation `utf8mb4_0900_ai_ci` y `sql_mode` estricto estándar.

### D-019 — Adopción pragmática de Composer y preservación del router nativo

Se resuelve formalmente **P-002**. Se adopta Composer para la gestión de dependencias mínimas justificadas (`vlucas/phpdotenv` para entorno) y el autoloading PSR-4 asignando el espacio de nombres raíz `CamargoPMS\` al directorio `app/`. Se preserva el enrutador nativo `app/Nucleo/Enrutador.php` tras validar su óptimo funcionamiento en UI-0 y UI-1, evitando el reemplazo innecesario de infraestructura funcional.

### D-020 — Esquema consolidado oficial y migraciones incrementales

Se formaliza el contrato de persistencia: `SQL/camargo_pms.sql` es la representación consolidada oficial y versionada del esquema vigente de Camargo PMS, libre de datos operativos y credenciales. `SQL/migraciones/` contiene la secuencia cronológica de cambios estructurales controlados mediante la tabla técnica `migraciones` y el runner CLI `php migrar.php`. Todo cambio estructural futuro debe reflejarse simultáneamente en una migración y en el SQL consolidado. El campo `lote` (`batch`) representa estrictamente metadatos de trazabilidad y agrupación cronológica, sin asumir reversibilidad automática.

### D-021 — Núcleo de Identidad Humana y separación Persona / Documento / Contacto

Se establece el modelo relacional del maestro humano central separando estrictamente la entidad `personas` de sus colecciones dependientes `personas_documentos` y `personas_contactos`. La tabla `personas` contiene exclusivamente atributos ontológicos del individuo (nombres obligatorios, apellidos paterno/materno con nulabilidad defensiva para extranjeros, fecha de nacimiento nullable sin fechas futuras, país de nacionalidad y dirección básica). Los documentos y medios de contacto se modelan como entidades normalizadas 1:N sin columnas repetibles planas (`telefono_1`, `email_1`, etc.).

### D-022 — Exclusión estricta de RUC como documento personal

Se prohíbe modelar el RUC como un tipo de documento personal equivalente a DNI o Pasaporte en el maestro de personas naturales. APIsPERU provee consultas independientes de DNI y RUC, pero conceptualmente el RUC corresponde a la identidad tributaria/fiscal de personas con negocio o entidades jurídicas (empresas/proveedores). Las personas jurídicas y sus números de RUC serán abordados en su módulo específico sin degradar el maestro de personas naturales.

### D-023 — Catálogos normalizados de Países y Tipos de Documento

Se formalizan los catálogos relacionales `paises` (códigos ISO-3166-1 alpha-2 y alpha-3 únicos, nombre, nacionalidad y estado activo) y `tipos_documento` (código único, nombre, descripción, restricciones de longitud y expresiones regulares de formato). Se definen como datos estructurales iniciales: Perú ('PE', 'PER') como país base disponible, y DNI (8 dígitos numéricos exactos), Pasaporte y Carné de Extranjería (CE) como tipos de documento personales.

### D-024 — Unicidad documental y garantía de principales activos vía columnas virtuales generadas

Se asegura la unicidad documental a nivel de base de datos mediante la restricción `UNIQUE (tipo_documento_id, numero_documento)`, impidiendo que dos personas compartan el mismo documento. Para garantizar la invariante de dominio de "máximo un documento principal activo por persona" y "máximo un contacto principal activo por tipo y persona", se adoptan en MySQL 8.4 columnas virtuales generadas (`uq_persona_principal` y `uq_contacto_tipo_principal`) con índices únicos condicionales sobre valores no nulos, complementados por la coordinación transaccional en `PersonaServicio`. La relación teléfono/WhatsApp se resuelve mediante un indicador booleano `es_whatsapp` en el mismo registro de contacto telefónico, evitando la duplicación ciega de números.

### D-025 — Gestión de estados, soft delete y preservación histórica

Se prohíbe el borrado físico (`DELETE`) en las operaciones ordinarias de personas, documentos y contactos. Se adopta la desactivación lógica mediante el campo `estado` (`'ACTIVO'`, `'INACTIVO'`) para preservar relaciones contractuales, de reservas y auditoría futura. Las claves foráneas hacia personas y catálogos aplican `ON DELETE RESTRICT ON UPDATE CASCADE`. La clave primaria adopta `BIGINT UNSIGNED AUTO_INCREMENT` para personas y colecciones de alto volumen e `INT UNSIGNED` para catálogos.

### D-026 — Principio de Separación Integral: PERSONA ≠ COLABORADOR ≠ EPISODIO LABORAL ≠ CARGO ≠ USUARIO ≠ ROL

Se formaliza la separación conceptual y técnica estricta de las entidades del dominio humano y organizacional:
1. `Persona`: Ser humano ontológico (identidad biométrica/civil).
2. `Colaborador`: Identidad laboral estable dentro de la organización.
3. `EpisodioLaboral`: Período cronológico continuo de vinculación laboral (ingreso hasta cese).
4. `Cargo`: Catálogo administrable de funciones/puestos de trabajo (no otorga permisos de software).
5. `Usuario`: Cuenta de acceso técnico autenticado a la plataforma.
6. `Rol`: Conjunto de permisos de seguridad de software para autorización granular.
Ninguna entidad se mezcla ni sustituye a otra.

### D-027 — Cardinalidad 1:1 lógica Persona-Colaborador con inmutabilidad de identidad interna

Una Persona natural puede tener como máximo un registro en la tabla `colaboradores` (`uq_colaboradores_persona`). La identidad del colaborador se identifica externamente mediante un código secuencial estable (`COL-XXXX`) que permanece inmutable ante ceses y reingresos. Se prohíbe terminantemente crear múltiples filas de colaboradores para la misma persona en recontrataciones o retornos futuros.

### D-028 — Historial laboral inmutable por Episodios y Asignaciones de Cargo con columnas virtuales generadas

El historial laboral no se sobreescribe ni se destruye. Cada período de contratación genera un registro en `episodios_laborales` y cada cambio o ascenso funcional dentro de un episodio genera un registro en `episodios_laborales_cargos`. Para garantizar a nivel de base de datos la invariante de "a lo sumo un episodio abierto activo por colaborador" y "a lo sumo una asignación de cargo vigente por episodio", se implementan columnas virtuales generadas (`uq_colaborador_abierto` y `uq_episodio_cargo_abierto`) con restricciones `UNIQUE` sobre valores no nulos.

### D-029 — Convención temporal de continuidad en transiciones de cargo y prohibición de solapamiento

En un cambio de cargo en fecha $D$, la asignación de cargo anterior se cierra con `fecha_fin = D - 1 día` y la nueva asignación se abre con `fecha_inicio = D` y `fecha_fin = NULL`, garantizando continuidad cronológica estricta sin solapamiento ni días vacíos. Se prohíbe el solapamiento temporal tanto a nivel de episodios laborales como de cargos dentro de un episodio. Toda operación de transición y cese se ejecuta con bloqueos pesimistas (`SELECT ... FOR UPDATE`) dentro de transacciones ACID.

### D-030 — Ajuste evolutivo de identidad: monónimos y unicidad documental internacional inicial

Se adoptó un ajuste evolutivo no destructivo en la migración `003` para dos aspectos de la identidad:
1. Nombres internacionales y monónimos: Se eliminó la restricción que exigía al menos un apellido (`chk_personas_al_menos_un_apellido`), manteniendo `nombres` como obligatorio y permitiendo apellidos nulos para individuos extranjeros o monónimos legales.
2. Unicidad documental internacional: La unicidad documental de `personas_documentos` incorporó inicialmente el país emisor mediante la columna virtual generada `pais_emisor_efectivo = COALESCE(pais_emisor_id, 0)` y la restricción `UNIQUE (tipo_documento_id, pais_emisor_efectivo, numero_documento)`. Esta solución fue superada estructuralmente en la migración `004` (ver D-031).

### D-031 — Jurisdicción documental estructural sin centinelas (Migración 004 / PERSONAL-1A)

Se elimina formalmente el centinela técnico `COALESCE(pais_emisor_id, 0)` y la columna virtual `pais_emisor_efectivo` mediante la migración `004`. La jurisdicción se modela estructuralmente en el catálogo `tipos_documento` mediante dos atributos canónicos:
1. `pais_fijo_id`: Clave foránea nullable hacia `paises(id)`. Si está definido, el tipo de documento posee jurisdicción fija no configurable (ej. DNI y CE fijados a Perú, ID 1). El servicio de dominio asigna automáticamente el país fijo si se omite y rechaza tajantemente cualquier emisión en otro país.
2. `pais_emisor_obligatorio`: Indicador booleano que señala si el tipo de documento requiere obligatoriamente indicar el país emisor (ej. PASAPORTE fijado en 1).

En consecuencia, `personas_documentos.pais_emisor_id` se define estrictamente como `INT UNSIGNED NOT NULL`, respaldado por la clave foránea íntegra `fk_documentos_pais_emisor` e indexado bajo la restricción única natural `UNIQUE (tipo_documento_id, pais_emisor_id, numero_documento)`. Queda prohibido el almacenamiento de jurisdicciones nulas o ficticias en la base de datos.

### D-032 — Invariante de solapamiento temporal de cargos en episodio (PERSONAL-1A)

Se formaliza la verificación estricta de no solapamiento temporal para todas las asignaciones de cargo dentro de un episodio laboral:
1. Ninguna asignación de cargo puede solaparse cronológicamente con otra del mismo episodio, aplicable tanto a intervalos abiertos como a intervalos cerrados (ej. Cargo A: 01/01 a 30/06 y Cargo B: 01/04 a 31/05 se rechaza tajantemente mediante `SolapamientoLaboralExcepcion`).
2. Las asignaciones de cargo están circunscritas a los límites del episodio laboral padre: no pueden iniciar antes de la apertura del episodio ni iniciar/finalizar después de su cese.
3. Se mantiene la convención $D-1$ / $D$ en transiciones de cargo con `cambiarCargo()`.
4. La verificación se centraliza en `ColaboradorServicio::validarSolapamientoAsignacionCargo()` y se protege concurrentemente mediante transacciones y bloqueos pesimistas (`SELECT ... FOR UPDATE`).

## Pendientes de decisión

| ID | Tema | Momento límite |
|---|---|---|
| P-003 | Framework de pruebas PHP/JS | Antes de pruebas automatizadas de dominio |
| P-004 | Estrategia de zona horaria y fecha hotelera | Antes de disponibilidad |
| P-005 | Moneda, redondeo e impuestos | Antes de tarifas/caja |
| P-006 | Estrategia de concurrencia para disponibilidad | Antes de reservas |
| P-007 | Librería PDF | Antes de contratos/recibos |
| P-008 | Proveedor inicial de pagos | Antes de integración de pagos |
| P-009 | Retención de datos y auditoría | Antes de producción |
