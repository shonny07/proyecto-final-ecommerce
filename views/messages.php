<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversación | ServiClick</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="index.php"><span class="brand-name">Servi<span class="brand-accent">Click</span></span><img class="brand-icon" src="assets/serviclick-click.png" alt=""></a>
        <nav aria-label="Navegación principal">
            <a href="reservas.php">Volver a reservas</a>
            <a href="logout.php">Cerrar sesión</a>
        </nav>
    </header>
    <main class="conversation-main">
        <div class="provider-heading">
            <p class="eyebrow">Mensajería de ServiClick</p>
            <h1><?= htmlspecialchars($conversation['servicio'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="provider-intro">Reserva #<?= (int) $conversation['reserva_id'] ?> · Conversación con <?= htmlspecialchars($otherParticipant, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php if ($message !== ''): ?><div class="form-success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="form-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="conversation-card" aria-label="Mensajes de la reserva">
            <div class="conversation-toolbar">
                <span>Estado de la reserva: <?= htmlspecialchars($conversation['estado_reserva'], ENT_QUOTES, 'UTF-8') ?></span>
                <a class="small-button" href="mensajes.php?reserva=<?= (int) $conversation['reserva_id'] ?>">Actualizar</a>
            </div>
            <?php $lastMessageIndex = count($conversation['mensajes']) - 1; $lastMessageId = $lastMessageIndex >= 0 ? (int) $conversation['mensajes'][$lastMessageIndex]['id'] : 0; ?>
            <div class="conversation-thread" id="conversation-thread" data-reserva="<?= (int) $conversation['reserva_id'] ?>" data-user="<?= $currentUserId ?>" data-last-message="<?= $lastMessageId ?>" aria-live="polite">
                <?php if ($conversation['mensajes'] === []): ?>
                    <div class="empty-state conversation-empty"><h3>Iniciá la conversación</h3><p>Los mensajes quedan guardados en esta reserva y solo los ven el cliente y el prestador.</p></div>
                <?php else: ?>
                    <?php foreach ($conversation['mensajes'] as $chatMessage): ?>
                        <?php $isMine = (int) $chatMessage['id_emisor'] === $currentUserId; ?>
                        <article class="chat-message <?= $isMine ? 'chat-message-mine' : 'chat-message-theirs' ?>">
                            <div class="chat-message-meta"><strong><?= $isMine ? 'Vos' : htmlspecialchars($chatMessage['nombre_emisor'], ENT_QUOTES, 'UTF-8') ?></strong><time><?= htmlspecialchars(date('d/m/Y H:i', strtotime($chatMessage['fecha_creacion'])), ENT_QUOTES, 'UTF-8') ?></time></div>
                            <p><?= nl2br(htmlspecialchars($chatMessage['contenido'], ENT_QUOTES, 'UTF-8')) ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <form class="conversation-compose" method="post" action="mensajes.php?reserva=<?= (int) $conversation['reserva_id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <label for="contenido">Tu mensaje</label>
                <textarea id="contenido" name="contenido" maxlength="4000" rows="4" required placeholder="Escribí un mensaje para <?= htmlspecialchars($otherParticipant, ENT_QUOTES, 'UTF-8') ?>"></textarea>
                <div class="conversation-compose-footer"><span>Hasta 4000 caracteres</span><button class="small-button" type="submit">Enviar mensaje</button></div>
            </form>
        </section>
    </main>
    <footer class="site-footer"><p>ServiClick</p></footer>
    <script>
        const thread = document.getElementById('conversation-thread');
        let lastMessageId = Number(thread.dataset.lastMessage);
        thread.scrollTop = thread.scrollHeight;

        async function refreshConversation() {
            if (document.hidden) return;
            try {
                const response = await fetch(`mensajes.php?reserva=${thread.dataset.reserva}&ajax=1`, {headers: {'Accept': 'application/json'}});
                if (!response.ok) return;
                const messages = await response.json();
                const newMessages = messages.filter((item) => Number(item.id) > lastMessageId);
                if (newMessages.length === 0) return;
                thread.querySelector('.conversation-empty')?.remove();
                for (const item of newMessages) {
                    const mine = Number(item.id_emisor) === Number(thread.dataset.user);
                    const article = document.createElement('article');
                    article.className = `chat-message ${mine ? 'chat-message-mine' : 'chat-message-theirs'}`;
                    const meta = document.createElement('div');
                    meta.className = 'chat-message-meta';
                    const sender = document.createElement('strong');
                    sender.textContent = mine ? 'Vos' : item.nombre_emisor;
                    const time = document.createElement('time');
                    const date = new Date(String(item.fecha_creacion).replace(' ', 'T'));
                    time.textContent = Number.isNaN(date.getTime()) ? '' : date.toLocaleString('es-AR', {dateStyle: 'short', timeStyle: 'short'});
                    meta.append(sender, time);
                    const body = document.createElement('p');
                    body.textContent = item.contenido;
                    article.append(meta, body);
                    thread.append(article);
                    lastMessageId = Number(item.id);
                }
                thread.scrollTop = thread.scrollHeight;
            } catch (error) {
                // La conversación sigue disponible; el próximo ciclo vuelve a consultar.
            }
        }

        window.setInterval(refreshConversation, 5000);
    </script>
</body>
</html>
