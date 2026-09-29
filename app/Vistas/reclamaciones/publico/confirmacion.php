<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'Constancia de Registro — Libro de Reclamaciones') ?></title>
    <link rel="stylesheet" href="<?= url_asset('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url_asset('vendor/fontawesome/css/all.css') ?>">
    <style>
        body {
            background-color: #f0f2f5;
            color: #2d3748;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .libro-header {
            background: linear-gradient(135deg, #1a365d 0%, #2b6cb0 100%);
            color: #ffffff;
            padding: 2.5rem 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .constancia-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            margin-top: -2rem;
            margin-bottom: 3rem;
            padding: 2.5rem;
            text-align: center;
        }
        .badge-codigo {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 1px;
            padding: 0.6rem 1.4rem;
            border-radius: 8px;
            background-color: #ebf8ff;
            color: #2b6cb0;
            border: 1px dashed #3182ce;
            display: inline-block;
            margin: 1rem 0;
        }
    </style>
</head>
<body>

    <header class="libro-header text-center">
        <div class="container">
            <h1 class="h2 fw-bold mb-1"><i class="fa-solid fa-circle-check text-success me-2"></i> Reclamación Registrada Formalmente</h1>
            <p class="mb-0 opacity-75">Constancia de recepción electrónica en el Libro de Reclamaciones</p>
        </div>
    </header>

    <main class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="constancia-card">
                    <div class="text-success mb-3">
                        <i class="fa-solid fa-circle-check fa-4x"></i>
                    </div>

                    <h2 class="h3 fw-bold text-dark mb-2">Su <?= htmlspecialchars($reclamacion->obtenerTipo()) ?> ha sido ingresado</h2>
                    <p class="text-muted">
                        Hemos registrado su manifestación en nuestro sistema oficial conforme al D.S. 011-2011-PCM y la Ley N° 31435.
                    </p>

                    <div class="my-4">
                        <span class="text-secondary small d-block">Hoja de Reclamación N°:</span>
                        <div class="badge-codigo text-danger fw-bold fs-4">
                            N° <?= htmlspecialchars($reclamacion->obtenerCodigoHoja()) ?>
                        </div>
                        <div class="small text-muted mt-1">
                            Código de Seguimiento: <strong><?= htmlspecialchars($reclamacion->obtenerCodigoInterno()) ?></strong>
                        </div>
                    </div>

                    <div class="card bg-light border-0 p-3 mb-4 text-start">
                        <div class="row g-2 small">
                            <div class="col-sm-6">
                                <span class="text-muted">Fecha y Hora de Interposición:</span><br>
                                <strong><?= date('d/m/Y H:i', strtotime($reclamacion->obtenerFechaInterposicion())) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted">Plazo Máximo Legal de Atención:</span><br>
                                <strong class="text-danger">Hasta el <?= date('d/m/Y', strtotime($reclamacion->obtenerFechaLimiteLegal())) ?> (15 d.h.)</strong>
                            </div>
                            <div class="col-12 mt-2 pt-2 border-top">
                                <span class="text-muted">Consumidor:</span> <strong><?= htmlspecialchars((string) ($reclamacion->obtenerSnapshotConsumidor()['nombre_completo'] ?? '')) ?></strong> &bull; Correo: <?= htmlspecialchars((string) ($reclamacion->obtenerSnapshotConsumidor()['email'] ?? '')) ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                        <a href="<?= htmlspecialchars($url_descarga_pdf) ?>" class="btn btn-primary btn-lg px-4" target="_blank">
                            <i class="fa-solid fa-file-pdf me-2"></i> Descargar Copia en PDF
                        </a>
                        <a href="<?= url_ruta('/libro-reclamaciones') ?>" class="btn btn-outline-secondary btn-lg px-4">
                            <i class="fa-solid fa-arrow-left me-2"></i> Registrar otra solicitud
                        </a>
                    </div>

                    <div class="mt-4 pt-3 border-top text-muted small text-start">
                        <i class="fa-solid fa-info-circle me-1"></i> <strong>Importante:</strong> Se ha remitido una constancia digital a su correo electrónico. Camargo Hostelería evaluará los hechos y dará respuesta motivada por escrito en un plazo no mayor a 15 días hábiles improrrogables.
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="mt-auto py-3 bg-white border-top text-center text-muted small">
        <div class="container">
            Camargo Hostelería &copy; <?= date('Y') ?> &bull; Sistema Oficial de Libro de Reclamaciones
        </div>
    </footer>

    <script src="<?= url_asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
