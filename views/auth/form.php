<?php
$isSetup = ($title ?? '') === 'Crear administrador';
$isLogin = ($title ?? '') === 'Iniciar sesión';
$providerRegistration = ($type ?? '') === 'prestador';
$formAction = $isSetup ? 'setup_admin.php' : ($isLogin ? 'login.php' : 'registro.php?tipo=' . urlencode($type ?? 'cliente'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> | Servicios Técnicos</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <header class="site-header">
        <a class="brand" href="index.php">Servicios<span>Técnicos</span></a>
        <nav aria-label="Navegación principal"><a href="index.php">Volver al catálogo</a></nav>
    </header>

    <main class="auth-main">
        <section class="auth-card">
            <p class="eyebrow"><?= $isSetup ? 'Configuración inicial' : ($isLogin ? 'Bienvenido de nuevo' : 'Creá tu cuenta') ?></p>
            <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>

            <?php if ($isSetup && !empty($complete)): ?>
                <div class="form-success">La cuenta de administrador está lista. Ya iniciaste sesión.</div>
                <a class="primary-link" href="index.php">Ir al catálogo</a>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="form-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <?php if (!$isLogin && !$isSetup): ?>
                    <div class="account-type-links">
                        <a class="<?= !$providerRegistration ? 'selected' : '' ?>" href="registro.php?tipo=cliente">Cliente</a>
                        <a class="<?= $providerRegistration ? 'selected' : '' ?>" href="registro.php?tipo=prestador">Prestador</a>
                    </div>
                <?php endif; ?>

                <form class="auth-form" method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                    <?php if (!$isLogin): ?>
                        <label for="nombre"><?= $providerRegistration ? 'Nombre del prestador o empresa' : 'Nombre completo' ?></label>
                        <input id="nombre" name="nombre" type="text" maxlength="120" autocomplete="name" required value="<?= htmlspecialchars($name ?? '', ENT_QUOTES, 'UTF-8') ?>">

                        <label for="email">Correo electrónico</label>
                        <input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <?php else: ?>
                        <label for="email">Correo electrónico</label>
                        <input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>

                    <label for="password">Contraseña</label>
                    <input id="password" name="password" type="password" minlength="8" autocomplete="<?= $isLogin ? 'current-password' : 'new-password' ?>" required>

                    <?php if (!$isLogin): ?>
                        <label for="password_confirmation">Repetí la contraseña</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
                    <?php endif; ?>

                    <button class="auth-submit" type="submit"><?= $isLogin ? 'Iniciar sesión' : ($isSetup ? 'Crear cuenta de administrador' : 'Crear cuenta') ?></button>
                </form>

                <div class="auth-alternative">
                    <?php if ($isLogin): ?>
                        <span>¿Todavía no tenés cuenta?</span>
                        <a href="registro.php?tipo=cliente">Registrate como cliente</a>
                        <a href="registro.php?tipo=prestador">Registrate como prestador</a>
                    <?php elseif (!$isSetup): ?>
                        <span>¿Ya tenés una cuenta?</span>
                        <a href="login.php">Iniciá sesión</a>
                    <?php else: ?>
                        <span>Esta pantalla solo funciona una vez, desde esta computadora.</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site-footer"><p>Servicios Técnicos</p></footer>
</body>
</html>
