---
name: camargo-core
description: Coordina fases, arquitectura y gobernanza de Camargo PMS. Usar al iniciar o cerrar cualquier incremento del proyecto o al tomar decisiones que afecten varias capas.
---

# Camargo Core

## Preparación

Leer `../../AGENTS.md` y `../../docs/gobernanza/README.md`. Consultar `DECISIONES.md`, `ROADMAP.md` y los documentos del alcance antes de actuar.

## Procedimiento

1. Confirmar fase, objetivo, exclusiones y criterio de aceptación.
2. Inspeccionar estructura, estado Git, cambios preexistentes, dependencias y pruebas.
3. Identificar decisiones aprobadas y pendientes; no convertir un pendiente en supuesto silencioso.
4. Limitar el incremento a un resultado coherente y reversible.
5. Implementar respetando las capas y usando el skill especializado aplicable.
6. Ejecutar pruebas proporcionales al riesgo y revisar el diff completo.
7. Actualizar documentación, decisiones y changelog afectados.
8. Informar resultados y detenerse en el límite de fase. Crear baseline solo cuando esté autorizada.

## Invariantes

- Camargo PMS es la fuente central de verdad.
- Código propio en español.
- Sin framework, ORM o dependencia estructural nueva sin decisión registrada.
- No mezclar refactorizaciones ni módulos ajenos.
- No sobrescribir cambios del usuario.
- Una fase con pruebas fallidas o riesgos ocultos no está cerrada.
