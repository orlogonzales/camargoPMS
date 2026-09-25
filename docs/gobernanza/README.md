# Gobernanza de Camargo PMS

Esta carpeta es la fuente única de verdad documental del proyecto. Describe decisiones aprobadas, límites y procedimientos; no sustituye pruebas ni código ejecutable.

## Mapa de documentos

- `ARQUITECTURA.md`: capas, dependencias y flujo de una petición.
- `STACK-TECNOLOGICO.md`: tecnologías aprobadas y decisiones diferidas.
- `CONVENCIONES.md`: nomenclatura y estilo del código propio.
- `ESTRUCTURA-PROYECTO.md`: estructura prevista y responsabilidad de carpetas.
- `MODELO-DOMINIO.md`: conceptos, relaciones e invariantes iniciales.
- `SEGURIDAD.md`: controles de aplicación, integraciones y datos.
- `BASE-DATOS.md`: reglas relacionales, migraciones e históricos.
- `FRONTEND.md`: interfaz, assets, navegación y JavaScript.
- `PLANTILLA-ALINA.md`: evidencia técnica de la plantilla oficial.
- `API.md`: contrato y principios para API e integraciones.
- `AUDITORIA.md`: modelo de actores y eventos auditables.
- `PRUEBAS.md`: estrategia y puertas de calidad.
- `GIT-Y-BASELINES.md`: flujo Git y micro-baselines.
- `DECISIONES.md`: registro de decisiones y asuntos pendientes.
- `ROADMAP.md`: secuencia de fases y condiciones de avance.
- `MODULOS.md`: alcance y dependencias funcionales.
- `CHANGELOG.md`: cambios relevantes por fase.

## Uso

Todo agente empieza por `AGENTS.md` y después consulta solo los documentos y skills aplicables. Una decisión nueva o modificada debe registrarse en `DECISIONES.md` y reflejarse en el documento especializado. Si código y documentación discrepan, no se corrige silenciosamente: se determina cuál representa la decisión aprobada y se actualizan ambos dentro del mismo incremento.

## Estado

Fase G-1. La gobernanza G-0 constituye el contenido del primer baseline documental. G-1 fija `main`, el remoto oficial, exclusiones y tratamiento del material Alina. No hay implementación funcional ni esquema de base de datos. Las estructuras descritas son vinculantes cuando están marcadas como aprobadas; los puntos pendientes no autorizan una implementación por defecto.
