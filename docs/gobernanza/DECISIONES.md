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

## Pendientes de decisión

| ID | Tema | Momento límite |
|---|---|---|
| P-001 | MySQL o MariaDB y versión | Antes de primera migración |
| P-002 | Namespace Composer y librería de routing | Fase de infraestructura |
| P-003 | Framework de pruebas PHP/JS | Fase de infraestructura |
| P-004 | Estrategia de zona horaria y fecha hotelera | Antes de disponibilidad |
| P-005 | Moneda, redondeo e impuestos | Antes de tarifas/caja |
| P-006 | Estrategia de concurrencia para disponibilidad | Antes de reservas |
| P-007 | Librería PDF | Antes de contratos/recibos |
| P-008 | Proveedor inicial de pagos | Antes de integración de pagos |
| P-009 | Retención de datos y auditoría | Antes de producción |
