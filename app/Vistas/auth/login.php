<?php

declare(strict_types=1);

/**
 * Vista de inicio de sesión de Camargo PMS adaptada de la plantilla Alina (sign_in.html).
 *
 * @var string $titulo Título de la página preparado por el controlador.
 * @var string|null $error Mensaje de error seguro si la autenticación falló.
 * @var string|null $nombreUsuarioPrevio Valor para preservar el nombre de usuario ante fallo.
 * @var string|null $return Ruta interna de retorno después de iniciar sesión.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Camargo PMS — Sistema Central de Gestión Hotelera">
    <link rel="icon" href="<?= url_asset('images/logo/favicon.png') ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?= url_asset('images/logo/favicon.png') ?>" type="image/x-icon">

    <title><?= e($titulo ?? 'Iniciar Sesión — Camargo PMS') ?></title>

    <!-- Tabler icons -->
    <link rel="stylesheet" type="text/css" href="<?= url_asset('vendor/tabler-icons/tabler-icons.css') ?>">

    <!-- Bootstrap css -->
    <link rel="stylesheet" type="text/css" href="<?= url_asset('vendor/bootstrap/bootstrap.min.css') ?>">

    <!-- App css -->
    <link rel="stylesheet" type="text/css" href="<?= url_asset('css/style.css') ?>">

    <!-- Responsive css -->
    <link rel="stylesheet" type="text/css" href="<?= url_asset('css/responsive.css') ?>">

    <!-- Camargo custom css -->
    <link rel="stylesheet" type="text/css" href="<?= url_asset('css/camargo.css') ?>">
</head>
<body>

<div class="sign-bg-wrapper">
    <div class="container main-container">
        <div class="row main-content-box">
            <div class="col-lg-5 form-content-box p-0">
                <div class="form-container">
                    <form class="app-form" action="<?= url_ruta('/login') ?>" method="POST">
                        <?= csrf_campo() ?>

                        <?php if (!empty($return)): ?>
                            <input type="hidden" name="return" value="<?= e($return) ?>">
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-12">
                                <div class="mb-sm-4 mb-3 text-center text-lg-start">
                                    <h2 class="text-blue f-w-600">Camargo <span class="text-primary">PMS</span></h2>
                                    <p class="f-s-16 mt-2 text-secondary">Acceso a la plataforma de gestión hotelera</p>
                                </div>
                            </div>

                            <?php if (!empty($error)): ?>
                                <div class="col-12">
                                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3 py-2 px-3 b-r-12" role="alert">
                                        <i class="ti ti-alert-circle f-s-20 flex-shrink-0"></i>
                                        <span class="f-s-14"><?= e($error) ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <input class="form-control" id="nombre_usuario" name="nombre_usuario"
                                           placeholder="Nombre de usuario" type="text"
                                           value="<?= e($nombreUsuarioPrevio ?? '') ?>" required autofocus autocomplete="username">
                                    <label for="nombre_usuario">Nombre de usuario</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <input class="form-control" id="contrasena" name="contrasena"
                                           placeholder="Contraseña" type="password" required autocomplete="current-password">
                                    <label for="contrasena">Contraseña</label>
                                </div>
                            </div>

                            <div class="col-12 mt-3">
                                <button type="submit" class="btn bg-gradient-primary btn-lg b-r-16 w-100">
                                    Iniciar Sesión
                                </button>
                            </div>

                            <div class="col-12 mt-4 text-center text-secondary f-s-13">
                                &copy; <?= date('Y') ?> Camargo Hostelería. Todos los derechos reservados.
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-7 image-content-box d-none d-lg-block p-0">
                <img alt="Camargo PMS" class="img-fluid bg-img-cls" src="<?= url_asset('images/login/01.jpg') ?>">
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap js -->
<script src="<?= url_asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>

</body>
</html>
