<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'Libro de Reclamaciones Virtual — Camargo Hostelería') ?></title>
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
        .libro-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            margin-top: -2rem;
            margin-bottom: 3rem;
            overflow: hidden;
        }
        .section-header {
            background-color: #f7fafc;
            border-bottom: 2px solid #edf2f7;
            padding: 0.85rem 1.5rem;
            font-weight: 700;
            font-size: 0.95rem;
            color: #2b6cb0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
        }
        .section-header i {
            margin-right: 0.6rem;
            font-size: 1.1rem;
        }
        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #4a5568;
            margin-bottom: 0.35rem;
        }
        .required::after {
            content: " *";
            color: #e53e3e;
        }
        .tipo-radio-card {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            height: 100%;
        }
        .tipo-radio-card:hover {
            border-color: #3182ce;
            background-color: #ebf8ff;
        }
        .tipo-radio-card input[type="radio"]:checked + label {
            color: #2b6cb0;
        }
        .tipo-radio-card.active {
            border-color: #3182ce;
            background-color: #ebf8ff;
        }
        .aviso-legal-box {
            background-color: #feebc8;
            border: 1px solid #fbd38d;
            border-radius: 8px;
            padding: 1rem;
            color: #744210;
            font-size: 0.82rem;
            line-height: 1.45;
        }
        .hp-field {
            display: none !important;
            visibility: hidden !important;
        }
    </style>
</head>
<body>

    <!-- Encabezado Institucional -->
    <header class="libro-header text-center">
        <div class="container">
            <h1 class="h2 fw-bold mb-1"><i class="fa-solid fa-book-open me-2"></i> Libro de Reclamaciones Virtual</h1>
            <p class="mb-0 opacity-75">Conforme a la Ley N° 29571, Ley N° 31435, Ley N° 32495 y D.S. N° 011-2011-PCM</p>
        </div>
    </header>

    <main class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="libro-card">
                    <form id="form-libro-reclamaciones" method="POST" action="<?= url_ruta('/libro-reclamaciones') ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                        <!-- Honeypot anti-bots -->
                        <div class="hp-field">
                            <label for="empresa_sitio_web_hp">Sitio web corporativo</label>
                            <input type="text" name="empresa_sitio_web_hp" id="empresa_sitio_web_hp" tabindex="-1" autocomplete="off">
                        </div>

                        <!-- 1. Establecimiento o Sede -->
                        <div class="section-header">
                            <i class="fa-solid fa-hotel"></i> 1. Identificación del Establecimiento o Sede
                        </div>
                        <div class="p-4 border-bottom">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="propiedad_id" class="form-label required">Establecimiento / Sede del Servicio u Hospedaje</label>
                                    <select class="form-select" id="propiedad_id" name="propiedad_id" required>
                                        <option value="" selected disabled>-- Seleccione la sede donde ocurrieron los hechos --</option>
                                        <?php foreach ($propiedades as $p): ?>
                                            <option value="<?= htmlspecialchars((string) $p->obtenerId()) ?>">
                                                <?= htmlspecialchars($p->obtenerNombre()) ?> (<?= htmlspecialchars($p->obtenerProvincia() ?? $p->obtenerDepartamento() ?? 'Perú') ?> - <?= htmlspecialchars($p->obtenerDireccion() ?? '') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Si adquirió el servicio por la web o plataforma virtual, seleccione la sede receptora de su reserva o la principal.</div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Identificación del Consumidor Reclamante -->
                        <div class="section-header">
                            <i class="fa-solid fa-user-check"></i> 2. Identificación del Consumidor Reclamante
                        </div>
                        <div class="p-4 border-bottom">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="consumidor_tipo_documento" class="form-label required">Tipo de Documento</label>
                                    <select class="form-select" id="consumidor_tipo_documento" name="consumidor_tipo_documento" required>
                                        <?php foreach ($tiposDoc as $td): ?>
                                            <option value="<?= htmlspecialchars($td->obtenerCodigo()) ?>" <?= $td->obtenerCodigo() === 'DNI' ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($td->obtenerNombre()) ?> (<?= htmlspecialchars($td->obtenerCodigo()) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="consumidor_numero_documento" class="form-label required">Número de Documento</label>
                                    <input type="text" class="form-control" id="consumidor_numero_documento" name="consumidor_numero_documento" placeholder="Ej. 70123456" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="consumidor_telefono" class="form-label required">Teléfono / Celular</label>
                                    <input type="tel" class="form-control" id="consumidor_telefono" name="consumidor_telefono" placeholder="Ej. 987654321" required>
                                </div>

                                <div class="col-md-6">
                                    <label for="consumidor_nombres" class="form-label required">Nombres o Razón Social</label>
                                    <input type="text" class="form-control" id="consumidor_nombres" name="consumidor_nombres" placeholder="Nombres completos" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="consumidor_apellidos" class="form-label required">Apellidos Completos</label>
                                    <input type="text" class="form-control" id="consumidor_apellidos" name="consumidor_apellidos" placeholder="Apellido paterno y materno" required>
                                </div>

                                <div class="col-md-6">
                                    <label for="consumidor_email" class="form-label required">Correo Electrónico (Notificación Oficial)</label>
                                    <input type="email" class="form-control" id="consumidor_email" name="consumidor_email" placeholder="ejemplo@correo.com" required>
                                    <div class="form-text">A este correo se remitirá copia de la Hoja de Reclamación y la respuesta legal en plazo máximo de 15 días hábiles.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="consumidor_direccion" class="form-label required">Domicilio</label>
                                    <input type="text" class="form-control" id="consumidor_direccion" name="consumidor_direccion" placeholder="Calle, Nro., Distrito, Ciudad" required>
                                </div>

                                <!-- Checkbox Menor de Edad -->
                                <div class="col-12 mt-3">
                                    <div class="form-check p-2 bg-light rounded border">
                                        <input class="form-check-input ms-1 me-2" type="checkbox" id="es_menor_edad" name="es_menor_edad" value="1">
                                        <label class="form-check-label fw-bold" for="es_menor_edad">
                                            El consumidor es menor de edad (se requiere la identificación del padre, madre o apoderado)
                                        </label>
                                    </div>
                                </div>

                                <!-- Bloque Apoderado Desplegable -->
                                <div id="bloque-apoderado" class="col-12 d-none mt-2">
                                    <div class="card border-warning bg-warning bg-opacity-10 p-3">
                                        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-user-shield me-1"></i> Datos del Padre, Madre o Apoderado</h6>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label for="apoderado_tipo_documento" class="form-label required">Tipo Documento Apoderado</label>
                                                <select class="form-select" id="apoderado_tipo_documento" name="apoderado_tipo_documento">
                                                    <option value="DNI" selected>DNI</option>
                                                    <option value="CE">Carné de Extranjería</option>
                                                    <option value="PASAPORTE">Pasaporte</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="apoderado_numero_documento" class="form-label required">N° Documento Apoderado</label>
                                                <input type="text" class="form-control" id="apoderado_numero_documento" name="apoderado_numero_documento" placeholder="N° Documento">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="apoderado_telefono" class="form-label">Teléfono Apoderado</label>
                                                <input type="tel" class="form-control" id="apoderado_telefono" name="apoderado_telefono" placeholder="Móvil apoderado">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="apoderado_nombres" class="form-label required">Nombres del Apoderado</label>
                                                <input type="text" class="form-control" id="apoderado_nombres" name="apoderado_nombres" placeholder="Nombres">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="apoderado_apellidos" class="form-label">Apellidos del Apoderado</label>
                                                <input type="text" class="form-control" id="apoderado_apellidos" name="apoderado_apellidos" placeholder="Apellidos">
                                            </div>
                                            <div class="col-md-12">
                                                <label for="apoderado_email" class="form-label">Correo Electrónico Apoderado</label>
                                                <input type="email" class="form-control" id="apoderado_email" name="apoderado_email" placeholder="correo@apoderado.com">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Identificación del Bien Contratado -->
                        <div class="section-header">
                            <i class="fa-solid fa-receipt"></i> 3. Identificación del Bien Contratado
                        </div>
                        <div class="p-4 border-bottom">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required d-block">Naturaleza del Bien</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="tipo_bien" id="bien_servicio" value="SERVICIO" checked>
                                            <label class="form-check-label fw-bold" for="bien_servicio"><i class="fa-solid fa-bell-concierge me-1"></i> SERVICIO</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="tipo_bien" id="bien_producto" value="PRODUCTO">
                                            <label class="form-check-label fw-bold" for="bien_producto"><i class="fa-solid fa-box me-1"></i> PRODUCTO</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label for="moneda" class="form-label">Moneda</label>
                                    <select class="form-select" id="moneda" name="moneda">
                                        <option value="PEN" selected>Soles (PEN S/)</option>
                                        <option value="USD">Dólares (USD $)</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="monto_reclamado" class="form-label">Monto Reclamado</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="monto_reclamado" name="monto_reclamado" value="0.00">
                                </div>
                                <div class="col-12">
                                    <label for="descripcion_bien" class="form-label required">Descripción del Producto o Servicio</label>
                                    <textarea class="form-control" id="descripcion_bien" name="descripcion_bien" rows="2" placeholder="Ej. Habitación Matrimonial Reserva #1234, Desayuno buffet o consumo en restaurante" required></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Detalle de la Reclamación y Pedido del Consumidor -->
                        <div class="section-header">
                            <i class="fa-solid fa-file-pen"></i> 4. Detalle de la Reclamación y Pedido del Consumidor
                        </div>
                        <div class="p-4 border-bottom">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label required d-block mb-2">Seleccione el Tipo de Solicitud Legal:</label>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="tipo-radio-card" onclick="document.getElementById('tipo_reclamo').checked = true;">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="tipo" id="tipo_reclamo" value="RECLAMO" checked>
                                                    <label class="form-check-label fw-bold f-s-16 text-primary" for="tipo_reclamo">
                                                        RECLAMO
                                                    </label>
                                                </div>
                                                <p class="small text-muted mb-0 mt-1">
                                                    Disconformidad relacionada directamente a los productos o servicios contratados (ej. falla en servicio de habitación, discrepancia de cobro, incumplimiento de reserva).
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="tipo-radio-card" onclick="document.getElementById('tipo_queja').checked = true;">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="tipo" id="tipo_queja" value="QUEJA">
                                                    <label class="form-check-label fw-bold f-s-16 text-primary" for="tipo_queja">
                                                        QUEJA
                                                    </label>
                                                </div>
                                                <p class="small text-muted mb-0 mt-1">
                                                    Disconformidad NO relacionada directamente a los productos o servicios; malestar o descontento respecto a la atención al público (ej. trato descortés, demoras injustificadas en recepción).
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mt-3">
                                    <label for="detalle_reclamacion" class="form-label required">Detalle de los Hechos Expuestos</label>
                                    <textarea class="form-control" id="detalle_reclamacion" name="detalle_reclamacion" rows="4" placeholder="Describa de forma cronológica, clara y precisa los hechos sucedidos..." required></textarea>
                                </div>

                                <div class="col-12">
                                    <label for="pedido_consumidor" class="form-label required">Pedido Concreto al Proveedor</label>
                                    <textarea class="form-control" id="pedido_consumidor" name="pedido_consumidor" rows="3" placeholder="Indique qué solución específica solicita a Camargo Hostelería (ej. devolución, reprogramación, rectificación)..." required></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- 5. Marco Legal y Enlace Probatorio -->
                        <div class="p-4 bg-light">
                            <div class="aviso-legal-box mb-3">
                                <i class="fa-solid fa-scale-balanced me-1"></i> <strong>Aviso Regulatorio:</strong> Conforme a la Ley N° 31435, el plazo máximo para la atención de este expediente es de <strong>quince (15) días hábiles improrrogables</strong>. La formulación del presente reclamo o queja no impide acudir a otras vías de solución de controversias ni es requisito previo para interponer una denuncia administrativa ante el INDECOPI.
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="declaracion_jurada" required>
                                <label class="form-check-label small" for="declaracion_jurada">
                                    Declaro bajo juramento que los datos ingresados son fidedignos y autorizo a Camargo Hostelería a remitirme comunicaciones y la respuesta formal a mi correo electrónico y domicilio señalados.
                                </label>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="reset" class="btn btn-outline-secondary px-4">Limpiar</button>
                                <button type="submit" class="btn btn-primary px-5 fw-bold" id="btn-enviar-reclamacion">
                                    <i class="fa-solid fa-paper-plane me-2"></i> Interponer Reclamación
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer class="mt-auto py-3 bg-white border-top text-center text-muted small">
        <div class="container">
            Camargo Hostelería &copy; <?= date('Y') ?> &bull; Sistema Oficial de Libro de Reclamaciones
        </div>
    </footer>

    <script src="<?= url_ruta('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var chkMenor = document.getElementById('es_menor_edad');
            var bloqueApoderado = document.getElementById('bloque-apoderado');

            if (chkMenor && bloqueApoderado) {
                chkMenor.addEventListener('change', function() {
                    if (this.checked) {
                        bloqueApoderado.classList.remove('d-none');
                        document.getElementById('apoderado_numero_documento').setAttribute('required', 'required');
                        document.getElementById('apoderado_nombres').setAttribute('required', 'required');
                    } else {
                        bloqueApoderado.classList.add('d-none');
                        document.getElementById('apoderado_numero_documento').removeAttribute('required');
                        document.getElementById('apoderado_nombres').removeAttribute('required');
                    }
                });
            }
        });
    </script>
</body>
</html>
