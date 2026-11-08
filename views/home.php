<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicios Técnicos</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="site-header site-header-home">
        <div class="site-bar">
            <a class="brand" href="index.php">Servicios<span>Técnicos</span></a>
            <nav aria-label="Navegación principal">
            <a href="#servicios">Servicios</a>
            <?php if ($currentUser !== null): ?>
                <span class="nav-greeting">Hola, <?= htmlspecialchars($currentUser['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (($currentUser['rol'] ?? '') === 'Adm'): ?>
                    <a href="admin.php">Administración</a>
                <?php elseif (($currentUser['rol'] ?? '') === 'prestador'): ?>
                    <a href="provider.php">Mis servicios</a>
                    <a href="reservas.php">Reservas</a>
                <?php elseif (($currentUser['rol'] ?? '') === 'User'): ?>
                    <a href="reservas.php">Mis reservas</a>
                <?php endif; ?>
                <a class="nav-button" href="logout.php">Cerrar sesión</a>
            <?php else: ?>
                <a href="login.php">Iniciar sesión</a>
                <a class="nav-button" href="registro.php?tipo=cliente">Crear cuenta</a>
            <?php endif; ?>
            </nav>
        </div>
        <section class="presentation-carousel" aria-label="Publicidad de prestadores">
            <?php if ($presentations === []): ?>
                <div class="presentation-empty">
                    <p class="eyebrow">Prestadores</p>
                    <h2>Encontrá profesionales para lo que necesitás.</h2>
                </div>
            <?php else: ?>
                <div class="presentation-marquee">
                    <div class="presentation-track<?= count($presentations) > 1 ? ' is-moving' : '' ?>" style="--presentation-duration: <?= count($presentations) * 32 ?>s">
                        <div class="presentation-group">
                            <?php $copies = count($presentations) > 1 ? 4 : 1; ?>
                            <?php for ($copy = 0; $copy < $copies; $copy++): ?>
                                <?php foreach ($presentations as $presentation): ?>
                                    <?php $providerUrl = 'index.php?' . http_build_query(['prestador_id' => (int) $presentation['prestador_id']]) . '#servicios'; ?>
                                    <div class="presentation-image-card">
                                        <a class="presentation-image-link" href="<?= htmlspecialchars($providerUrl, ENT_QUOTES, 'UTF-8') ?>">
                                            <img src="<?= htmlspecialchars($presentation['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="Ver los servicios de <?= htmlspecialchars($presentation['nombre'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endfor; ?>
                        </div>
                        <?php if (count($presentations) > 1): ?>
                            <div class="presentation-group" aria-hidden="true">
                                <?php for ($copy = 0; $copy < $copies; $copy++): ?>
                                    <?php foreach ($presentations as $presentation): ?>
                                        <?php $providerUrl = 'index.php?' . http_build_query(['prestador_id' => (int) $presentation['prestador_id']]) . '#servicios'; ?>
                                        <div class="presentation-image-card">
                                            <a class="presentation-image-link" href="<?= htmlspecialchars($providerUrl, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1">
                                                <img src="<?= htmlspecialchars($presentation['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </header>

    <main>
        <section class="hero">
            <div class="hero-copy">
                <p class="eyebrow">Soluciones para tu hogar y negocio</p>
                <h1>Encontrá el servicio técnico que necesitás.</h1>
                <p class="hero-text">Explorá servicios y conocé a los prestadores disponibles.</p>
                <form class="search-form" action="index.php" method="get" role="search">
                    <label class="visually-hidden" for="q">Buscar servicios, rubros o prestadores</label>
                    <input id="q" name="q" type="search" placeholder="¿Qué servicio estás buscando?" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit">Buscar</button>
                </form>
            </div>
        </section>

        <section class="catalog" id="servicios">
            <?php if (!empty($needsAdminSetup) && $currentUser === null): ?>
                <p class="setup-notice">¿Primera vez aquí? <a href="setup_admin.php">Configurar cuenta de administrador</a></p>
            <?php endif; ?>
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Catálogo</p>
                    <h2><?= $search !== '' ? 'Resultados de búsqueda' : 'Servicios publicados' ?></h2>
                </div>
                <?php if ($search !== ''): ?>
                    <a class="clear-search" href="index.php">Limpiar búsqueda</a>
                <?php endif; ?>
            </div>

            <?php if ($databaseError): ?>
                <div class="notice notice-error">No se pudo cargar el catálogo. Revisá la conexión con la base de datos.</div>
            <?php elseif ($services === []): ?>
                <div class="empty-state">
                    <div class="empty-icon" aria-hidden="true">⌕</div>
                    <h3><?= $search !== '' ? 'No encontramos coincidencias' : 'Todavía no hay servicios publicados' ?></h3>
                    <p><?= $search !== '' ? 'Probá buscar con otro término.' : 'Cuando se publiquen servicios, vas a poder encontrarlos en este catálogo.' ?></p>
                </div>
            <?php else: ?>
                <div class="service-grid">
                    <?php foreach ($services as $service): ?>
                        <article class="service-card">
                            <p class="category-tag"><?= htmlspecialchars($service['categoria'], ENT_QUOTES, 'UTF-8') ?></p>
                            <h3><?= htmlspecialchars($service['servicio'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p class="provider-name">Prestador: <?= htmlspecialchars($service['prestador'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="provider-rating" aria-label="Calificación promedio">
                                <span><?= number_format((float) $service['promedio_calificacion'], 1, ',', '.') ?> ★</span>
                                (<?= (int) $service['cantidad_calificaciones'] ?> <?= (int) $service['cantidad_calificaciones'] === 1 ? 'opinión' : 'opiniones' ?>)
                            </p>
                            <p class="price">
                                <?= number_format((float) $service['precio'], 2, ',', '.') ?>
                                <span><?= match ($service['tipo_tarifa']) {
                                    'por_hora' => 'por hora',
                                    'turno' => 'por turno',
                                    default => 'por visita',
                                } ?></span>
                            </p>
                            <?php
                                $serviceDuration = (int) $service['duracion_minutos'];
                                $durationLabel = intdiv($serviceDuration, 60) > 0
                                    ? intdiv($serviceDuration, 60) . ' h' . ($serviceDuration % 60 > 0 ? ' ' . ($serviceDuration % 60) . ' min' : '')
                                    : $serviceDuration . ' min';
                            ?>
                            <p class="provider-help">Duración del turno: <?= htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if (($currentUser['rol'] ?? '') === 'User'): ?>
                                <form class="booking-form" method="post" action="reservas.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="reservar">
                                    <input type="hidden" name="prestador_id" value="<?= (int) $service['prestador_id'] ?>">
                                    <input type="hidden" name="servicio_id" value="<?= (int) $service['servicio_id'] ?>">
                                    <label>Fecha
                                        <input type="date" name="fecha" min="<?= date('Y-m-d') ?>" required>
                                    </label>
                                    <label>Turno disponible
                                        <select name="hora" required disabled>
                                            <option value="">Primero elegí una fecha</option>
                                        </select>
                                    </label>
                                    <label>Detalle (opcional)
                                        <textarea name="comentarios" maxlength="2000" rows="2" placeholder="Contanos qué necesitás"></textarea>
                                    </label>
                                    <button class="small-button" type="submit">Solicitar reserva</button>
                                </form>
                            <?php elseif ($currentUser === null): ?>
                                <a class="small-button booking-link" href="login.php">Iniciá sesión para reservar</a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site-footer">
        <p>Servicios Técnicos</p>
    </footer>
    <script>
        document.querySelectorAll('.booking-form').forEach((form) => {
            const dateInput = form.querySelector('input[name="fecha"]');
            const timeSelect = form.querySelector('select[name="hora"]');
            const providerId = form.querySelector('input[name="prestador_id"]').value;
            const serviceId = form.querySelector('input[name="servicio_id"]').value;
            dateInput.addEventListener('change', async () => {
                timeSelect.disabled = true;
                timeSelect.replaceChildren(new Option('Buscando turnos...', ''));
                try {
                    const params = new URLSearchParams({prestador_id: providerId, servicio_id: serviceId, fecha: dateInput.value});
                    const response = await fetch(`turnos.php?${params.toString()}`, {headers: {'Accept': 'application/json'}});
                    if (!response.ok) throw new Error('No se pudieron cargar los turnos');
                    const data = await response.json();
                    timeSelect.replaceChildren();
                    if (!Array.isArray(data.turnos) || data.turnos.length === 0) {
                        timeSelect.add(new Option('No hay turnos disponibles para esa fecha', ''));
                        return;
                    }
                    timeSelect.add(new Option('Elegí un turno', ''));
                    data.turnos.forEach((turno) => timeSelect.add(new Option(turno, turno)));
                    timeSelect.disabled = false;
                } catch (error) {
                    timeSelect.replaceChildren(new Option('No se pudieron cargar los turnos', ''));
                }
            });
        });
    </script>
</body>
</html>
