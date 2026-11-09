<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis reservas | ServiClick</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="index.php"><span class="brand-name">Servi<span class="brand-accent">Click</span></span><img class="brand-icon" src="assets/serviclick-click.png" alt=""></a>
        <nav aria-label="Navegación principal">
            <a href="index.php">Catálogo</a>
            <?php if ($role === 'prestador'): ?><a href="provider.php">Mis servicios</a><?php endif; ?>
            <a href="logout.php">Cerrar sesión</a>
        </nav>
    </header>
    <main class="reservation-main">
        <div class="provider-heading">
            <p class="eyebrow"><?= $role === 'prestador' ? 'Panel del prestador' : 'Tu cuenta' ?></p>
            <h1><?= $role === 'prestador' ? 'Reservas recibidas' : 'Mis reservas' ?></h1>
            <p class="provider-intro"><?= $role === 'prestador' ? 'Podés cancelar los turnos futuros o marcar como completados los que ya terminaron.' : 'Podés cancelar tus turnos futuros y calificar las reservas completadas.' ?></p>
        </div>
        <?php if ($message !== ''): ?><div class="form-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="form-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($reservations === []): ?>
            <div class="empty-state"><h3>Todavía no hay reservas</h3><p>Las reservas nuevas aparecerán acá.</p></div>
        <?php else: ?>
            <div class="reservation-list">
                <?php foreach ($reservations as $reservation): ?>
                    <article class="reservation-card">
                        <div class="reservation-title">
                            <div><p class="category-tag"><?= htmlspecialchars($reservation['estado'], ENT_QUOTES, 'UTF-8') ?></p><h2><?= htmlspecialchars($reservation['servicio'], ENT_QUOTES, 'UTF-8') ?></h2></div>
                            <?php $endFormat = date('Y-m-d', strtotime($reservation['fecha_hora'])) === date('Y-m-d', strtotime($reservation['fecha_hora_fin'])) ? 'H:i' : 'd/m/Y H:i'; ?>
                            <time><?= htmlspecialchars(date('d/m/Y H:i', strtotime($reservation['fecha_hora'])) . ' — ' . date($endFormat, strtotime($reservation['fecha_hora_fin'])), ENT_QUOTES, 'UTF-8') ?></time>
                        </div>
                        <p><?= $role === 'prestador' ? 'Cliente: ' . htmlspecialchars($reservation['cliente'], ENT_QUOTES, 'UTF-8') : 'Prestador: ' . htmlspecialchars($reservation['prestador'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p><strong>Importe acordado:</strong> $<?= number_format((float) $reservation['precio_acordado'], 2, ',', '.') ?> ARS</p>
                        <?php $paymentLabels = ['approved' => 'Confirmado', 'pending' => 'Pendiente', 'in_process' => 'En proceso', 'rejected' => 'Rechazado', 'cancelled' => 'Cancelado', 'refunded' => 'Reembolsado', 'charged_back' => 'Desconocido por contracargo', 'error' => 'No se pudo iniciar', 'simulado_aprobado' => 'Aprobado (simulación)']; ?>
                        <p><strong>Pago:</strong>
                            <?php if (($reservation['pago_proveedor'] ?? '') === 'simulacion'): ?>Aprobado — simulación académica
                            <?php elseif ((int) ($reservation['pago_confirmado'] ?? 0) === 1): ?>Confirmado
                            <?php elseif (!empty($reservation['pago_estado'])): ?><?= htmlspecialchars($paymentLabels[(string) $reservation['pago_estado']] ?? (string) $reservation['pago_estado'], ENT_QUOTES, 'UTF-8') ?>
                            <?php else: ?>Pendiente de pago<?php endif; ?>
                        </p>
                        <?php if ($role === 'User' && !empty($reservation['pago_id']) && (int) ($reservation['pago_confirmado'] ?? 0) === 1): ?>
                            <p><strong>Medio:</strong> <?= htmlspecialchars((string) ($reservation['pago_metodo'] ?? 'Mercado Pago'), ENT_QUOTES, 'UTF-8') ?></p>
                            <a class="small-button" href="comprobante.php?pago=<?= (int) $reservation['pago_id'] ?>">Descargar comprobante PDF</a>
                        <?php endif; ?>
                        <?php if ($role === 'User' && $reservation['estado'] === 'pendiente' && (int) ($reservation['pago_confirmado'] ?? 0) !== 1): ?>
                            <?php if ($mercadoPagoEnabled): ?>
                                <form method="post" action="pagar.php" class="reservation-action">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="reserva_id" value="<?= (int) $reservation['id'] ?>">
                                    <button class="small-button" type="submit">Pagar con Mercado Pago</button>
                                    <p class="provider-help">Podés pagar como invitado con tarjeta de débito o crédito. Mercado Pago también puede ofrecer otros medios según la cuenta y el importe.</p>
                                </form>
                            <?php endif; ?>
                            <?php if ($demoPaymentsEnabled): ?>
                                <form method="post" action="pagar.php" class="reservation-action">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="reserva_id" value="<?= (int) $reservation['id'] ?>">
                                    <input type="hidden" name="accion" value="simular">
                                    <label>Simular un medio de pago
                                        <select name="medio_pago" required>
                                            <option value="debito">Tarjeta de débito</option>
                                            <option value="credito">Tarjeta de crédito</option>
                                            <option value="transferencia">Transferencia bancaria</option>
                                        </select>
                                    </label>
                                    <button class="small-button" type="submit">Simular pago y generar comprobante</button>
                                    <p class="provider-help">Modo de demostración: no se realiza ningún cobro. El comprobante PDF indicará que es una simulación académica.</p>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($role === 'User' && !empty($reservation['contacto_prestador'])): ?>
                            <p class="reservation-contact">WhatsApp del prestador: <a href="tel:+<?= htmlspecialchars($reservation['contacto_prestador'], ENT_QUOTES, 'UTF-8') ?>">+<?= htmlspecialchars($reservation['contacto_prestador'], ENT_QUOTES, 'UTF-8') ?></a></p>
                        <?php endif; ?>
                        <?php if (!empty($reservation['detalle'])): ?><p class="provider-help"><?= nl2br(htmlspecialchars($reservation['detalle'], ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
                        <?php if ($reservation['estado'] === 'pendiente' && strtotime($reservation['fecha_hora']) > time()): ?>
                            <form method="post" action="reservas.php" class="reservation-action" onsubmit="return confirm('¿Querés cancelar esta reserva? El turno quedará disponible nuevamente.')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="cancelar">
                                <input type="hidden" name="reserva_id" value="<?= (int) $reservation['id'] ?>">
                                <button class="delete-button" type="submit">Cancelar reserva</button>
                            </form>
                        <?php elseif ($role === 'prestador' && $reservation['estado'] === 'pendiente' && strtotime($reservation['fecha_hora_fin']) <= time()): ?>
                            <form method="post" action="reservas.php" class="reservation-action">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="completar">
                                <input type="hidden" name="reserva_id" value="<?= (int) $reservation['id'] ?>">
                                <button class="small-button" type="submit">Marcar como completada</button>
                            </form>
                        <?php elseif ($role === 'User' && $reservation['estado'] === 'completada' && $reservation['estrellas'] === null): ?>
                            <form method="post" action="reservas.php" class="rating-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="calificar">
                                <input type="hidden" name="reserva_id" value="<?= (int) $reservation['id'] ?>">
                                <label>Calificación
                                    <select name="estrellas" required>
                                        <option value="">Elegí estrellas</option>
                                        <option value="5">★★★★★ — 5</option>
                                        <option value="4">★★★★ — 4</option>
                                        <option value="3">★★★ — 3</option>
                                        <option value="2">★★ — 2</option>
                                        <option value="1">★ — 1</option>
                                    </select>
                                </label>
                                <label>Opinión (opcional)
                                    <textarea name="opinion" maxlength="500" rows="3" placeholder="¿Cómo fue el servicio?"></textarea>
                                </label>
                                <button class="small-button" type="submit">Enviar calificación</button>
                            </form>
                        <?php elseif ($role === 'User' && $reservation['estrellas'] !== null): ?>
                            <p class="rating-readonly">Tu calificación: <span aria-label="<?= (int) $reservation['estrellas'] ?> de 5 estrellas"><?= str_repeat('★', (int) $reservation['estrellas']) . str_repeat('☆', 5 - (int) $reservation['estrellas']) ?></span><?php if (!empty($reservation['opinion'])): ?> — <?= htmlspecialchars($reservation['opinion'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <footer class="site-footer"><p>ServiClick</p></footer>
</body>
</html>
