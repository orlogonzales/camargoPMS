# Operación y Despliegue del Scheduler iCalendar
## Guía de Automatización Periódica Portable (AIRBNB-ICAL-1D2)

---

## 1. Propósito y Arquitectura

El programador de sincronización de Camargo PMS permite automatizar la importación periódica de calendarios externos (Airbnb, Booking y otros canales iCal) de manera desasistida, garantizando alta portabilidad entre sistemas operativos, ausencia de procesos residentes o daemons, y total protección contra condiciones de carrera.

### Arquitectura de Ejecución

```text
Programador del SO (Cron / Task Scheduler cada 5 min)
        ↓
Control de Instancia Única del Scheduler (flock en Linux / IgnoreNew en Windows)
        ↓
PHP CLI: bin/sincronizar-ical.php --solo-debidas --quiet
        ↓ [Autoridad Temporal]
ConexionIcalRepositorio::listarDebidasParaSondeo()
        ↓ (Itera conexiones con frecuencia_minutos vencida)
SincronizacionIcalServicio::sincronizarConexion()
        ↓ [Autoridad de Concurrencia]
GET_LOCK("camargo_ical_sync_{id}", 0) en MySQL
        ↓ [Autoridad Soberana de Estado]
Motor Soberano iCalendar (Descarga, Reconciliación, Inventario Diario)
        ↓
RELEASE_LOCK("camargo_ical_sync_{id}") en bloque finally
```

### Principio de No-Daemon

Camargo PMS adopta la arquitectura canónica de ejecución episódica mediante el programador del sistema operativo:
- **NO** se emplean daemons PHP en bucle infinito (`while (true)`).
- **NO** se requieren workers residentes ni supervisores de procesos (Supervisor, Systemd services de ejecución continua).
- **NO** se introducen colas externas ni Redis.
- **NO** se utiliza polling desde navegador ni endpoints HTTP simulando ser cronjobs.
- El proceso PHP despierta, ejecuta la reconciliación de las conexiones debidas, libera recursos y concluye devolviendo un código de salida tipado.

---

## 2. Jerarquía de Autoridades Vinculantes

Para preservar la integridad del inventario y evitar discrepancias de sincronización, el sistema establece una clara división de responsabilidades:

| Componente | Rol y Autoridad | Responsabilidad Estricta |
|---|---|---|
| **Cron / Task Scheduler** | **Disparador del SO** | Despierta periódicamente el script CLI (recomendado cada 5 minutos). No toma decisiones de negocio ni conoce el estado de las conexiones. |
| **`--solo-debidas`** | **Autoridad Temporal** | Consulta la BD y selecciona únicamente aquellas conexiones cuyo tiempo transcurrido desde la última sincronización supera su `frecuencia_minutos`. Excluye conexiones pausadas o revocadas. |
| **`GET_LOCK` por Conexión** | **Autoridad de Concurrencia** | Bloqueo distribuido atómico en MySQL a nivel de conexión individual. Garantiza que la UI Alina y el programador no colisionen jamás. |
| **MySQL / Dominio PMS** | **Autoridad Soberana de Estado** | Valida eventos, reconcilia noches ocupadas en `inventario_diario_unidades` bajo transacción ACID y registra auditoría inmutable. |

---

## 3. Prevención de Solapamiento del Scheduler

Si una ejecución del programador demora más del intervalo programado (por ejemplo, ante múltiples conexiones con alta latencia de red en OTAs externas):
1. **Prevención Global a nivel de Scheduler:** Se asegura que el programador del SO no lance una segunda instancia paralela del runner completo.
   - En **Linux:** Se utiliza `flock -n` sobre un descriptor de archivo dedicado.
   - En **Windows:** El Programador de Tareas aplica la política nativa `<MultipleInstancesPolicy>IgnoreNew</MultipleInstancesPolicy>`, y el script PowerShell implementa un `Mutex` global.
2. **Preservación del `GET_LOCK` de Conexión:** Este control global no sustituye ni interfiere con `GET_LOCK("camargo_ical_sync_{id}")`. Un usuario en el panel Alina sigue teniendo la capacidad de sincronizar manualmente cualquier conexión que no esté siendo procesada en ese instante exacto.

---

## 4. Portabilidad y Declaración de Entornos

- **Entorno de Producción:** Se declara formalmente como **NO VERIFICADO** a nivel de rutas fijas. Puede residir en distribuciones Linux (Debian, Ubuntu, AlmaLinux, Rocky) o entornos de contenedores. Por tanto, se proveen plantillas parametrizadas con variables de entorno y rutas absolutas documentadas.
- **Entorno de Desarrollo / Local:** Se verifica habitualmente sobre Windows con entorno Laragon, admitiendo ejecución manual y automatización mediante Programador de Tareas.

---

## 5. Despliegue en Linux (Cron)

### Artefactos Disponibles en `bin/`
- [`bin/cron-ical.sh`](file:///d:/laragon/www/app.camargo-pms/bin/cron-ical.sh): Wrapper bash con gestión de `flock`, detección de PHP y redirección a log.
- [`bin/crontab-ical.template`](file:///d:/laragon/www/app.camargo-pms/bin/crontab-ical.template): Plantilla con ejemplos de configuración.

### Procedimiento de Instalación Paso a Paso

1. Dar permisos de ejecución al script runner:
   ```bash
   chmod +x /ruta/al/proyecto/bin/cron-ical.sh
   ```

2. Abrir la tabla crontab del usuario del servidor web (ej. `www-data` o `camargo`, **nunca como `root`**):
   ```bash
   crontab -e -u www-data
   ```

3. Agregar la siguiente línea programada para ejecutar cada 5 minutos:
   ```cron
   */5 * * * * /ruta/al/proyecto/bin/cron-ical.sh >/dev/null 2>&1
   ```

   *Alternativa directa con flock inline (sin wrapper bash):*
   ```cron
   */5 * * * * /usr/bin/flock -n /tmp/camargo_ical_scheduler.lock /usr/bin/php /ruta/al/proyecto/bin/sincronizar-ical.php --solo-debidas --quiet >> /ruta/al/proyecto/storage/logs/ical_scheduler.log 2>&1
   ```

### Verificación y Diagnóstico

- Verificar que la tarea está registrada:
  ```bash
  crontab -l -u www-data
  ```
- Monitorear ejecuciones en el log de cron del sistema:
  ```bash
  grep CRON /var/log/syslog | grep cron-ical
  ```
- Revisar la telemetría de salida del scheduler:
  ```bash
  tail -f /ruta/al/proyecto/storage/logs/ical_scheduler.log
  ```

### Desactivación

Para pausar la sincronización automática en Linux, ejecutar `crontab -e -u www-data` y comentar la línea anteponiendo `#`:
```cron
# */5 * * * * /ruta/al/proyecto/bin/cron-ical.sh >/dev/null 2>&1
```

---

## 6. Despliegue en Windows (Task Scheduler)

### Artefactos Disponibles en `bin/`
- [`bin/task-scheduler-ical.ps1`](file:///d:/laragon/www/app.camargo-pms/bin/task-scheduler-ical.ps1): Wrapper PowerShell con control de Mutex de instancia única.
- [`bin/task-scheduler-ical.xml`](file:///d:/laragon/www/app.camargo-pms/bin/task-scheduler-ical.xml): Plantilla XML para importación directa en el Programador de Tareas.

### Procedimiento de Instalación Paso a Paso

#### Opción 1: Mediante Comando `schtasks` (Automatizado)
1. Abrir PowerShell o CMD con permisos de Administrador.
2. Editar `bin/task-scheduler-ical.xml` sustituyendo `{{RUTA_PHP}}` y `{{RUTA_PROYECTO}}` por sus rutas absolutas (ej. `C:\laragon\bin\php\php-8.3.x\php.exe` y `D:\laragon\www\app.camargo-pms`).
3. Registrar la tarea en el Programador de Tareas de Windows:
   ```cmd
   schtasks /Create /TN "CamargoPMS_IcalScheduler" /XML "D:\laragon\www\app.camargo-pms\bin\task-scheduler-ical.xml"
   ```

#### Opción 2: Mediante PowerShell Directo
```powershell
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-ExecutionPolicy Bypass -File `"D:\laragon\www\app.camargo-pms\bin\task-scheduler-ical.ps1`""
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 5)
$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -TaskName "CamargoPMS_IcalScheduler" -Action $action -Trigger $trigger -Settings $settings -Description "Sincronizador Automático iCalendar Camargo PMS"
```

### Verificación y Diagnóstico

- Consultar estado de la tarea programada:
  ```powershell
  Get-ScheduledTask -TaskName "CamargoPMS_IcalScheduler"
  ```
- Ejecutar manualmente una corrida de prueba:
  ```powershell
  Start-ScheduledTask -TaskName "CamargoPMS_IcalScheduler"
  ```
- Revisar log generado:
  ```powershell
  Get-Content -Path "D:\laragon\www\app.camargo-pms\storage\logs\ical_scheduler.log" -Tail 20
  ```

### Desactivación

Para desactivar temporalmente la tarea:
```powershell
Disable-ScheduledTask -TaskName "CamargoPMS_IcalScheduler"
```
Para eliminarla completamente:
```powershell
Unregister-ScheduledTask -TaskName "CamargoPMS_IcalScheduler" -Confirm:$false
```

---

## 7. Códigos de Salida y Aislamiento de Fallos

El script CLI [`bin/sincronizar-ical.php`](file:///d:/laragon/www/app.camargo-pms/bin/sincronizar-ical.php) emite estrictamente tres códigos de retorno:

| Código | Significado | Comportamiento del Programador |
|:---:|---|---|
| `0` | **Éxito total** | Todas las conexiones debidas se sincronizaron con éxito, o no había ninguna conexión con periodicidad vencida en este ciclo. |
| `1` | **Error fatal** | Falla de conexión a base de datos, archivo `.env` inaccesible, dependencias dañadas o argumentos inválidos. Requiere atención inmediata del administrador. |
| `2` | **Fallo técnico parcial** | Una o más conexiones sufrieron errores de red, timeout externo o formato iCal corrupto en la OTA externa. El lote continuó procesando las demás conexiones con aislamiento total. No requiere detener el scheduler; el siguiente ciclo reintentará la conexión según su frecuencia. |

---

## 8. Seguridad y Protección de Secretos

1. **Sin Exposición de Secretos en Línea de Comandos:** El runner CLI no recibe URLs de importación, tokens de exportación ni claves de cifrado como argumentos de proceso. Los procesos visibles en `ps aux` en Linux o `Get-Process` en Windows muestran únicamente:
   ```text
   php bin/sincronizar-ical.php --solo-debidas --quiet
   ```
2. **Cero Salida en Modo Silencioso (`--quiet`):** Cuando no hay errores, el proceso no emite ninguna salida a STDOUT ni STDERR, evitando saturar los buzones de correo de cron o los registros del sistema.
3. **Cero Reintentos Inmediatos Agresivos:** Si una OTA externa responde con HTTP 500 o timeout, Camargo PMS registra el fallo en `sincronizaciones_ical_log` y pasa a la siguiente conexión sin martillar el servidor externo. La recuperación ocurre en el próximo intervalo programado del scheduler.
4. **Independencia de Sesión Web:** La ejecución CLI se realiza con el usuario del sistema operativo; no genera sesiones web, no consume cookies ni requiere tokens CSRF.
