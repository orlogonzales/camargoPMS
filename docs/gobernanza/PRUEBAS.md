# Pruebas y puertas de calidad

## Capas

- Unitarias: reglas de servicios, cálculos, transiciones y utilidades sin infraestructura real.
- Integración: repositorios, PDO, migraciones, transacciones y adaptadores.
- Funcionales HTTP: rutas, intermediarios, permisos, CSRF, HTML y contratos JSON.
- Frontend: módulos JavaScript, interacción y errores de consola.
- End-to-end: flujos críticos completos cuando la infraestructura exista.
- Visuales: comparación de Alina, responsive y documentos PDF cuando corresponda.

## Casos críticos

Disponibilidad concurrente, idempotencia de pagos/webhooks, movimientos de caja, permisos, históricos de tarifa, contratos versionados y migraciones requieren pruebas positivas, negativas y de repetición.

## Gate por incremento

Antes de cerrar:

1. análisis estático/lint y formato disponibles;
2. pruebas nuevas del comportamiento modificado;
3. suite relacionada sin fallos;
4. rutas y assets sin 404;
5. consola del navegador sin errores para cambios UI;
6. revisión de seguridad cuando aplique;
7. diff sin cambios ajenos;
8. documentación y changelog actualizados.

Una prueba omitida debe informarse con motivo, riesgo y forma de ejecutarla; no se presenta como superada.

## Datos de prueba

Usar datos sintéticos y reproducibles. No copiar datos productivos, credenciales o documentos reales. Las pruebas de BD deben aislarse y poder reiniciarse sin afectar otros entornos.

## UI-0/UI-1

La primera baseline de interfaz debe comprobar escritorio y móvil, loader, navegación acoplada, submenús, activo por ruta, sidebar, Simplebar, tema, breadcrumbs, assets globales/específicos, accesibilidad básica y ausencia de errores de consola.

## Herramientas

El framework concreto de pruebas PHP/JS y las herramientas de navegador siguen pendientes. Se decidirán en infraestructura con compatibilidad PHP 8.3, ejecución local simple y automatización futura como criterios.
