# Git y micro-baselines

## Estado y remoto oficial

G-0 se completó sin repositorio. G-1 inicializa el repositorio con rama `main` y configura únicamente este remoto oficial:

```text
origin  https://github.com/orlogonzales/camargoPMS.git
```

Un micro-baseline no se declara oficial hasta comprobar SHA, working tree y relación ahead/behind entre `main` y `origin/main`. Nunca se reconcilia historial remoto mediante pull, merge, rebase o push forzado sin revisión y autorización explícitas.

## Política de contenido de G-1

- Se versionan `AGENTS.md`, `docs/gobernanza/`, `.skills/`, `.gitignore` y la distribución original `admin-dashboard/`.
- Alina y su documentación se versionan intactas porque son la referencia visual reproducible del proyecto.
- `admin-dashboard/alina-figma-version.fig` permanece local e intacto, pero se ignora: pesa 22,62 MiB y no es necesario para ejecutar, estudiar o validar la plantilla.
- No se adopta Git LFS en G-1.
- Dependencias instaladas, secretos, runtime, logs, cache, uploads, exports, backups, bases locales, temporales e IDE se ignoran.
- Migraciones futuras, archivos `.env.example`, documentación y código reproducible no se ignoran.

Antes de cada primer push o baseline se consulta nuevamente el remoto. Si contiene historial inesperado, el proceso se detiene sin mezclar ni sobrescribir.

## Trabajo incremental

- Una rama o incremento representa un propósito coherente.
- Prefijos previstos: `fase/`, `funcionalidad/`, `correccion/`, `documentacion/`.
- Commits pequeños, revisables y en modo imperativo, por ejemplo `docs(gobernanza): fija arquitectura por capas`.
- No mezclar formato masivo, refactor y funcionalidad no relacionada.
- No reescribir historia compartida ni forzar push sin instrucción explícita.

## Micro-baseline

Una micro-baseline es un commit estable que permite volver a un estado conocido. Requiere:

- alcance terminado;
- pruebas aplicables superadas;
- diff revisado;
- documentación consistente;
- secretos y artefactos excluidos;
- riesgos y pendientes informados;
- autorización de cierre cuando la fase la exija.

No crear baseline para ocultar trabajo incompleto o pruebas fallidas.

Después del push, verificar que `main` y `origin/main` señalen al mismo SHA con cero commits ahead/behind.

## Cambios existentes

Antes de editar, inspeccionar el working tree. Todo cambio preexistente pertenece al usuario salvo evidencia contraria. Evitar sobrescribirlo, incluirlo accidentalmente en commits o restaurarlo mediante acciones destructivas.
