<#
.SYNOPSIS
    Camargo PMS — Wrapper de Automatización iCalendar para Windows (Task Scheduler / PowerShell).
    Fase: AIRBNB-ICAL-1D2

.DESCRIPTION
    Ejecuta periódicamente la sincronización de conexiones iCal debidas mediante PHP CLI,
    garantizando el uso de rutas absolutas, prevención de solapamiento del scheduler con Mutex
    del sistema, soporte para rutas con espacios y propagación estricta de códigos de salida.

.AUTHORITY
    Task Scheduler SO   = Disparador periódico (cada 5 minutos)
    Mutex Global .NET   = Prevención de solapamiento de la tarea en Windows
    --solo-debidas      = Autoridad temporal (evalúa frecuencia_minutos en BD)
    GET_LOCK MySQL      = Autoridad de concurrencia por conexión
    MySQL / Dominio     = Autoridad soberana de estado e inventario
#>

[CmdletBinding()]
param(
    [string]$PhpPath = $env:PHP_BIN,
    [string]$ProjectPath = $null
)

$ErrorActionPreference = "Stop"

# 1. Resolución de Rutas Absolutas (seguro ante espacios)
if (-not $ProjectPath) {
    $ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
    $ProjectPath = (Resolve-Path (Join-Path $ScriptDir "..")).Path
}

$RunnerScript = Join-Path $ProjectPath "bin\sincronizar-ical.php"
$LogDir = Join-Path $ProjectPath "storage\logs"
$LogFile = Join-Path $LogDir "ical_scheduler.log"

if (-not (Test-Path $LogDir)) {
    New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
}

# 2. Resolución del Ejecutable PHP
if (-not $PhpPath) {
    $phpCmd = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($phpCmd) {
        $PhpPath = $phpCmd.Source
    } else {
        # Fallback a ruta típica de Laragon en desarrollo si existe
        $laragonPhp = Get-ChildItem -Path "C:\laragon\bin\php" -Filter "php.exe" -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($laragonPhp) {
            $PhpPath = $laragonPhp.FullName
        } else {
            $msg = "[ERROR] [$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] No se encontró ejecutable de PHP. Especifique -PhpPath o configure la variable PHP_BIN."
            Add-Content -Path $LogFile -Value $msg
            exit 1
        }
    }
}

if (-not (Test-Path $RunnerScript)) {
    $msg = "[ERROR] [$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Script runner no encontrado en '$RunnerScript'."
    Add-Content -Path $LogFile -Value $msg
    exit 1
}

# 3. Prevención de Solapamiento mediante Mutex Global de Windows
$mutexName = "Global\CamargoPMS_IcalScheduler_Mutex"
$createdNew = $false
$mutex = $null

try {
    $mutex = New-Object System.Threading.Mutex($true, $mutexName, [ref]$createdNew)
} catch {
    # Fallback a mutex local si la política de seguridad no permite Global\
    $mutexName = "Local\CamargoPMS_IcalScheduler_Mutex"
    $mutex = New-Object System.Threading.Mutex($true, $mutexName, [ref]$createdNew)
}

if (-not $createdNew) {
    $hasHandle = $false
    try {
        $hasHandle = $mutex.WaitOne(0, $false)
    } catch {
        # Mutex abandonado por proceso previo terminado anormalmente
        $hasHandle = $true
    }

    if (-not $hasHandle) {
        $msg = "[INFO] [$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Tarea scheduler previa aún en ejecución (Mutex activo). Omitiendo ciclo."
        Add-Content -Path $LogFile -Value $msg
        exit 0
    }
}

# 4. Invocación Segura mediante PHP CLI
$exitCode = 0
try {
    # Invocación con rutas entrecomilladas para tolerar espacios
    $process = Start-Process -FilePath $PhpPath -ArgumentList "`"$RunnerScript`"", "--solo-debidas", "--quiet" -NoNewWindow -Wait -PassThru -RedirectStandardOutput $LogFile -RedirectStandardError $LogFile
    $exitCode = $process.ExitCode
} catch {
    $msg = "[ERROR] [$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Excepción al invocar PHP CLI: $_"
    Add-Content -Path $LogFile -Value $msg
    $exitCode = 1
} finally {
    if ($mutex) {
        try {
            $mutex.ReleaseMutex()
            $mutex.Dispose()
        } catch {
            # Ignorar si ya fue liberado
        }
    }
}

exit $exitCode
