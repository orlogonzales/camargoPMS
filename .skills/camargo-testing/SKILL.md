---
name: camargo-testing
description: Define y ejecuta pruebas y gates de cierre para incrementos de Camargo PMS, desde reglas unitarias hasta integración, HTTP, interfaz y validación visual.
---

# Camargo Testing

Leer `../../AGENTS.md` y `../../docs/gobernanza/PRUEBAS.md`. Consultar seguridad, datos o frontend según el cambio.

## Selección de pruebas

- Servicio o regla: unitarias con límites y transiciones inválidas.
- Repositorio/migración: integración con BD aislada.
- Ruta o API: método, esquema, permisos, CSRF, errores y contrato.
- UI: interacción, accesibilidad básica, responsive, red y consola.
- Pago/webhook: firma, duplicado, reintento, orden y atomicidad.
- Disponibilidad: frontera temporal y concurrencia.

## Ejecución

Registrar comando o procedimiento, entorno y resultado real. Una prueba no ejecutada se marca como no ejecutada con su riesgo; nunca como aprobada.

## Gate

No recomendar baseline con fallos, errores de consola conocidos, migración no verificada, comportamiento crítico sin prueba o diff fuera de alcance. Revisar también que la documentación describa el comportamiento entregado.
