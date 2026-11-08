<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis servicios | Servicios Técnicos</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="index.php">Servicios<span>Técnicos</span></a>
        <nav aria-label="Navegación principal">
            <span class="nav-greeting">Hola, <?= htmlspecialchars($currentUser['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
            <a href="index.php">Ver catálogo</a>
            <a href="reservas.php">Reservas</a>
            <a class="nav-button" href="logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="provider-main">
        <div class="provider-heading">
            <div>
                <p class="eyebrow">Panel del prestador</p>
                <h1>Mis servicios</h1>
                <p class="provider-intro">Configurá tu contacto, publicá tus servicios y prepará tu tarjeta de presentación.</p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="form-success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="form-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="provider-card">
            <div class="provider-card-heading">
                <div>
                    <p class="eyebrow">Paso 1</p>
                    <h2>Perfil de contacto</h2>
                </div>
                <span><?= htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <p class="provider-help">El correo se usa para avisarte de las reservas. WhatsApp es opcional; si lo dejás vacío, no se enviarán mensajes ni se mostrará un número al cliente.</p>
            <form class="provider-profile-form" method="post" action="provider.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="actualizar_perfil">
                <label>
                    <span>WhatsApp (opcional, con código de país)</span>
                    <input type="tel" name="contacto" maxlength="20" placeholder="Dejalo vacío si no querés usar WhatsApp" value="<?= $profile['contacto'] !== null && $profile['contacto'] !== '' ? '+' . htmlspecialchars($profile['contacto'], ENT_QUOTES, 'UTF-8') : '' ?>">
                </label>
                <button class="small-button" type="submit">Guardar contacto</button>
            </form>
        </section>

        <section class="provider-card" id="disponibilidad">
            <div class="provider-card-heading">
                <div>
                    <p class="eyebrow">Paso 2</p>
                    <h2>Mi disponibilidad</h2>
                </div>
                <span><?= count($availabilityBlocks) ?> períodos ocupados</span>
            </div>
            <p class="provider-help">Definí en qué horario atendés cada día y bloqueá períodos puntuales cuando no estés disponible. Las reservas ocuparán automáticamente la duración configurada para el servicio.</p>
            <h3 class="availability-subheading">Horario de atención</h3>
            <?php if ($weeklySchedule === []): ?><p class="provider-help">Todavía no configuraste tus turnos. Los clientes no van a ver horarios disponibles hasta que guardes al menos uno.</p><?php endif; ?>
            <form class="provider-form weekly-schedule-form" method="post" action="provider.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="actualizar_horarios">
                <?php foreach ($weekdays as $day => $weekday): ?>
                    <?php $dayWindows = $weeklySchedule[(string) $day] ?? $weeklySchedule[$day] ?? []; ?>
                    <?php $dayFormat = count($dayWindows) > 1 ? 'separado' : 'corrido'; ?>
                    <div class="weekly-day-row" data-weekly-day>
                        <div class="weekly-day-settings">
                            <strong><?= htmlspecialchars($weekday, ENT_QUOTES, 'UTF-8') ?></strong>
                            <label><span>Formato</span>
                                <select name="formato_horario[<?= $day ?>]" data-schedule-format>
                                    <option value="corrido" <?= $dayFormat === 'corrido' ? 'selected' : '' ?>>De corrido</option>
                                    <option value="separado" <?= $dayFormat === 'separado' ? 'selected' : '' ?>>Separado en dos tramos</option>
                                </select>
                            </label>
                        </div>
                        <div class="weekly-window-pair">
                            <p class="weekly-window-title" data-window-title><?= $dayFormat === 'separado' ? 'Primer tramo' : 'Horario de atención' ?></p>
                            <label><span>Desde</span><input type="time" name="horarios[<?= $day ?>][0][inicio]" value="<?= htmlspecialchars((string) ($dayWindows[0]['inicio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                            <label><span>Hasta</span><input type="time" name="horarios[<?= $day ?>][0][fin]" value="<?= htmlspecialchars((string) ($dayWindows[0]['fin'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                        </div>
                        <div class="weekly-window-pair weekly-second-window" data-second-window <?= $dayFormat === 'corrido' ? 'hidden' : '' ?>>
                            <p class="weekly-window-title">Segundo tramo</p>
                            <label><span>Desde</span><input type="time" name="horarios[<?= $day ?>][1][inicio]" value="<?= htmlspecialchars((string) ($dayWindows[1]['inicio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                            <label><span>Hasta</span><input type="time" name="horarios[<?= $day ?>][1][fin]" value="<?= htmlspecialchars((string) ($dayWindows[1]['fin'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                        </div>
                    </div>
                <?php endforeach; ?>
                <button class="small-button" type="submit">Guardar horario de atención</button>
            </form>
            <h3 class="availability-subheading">Bloqueos puntuales</h3>
            <p class="provider-help">Usá esta opción para vacaciones o períodos ocupados. Si dejás las horas vacías, se bloquean todos los días inclusive desde la fecha inicial hasta la final.</p>
            <form class="provider-form availability-form" method="post" action="provider.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="agregar_bloqueo">
                <div class="availability-fields">
                    <label><span>Desde</span><input type="date" name="fecha_inicio" min="<?= date('Y-m-d') ?>" required></label>
                    <label><span>Hasta (inclusive)</span><input type="date" name="fecha_fin" min="<?= date('Y-m-d') ?>" required></label>
                    <label><span>Hora inicial (opcional)</span><input type="time" name="hora_inicio"></label>
                    <label><span>Hora final (opcional)</span><input type="time" name="hora_fin"></label>
                </div>
                <label><span>Motivo (opcional)</span><input type="text" name="motivo" maxlength="120" placeholder="Ej.: vacaciones o trabajo comprometido"></label>
                <button class="small-button" type="submit">Guardar período no disponible</button>
            </form>
            <?php if ($availabilityBlocks !== []): ?>
                <div class="availability-list">
                    <?php foreach ($availabilityBlocks as $block): ?>
                        <?php $blockStart = strtotime((string) ($block['inicio'] ?? 'now')); $blockEnd = strtotime((string) ($block['fin'] ?? 'now')); ?>
                        <?php $allDayBlock = date('H:i:s', $blockStart) === '00:00:00' && date('H:i:s', $blockEnd) === '00:00:00'; ?>
                        <article class="availability-item">
                            <div>
                                <strong><?= $allDayBlock ? date('d/m/Y', $blockStart) . ' — ' . date('d/m/Y', $blockEnd - 1) . ' (día completo)' : date('d/m/Y H:i', $blockStart) . ' — ' . date('d/m/Y H:i', $blockEnd) ?></strong>
                                <?php if (isset($block['reserva_id'])): ?>
                                    <p>Ocupado automáticamente por la reserva #<?= (int) $block['reserva_id'] ?>.</p>
                                <?php elseif (!empty($block['motivo'])): ?>
                                    <p><?= htmlspecialchars((string) $block['motivo'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($block['id']) && !isset($block['reserva_id'])): ?>
                                <form method="post" action="provider.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="eliminar_bloqueo">
                                    <input type="hidden" name="bloqueo_id" value="<?= htmlspecialchars((string) $block['id'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="delete-button" type="submit">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="provider-help availability-empty">Todavía no cargaste períodos de indisponibilidad.</p>
            <?php endif; ?>
        </section>

        <section class="provider-card">
            <div class="provider-card-heading">
                <div>
                    <p class="eyebrow">Paso 3</p>
                    <h2><?= $offers === [] ? 'Publicá tu primer servicio' : 'Tus servicios publicados' ?></h2>
                </div>
                <span><?= count($offers) ?> publicados</span>
            </div>

            <?php if ($offers !== []): ?>
                <div class="provider-offers">
                    <?php foreach ($offers as $offer): ?>
                        <?php
                            $offerDuration = (int) $offer['duracion_minutos'];
                            $offerDurationLabel = intdiv($offerDuration, 60) > 0 ? intdiv($offerDuration, 60) . ' h' . ($offerDuration % 60 > 0 ? ' ' . ($offerDuration % 60) . ' min' : '') : $offerDuration . ' min';
                            $durationInHours = $offerDuration >= 60 && $offerDuration % 15 === 0;
                            $durationInputValue = $durationInHours ? rtrim(rtrim(number_format($offerDuration / 60, 2, '.', ''), '0'), '.') : (string) $offerDuration;
                        ?>
                        <details class="provider-offer-details service-edit-panel">
                            <summary>
                                <span class="service-edit-title"><span class="service-action-badge">Modificar servicio</span><?= htmlspecialchars($offer['categoria'] . ' · ' . $offer['servicio'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="offer-summary-price">
                                    $<?= number_format((float) $offer['precio'], 2, ',', '.') ?>
                                    <?= match ($offer['tipo_tarifa']) {
                                        'por_hora' => 'por hora',
                                        'turno' => 'por turno',
                                        default => 'por visita',
                                    } ?>
                                    · <?= htmlspecialchars($offerDurationLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </summary>
                            <div class="provider-offer-body">
                                <form class="offer-edit-form" method="post" action="provider.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="actualizar_oferta">
                                    <input type="hidden" name="servicio_id" value="<?= (int) $offer['servicio_id'] ?>">
                                    <label>
                                        <span>Valor</span>
                                        <input type="number" name="precio" min="0.01" step="0.01" required value="<?= htmlspecialchars((string) $offer['precio'], ENT_QUOTES, 'UTF-8') ?>">
                                    </label>
                                    <label>
                                        <span>Tipo de tarifa</span>
                                        <select name="tipo_tarifa" required>
                                            <option value="visita" <?= $offer['tipo_tarifa'] === 'visita' ? 'selected' : '' ?>>Por visita</option>
                                            <option value="por_hora" <?= $offer['tipo_tarifa'] === 'por_hora' ? 'selected' : '' ?>>Por hora</option>
                                            <option value="turno" <?= $offer['tipo_tarifa'] === 'turno' ? 'selected' : '' ?>>Por turno</option>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Duración del turno</span>
                                        <div class="duration-input-group">
                                            <input type="number" name="duracion_valor" min="<?= $durationInHours ? '0.25' : '5' ?>" max="<?= $durationInHours ? '168' : '10080' ?>" step="<?= $durationInHours ? '0.25' : '5' ?>" data-duration-value required value="<?= htmlspecialchars($durationInputValue, ENT_QUOTES, 'UTF-8') ?>">
                                            <select name="duracion_unidad" data-duration-unit aria-label="Unidad de duración" required>
                                                <option value="min" <?= !$durationInHours ? 'selected' : '' ?>>Minutos</option>
                                                <option value="hs" <?= $durationInHours ? 'selected' : '' ?>>Horas</option>
                                            </select>
                                        </div>
                                    </label>
                                    <button class="small-button" type="submit">Guardar cambios</button>
                                </form>
                                <form class="offer-remove-form" method="post" action="provider.php" onsubmit="return confirm('¿Querés quitar este servicio de tu catálogo?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="quitar_oferta">
                                    <input type="hidden" name="servicio_id" value="<?= (int) $offer['servicio_id'] ?>">
                                    <button class="delete-button" type="submit">Quitar servicio</button>
                                </form>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="provider-help">Todavía no tenés servicios publicados. Elegí uno del catálogo o creá el primero.</p>
            <?php endif; ?>

            <div class="provider-service-actions">
                <?php if ($offers === [] && $availableServices !== []): ?>
                    <details class="provider-action-details service-pick-panel" <?= $offers === [] ? 'open' : '' ?>>
                        <summary><?= $offers === [] ? 'Elegir un servicio del catálogo' : 'Agregar un servicio del catálogo' ?></summary>
                        <form class="provider-form" method="post" action="provider.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="agregar_existente">
                            <label>
                                <span>Servicio</span>
                                <select name="servicio_id" required>
                                    <option value="">Elegí un servicio</option>
                                    <?php foreach ($availableServices as $service): ?>
                                        <option value="<?= (int) $service['id'] ?>"><?= htmlspecialchars($service['categoria'] . ' · ' . $service['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <?php require __DIR__ . '/offer_fields.php'; ?>
                            <button class="auth-submit" type="submit">Publicar servicio</button>
                        </form>
                    </details>
                <?php elseif ($offers === [] && $availableServices === []): ?>
                    <p class="provider-help">No hay servicios en el catálogo para elegir. Podés crear uno nuevo abajo.</p>
                <?php endif; ?>

                <?php if ($categories !== []): ?>
                    <details class="provider-action-details service-create-panel" <?= $offers === [] && $availableServices === [] ? 'open' : '' ?>>
                        <summary><span class="service-action-badge">Nuevo</span>Crear un servicio nuevo</summary>
                        <form class="provider-form" method="post" action="provider.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="crear_servicio">
                            <label>
                                <span>Nombre del servicio</span>
                                <input type="text" name="nombre_servicio" maxlength="150" required placeholder="Ej.: Reparación de calefón">
                            </label>
                            <label>
                                <span>Rubro</span>
                                <select name="categoria_id" required>
                                    <option value="">Elegí un rubro</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int) $category['id'] ?>">
                                            <?= htmlspecialchars(($category['nombre_padre'] ? $category['nombre_padre'] . ' · ' : '') . $category['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <?php require __DIR__ . '/offer_fields.php'; ?>
                            <button class="auth-submit" type="submit">Crear y publicar</button>
                        </form>
                    </details>
                <?php else: ?>
                    <p class="provider-help">Todavía no hay rubros. Pedile al administrador que cargue categorías para poder crear un servicio.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="provider-card">
            <div class="provider-card-heading">
                <div>
                    <p class="eyebrow">Paso 4</p>
                    <h2>Tarjeta de presentación</h2>
                </div>
            </div>
            <p class="provider-help">Cada prestador puede publicar una sola imagen. Al hacer clic, el cliente verá todos tus servicios publicados. No incluyas teléfonos, correo ni redes de contacto en la imagen; esos datos se comparten después de una reserva.</p>
            <?php if ($offers === []): ?>
                <p class="provider-help">Para cargar la tarjeta, primero publicá al menos un servicio.</p>
            <?php else: ?>
                <form class="provider-form presentation-form" method="post" action="provider.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="guardar_tarjeta">
                    <label>
                        <span><?= !empty($presentation['imagen']) ? 'Reemplazar imagen (opcional)' : 'Imagen de la tarjeta (obligatoria)' ?></span>
                        <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" <?= empty($presentation['imagen']) ? 'required' : '' ?>>
                    </label>
                    <p class="provider-help">Formatos JPG, PNG o WebP; hasta 5 MB y 6000 × 4000 píxeles.</p>
                    <button class="small-button" type="submit"><?= $presentation === null ? 'Publicar tarjeta' : 'Guardar tarjeta' ?></button>
                </form>
            <?php endif; ?>
            <?php if ($presentation !== null): ?>
                <div class="presentation-preview" aria-label="Vista previa de tu tarjeta">
                    <?php if (!empty($presentation['imagen'])): ?>
                        <img src="<?= htmlspecialchars($presentation['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="Vista previa de tu tarjeta de presentación">
                    <?php else: ?>
                        <span>Todavía tenés que subir la imagen de tu tarjeta.</span>
                    <?php endif; ?>
                </div>
                <form method="post" action="provider.php" onsubmit="return confirm('¿Querés eliminar tu tarjeta del encabezado?')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="eliminar_tarjeta">
                    <button class="delete-button" type="submit">Eliminar tarjeta</button>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site-footer"><p>Servicios Técnicos</p></footer>
    <script>
        document.querySelectorAll('[data-weekly-day]').forEach((dayRow) => {
            const formatSelect = dayRow.querySelector('[data-schedule-format]');
            const secondWindow = dayRow.querySelector('[data-second-window]');
            const firstTitle = dayRow.querySelector('[data-window-title]');
            const refreshScheduleFormat = () => {
                const isSplit = formatSelect.value === 'separado';
                secondWindow.hidden = !isSplit;
                firstTitle.textContent = isSplit ? 'Primer tramo' : 'Horario de atención';
            };
            formatSelect.addEventListener('change', refreshScheduleFormat);
            refreshScheduleFormat();
        });
        document.querySelectorAll('[data-duration-unit]').forEach((unitSelect) => {
            const input = unitSelect.closest('.duration-input-group').querySelector('[data-duration-value]');
            unitSelect.dataset.currentUnit = unitSelect.value;
            const setLimits = () => {
                if (unitSelect.value === 'hs') {
                    input.min = '0.083333';
                    input.max = '168';
                    input.step = 'any';
                    input.placeholder = 'Ej.: 4 o 1.5';
                } else {
                    input.min = '5';
                    input.max = '10080';
                    input.step = '5';
                    input.placeholder = 'Ej.: 45';
                }
            };
            unitSelect.addEventListener('change', () => {
                const previousUnit = unitSelect.dataset.currentUnit;
                const currentValue = Number(input.value);
                if (Number.isFinite(currentValue) && input.value !== '') {
                    const converted = previousUnit === 'min' ? currentValue / 60 : currentValue * 60;
                    input.value = previousUnit === 'min'
                        ? converted.toFixed(6).replace(/0+$/, '').replace(/\.$/, '')
                        : String(Math.round(converted));
                }
                unitSelect.dataset.currentUnit = unitSelect.value;
                setLimits();
            });
            setLimits();
        });
    </script>
</body>
</html>
