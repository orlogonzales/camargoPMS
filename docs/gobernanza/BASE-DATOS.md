# Base de datos

## Enfoque

MySQL/MariaDB será la persistencia relacional mediante PDO. No se define todavía el esquema físico. El modelo debe favorecer integridad, trazabilidad e históricos frente a atajos de interfaz.

## Reglas

- Claves primarias estables y claves foráneas explícitas.
- Restricciones `NOT NULL`, `UNIQUE`, `CHECK` y FK cuando expresen una invariante real y sean compatibles con la versión seleccionada.
- Índices derivados de consultas justificadas; evitar índices especulativos.
- Importes en `DECIMAL`, nunca `FLOAT`.
- Fechas y horas almacenadas con una política única; intercambio ISO 8601 con zona explícita.
- Estados mediante catálogos o códigos estables con transiciones controladas; no texto libre.
- No usar campos JSON para evitar relaciones que deben ser consultables o validadas.

## Históricos

Las operaciones emitidas congelan los valores necesarios para reproducirlas: tarifa, descripción, precio, costo, proveedor, versión contractual, impuestos y datos relevantes. Cambiar el catálogo afecta operaciones futuras, no documentos pasados.

La configuración y tarifas sensibles a vigencia usan `vigente_desde`, `vigente_hasta` o versión equivalente. No sobrescribir una fila histórica si altera el significado de registros ya emitidos.

## Disponibilidad y concurrencia

Confirmar reserva, estancia, arrendamiento o bloqueo es una operación crítica. El servicio debe:

1. abrir transacción;
2. adquirir la protección de concurrencia definida;
3. volver a comprobar solapamientos;
4. persistir todos los cambios relacionados;
5. confirmar o revertir por completo.

La estrategia exacta —bloqueo pesimista, tabla de inventario temporal u otra— se decidirá con pruebas de concurrencia. Una comprobación previa en interfaz nunca es suficiente.

## Migraciones

- Toda modificación de esquema entra por una migración versionada y revisada.
- Una migración tiene propósito único, precondiciones y reversión o plan de recuperación.
- No editar una migración aplicada en entornos compartidos; crear una nueva.
- Cambios destructivos requieren autorización, respaldo verificado y plan de restauración.
- Semillas contienen catálogos mínimos, nunca datos productivos o credenciales.
- Probar migración desde cero y actualización desde la baseline soportada.

## PDO y repositorios

- Excepciones habilitadas y charset explícito.
- Parámetros enlazados con tipo correcto.
- Consultas en repositorios, no en vistas ni controladores.
- Paginación y límites obligatorios para colecciones potencialmente grandes.
- Evitar `SELECT *` en contratos estables y prevenir consultas N+1.

## Respaldo

Antes de la primera fase con datos reales se debe aprobar política de respaldo, cifrado, retención, pruebas de restauración y recuperación ante fallos. Tener un archivo de respaldo sin prueba de restauración no satisface este requisito.
