---
name: camargo-security
description: Revisa o implementa seguridad de Camargo PMS para sesiones, autorización, CSRF, XSS, secretos, archivos, API, pagos, webhooks y auditoría.
---

# Camargo Security

Leer `../../AGENTS.md`, `../../docs/gobernanza/SEGURIDAD.md`, `API.md` y `AUDITORIA.md`.

## Análisis por cambio

Identificar actor, activo protegido, entrada no confiable, límite de confianza, permisos, datos sensibles, abuso posible y evento auditable.

## Controles obligatorios

- Validación servidor y salida escapada.
- Consultas preparadas.
- Autenticación separada de autorización.
- Permiso backend por ruta/caso de uso; el menú no autoriza.
- CSRF para mutaciones con sesión.
- Sesiones y cookies seguras.
- Secretos fuera del repositorio y logs.
- Uploads validados y almacenados fuera del área pública.
- Clientes técnicos revocables y de alcance limitado.
- Webhooks autenticados e idempotentes.

## Revisión

Probar negativas: sin sesión, permiso insuficiente, objeto ajeno, token ausente/repetido, payload alterado, duplicado y entrada maliciosa según el alcance. No afirmar seguridad total; informar amenazas revisadas, controles y riesgos residuales.
