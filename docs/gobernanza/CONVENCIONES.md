# Convenciones

## Idioma

Todo identificador propio se escribe en español. Se conservan keywords de PHP/JavaScript, APIs de librerías, protocolos y nombres externos. No se mezclan sufijos propios en inglés.

## PHP

- Namespace y directorio en PascalCase: `Aplicacion\Servicios`.
- Clase: `ReservaControlador`, `ReservaServicio`, `ReservaRepositorio`.
- Interfaz: `ProveedorPagoInterfaz`.
- Excepción: `DisponibilidadNoPermitidaExcepcion`.
- Métodos y variables en camelCase: `crearReserva()`, `$fechaIngreso`.
- Constantes en MAYUSCULAS_CON_GUION_BAJO.
- Un archivo por clase y nombre de archivo igual a la clase.
- `declare(strict_types=1);` en código propio.
- Tipos de parámetros, retornos y propiedades siempre que PHP lo permita.
- PHPDoc en clases públicas, contratos y métodos relevantes; debe explicar propósito, parámetros, retorno, excepciones, efectos secundarios e invariantes no evidentes.

## JavaScript

- Archivos en minúsculas y kebab-case cuando tengan varias palabras: `camargo-layout.js`, `detalle-reserva.js`, `camargo-pickers.js`.
- Funciones y variables en camelCase español: `cargarDisponibilidad()`.
- Constantes en MAYUSCULAS_CON_GUION_BAJO.
- Módulos propios separados de vendor y de los originales Alina.
- JSDoc en módulos, funciones exportadas y lógica relevante.
- Cero jQuery en código propio: uso exclusivo de Vanilla JS moderno (ES6+).
- No insertar HTML sin escapar datos externos ni usar `innerHTML` con contenido no confiable.

## Interfaz y componentes (D-071)

- **Iconografía:** Font Awesome 6 Free (v6.3.0) es la única librería autorizada para código propio (`fa-solid fa-*`, `fa-regular fa-*`). Prohibido el uso de Tabler Icons (`ti ti-*`) u otras librerías externas.
- **Selectores de fecha:** Flatpickr Alina local es obligatorio para inputs de fecha (`.camargo-datepicker`) y rangos (`.camargo-rangepicker`).
- **Contrato D-066 en formularios:** Los Range Pickers actúan exclusivamente como capa de experiencia de usuario, sincronizando automáticamente campos canónicos `fecha_entrada` y `fecha_salida` como cadenas ISO `YYYY-MM-DD` con semántica de intervalo semiabierto $[ \text{entrada}, \text{salida} )$.

## Vistas y rutas

- Carpetas de vistas en minúsculas: `plantillas`, `componentes`, `panel`, `reservas`.
- Archivos de vista en kebab-case: `migas-pan.php`, `formulario-reserva.php`.
- Rutas web y API en minúsculas y kebab-case: `/reservas/nueva`, `/api/v1/arrendamientos`.
- Nombres de ruta internos en español y separados por punto: `reservas.crear`.
- Los controladores entregan a la vista datos preparados; la vista no consulta servicios ni repositorios.

## Base de datos

- Tablas en plural y snake_case español: `reservas`, `tarifas_servicios`.
- Columnas en snake_case: `fecha_ingreso`, `importe_total`.
- Clave primaria: `id`; clave foránea: `<entidad_singular>_id`.
- Fechas de control: `creado_en`, `actualizado_en` y, solo cuando aplique, `eliminado_en`.
- Índices y restricciones con nombres expresivos: `uq_unidades_codigo`, `fk_reservas_unidad`.

## API y JSON

- Recursos plurales en español bajo `/api/v1`.
- Campos JSON en snake_case para coincidir con el contrato persistente, salvo adaptadores externos.
- Estados y códigos internos son estables; las etiquetas visibles pueden cambiar o traducirse.
- Fechas y horas se intercambian en ISO 8601 con zona explícita.

## Comentarios y documentación

Explicar decisiones, invariantes y motivos. Evitar comentarios que repitan la línea siguiente. Los ejemplos no deben contener credenciales ni datos personales reales.
