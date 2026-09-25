---
name: camargo-database
description: Diseña, migra o revisa persistencia MySQL/MariaDB de Camargo PMS, incluidos PDO, integridad, históricos, transacciones y concurrencia.
---

# Camargo Database

Leer `../../AGENTS.md`, `../../docs/gobernanza/BASE-DATOS.md`, `MODELO-DOMINIO.md`, `CONVENCIONES.md` y `AUDITORIA.md`.

## Antes de diseñar

Definir caso de uso, invariantes, cardinalidades, ciclo de vida, históricos, volumen esperado y consultas críticas. No convertir el modelo conceptual completo en tablas en una sola fase.

## Reglas

- Tablas y columnas en snake_case español.
- PK, FK, restricciones e índices explícitos.
- `DECIMAL` para dinero y política temporal única.
- Snapshots o versiones para operaciones históricas.
- SQL solo en repositorios y siempre parametrizado.
- Transacción para cambios coordinados; disponibilidad y finanzas requieren pruebas de concurrencia o idempotencia.

## Migraciones

Crear una migración de propósito único. Probar base vacía y actualización desde baseline soportada. No editar migraciones ya compartidas ni ejecutar cambios destructivos sin autorización, respaldo verificado y recuperación ensayada.

## Entrega

Incluir modelo afectado, restricciones, consultas previstas, índices, plan de migración/reversión y pruebas. Declarar las decisiones de motor o versión aún pendientes.
