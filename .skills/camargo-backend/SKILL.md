---
name: camargo-backend
description: Implementa o revisa backend PHP de Camargo PMS con MVC propio, Servicios, Repositorios, PDO, validación y manejo seguro de errores.
---

# Camargo Backend

Leer `../../AGENTS.md`, `../../docs/gobernanza/ARQUITECTURA.md`, `CONVENCIONES.md`, `SEGURIDAD.md` y `API.md`.

## Distribución de responsabilidades

- Enrutador: resuelve método y ruta.
- Intermediario: aplica controles transversales.
- Controlador: transforma HTTP en una llamada de caso de uso y respuesta.
- Servicio: reglas, coordinación y transacción.
- Repositorio: persistencia mediante PDO.
- Vista: presentación de datos preparados.

Si un controlador calcula negocio o genera SQL, mover esa responsabilidad antes de cerrar. Si un repositorio decide estados de negocio o construye respuestas HTTP, separar la lógica.

## Implementación

- Usar `strict_types`, tipos y nombres españoles.
- Validar forma de entrada en el borde y reglas en el servicio.
- Traducir excepciones de dominio a respuestas controladas; ocultar fallos técnicos en producción.
- Inyectar dependencias; no abrir conexiones dispersas.
- Definir el límite transaccional en el servicio.
- Producir HTML o JSON consistente sin duplicar reglas.
- Documentar contratos públicos, excepciones y efectos secundarios con PHPDoc.

## Verificación

Probar éxito, validación, autorización, conflicto, fallo transaccional y casos límite relevantes. Consultar el skill `camargo-testing` para cerrar el incremento.
