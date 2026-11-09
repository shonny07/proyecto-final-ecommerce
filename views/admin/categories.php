<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar categorías | ServiClick</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="index.php"><span class="brand-name">Servi<span class="brand-accent">Click</span></span><img class="brand-icon" src="assets/serviclick-click.png" alt=""></a>
        <nav aria-label="Navegación principal">
            <span class="nav-greeting">Hola, <?= htmlspecialchars($currentUser['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
            <a href="index.php">Ver catálogo</a>
            <a class="nav-button" href="logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main class="admin-main">
        <div class="admin-heading">
            <div>
                <p class="eyebrow">Administración</p>
                <h1>Rubros y categorías</h1>
                <p class="admin-intro">Organizá los rubros que los prestadores van a elegir al publicar sus servicios.</p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="form-success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="form-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="admin-card">
            <div class="admin-list-heading">
                <div><p class="eyebrow">Nivel principal</p><h2>Rubros padres</h2></div>
                <span><?= count($parentCategories) ?> rubros</span>
            </div>
            <p class="admin-empty category-section-help">Son los rubros generales, por ejemplo: Electricidad, Plomería o Construcción.</p>
            <form class="category-create-form category-parent-create-form" method="post" action="admin.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="crear">
                <label>
                    <span>Nombre del rubro padre</span>
                    <input type="text" name="nombre" maxlength="120" required placeholder="Ej.: Electricidad">
                </label>
                <button class="auth-submit" type="submit">Agregar rubro padre</button>
            </form>

            <?php if ($parentCategories === []): ?>
                <p class="admin-empty category-list-empty">Todavía no hay rubros padres.</p>
            <?php else: ?>
                <div class="category-list">
                    <?php foreach ($parentCategories as $category): ?>
                        <article class="category-row">
                            <form class="category-edit-form category-parent-edit-form" method="post" action="admin.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="editar">
                                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                <label>
                                    <span>Rubro padre</span>
                                    <input type="text" name="nombre" maxlength="120" required value="<?= htmlspecialchars($category['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                </label>
                                <div class="category-actions">
                                    <button class="small-button" type="submit">Guardar</button>
                                </div>
                            </form>
                            <form class="category-delete-form" method="post" action="admin.php" onsubmit="return confirm('¿Querés eliminar esta categoría?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                <button class="delete-button" type="submit">Eliminar</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="admin-card">
            <div class="admin-list-heading">
                <div><p class="eyebrow">Nivel secundario</p><h2>Rubros y categorías</h2></div>
                <span><?= count($childCategories) ?> categorías</span>
            </div>
            <p class="admin-empty category-section-help">Son las opciones más específicas que se agrupan dentro de un rubro padre.</p>
            <?php if ($parentCategories !== []): ?>
                <form class="category-create-form category-child-create-form" method="post" action="admin.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="crear">
                    <label><span>Nombre del rubro</span><input type="text" name="nombre" maxlength="120" required placeholder="Ej.: Instalaciones eléctricas"></label>
                    <label><span>Rubro padre</span>
                        <select name="rubro_padre" required>
                            <option value="">Elegí un rubro padre</option>
                            <?php foreach ($parentCategories as $parent): ?>
                                <option value="<?= (int) $parent['id'] ?>"><?= htmlspecialchars($parent['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="auth-submit" type="submit">Agregar rubro</button>
                </form>
            <?php else: ?>
                <p class="admin-empty">Primero creá un rubro padre para poder agregar categorías debajo.</p>
            <?php endif; ?>

            <?php if ($childCategories === []): ?>
                <p class="admin-empty category-list-empty">Todavía no hay categorías cargadas debajo de los rubros padres.</p>
            <?php else: ?>
                <div class="category-list">
                    <?php foreach ($childCategories as $category): ?>
                        <article class="category-row category-child-row">
                            <form class="category-edit-form" method="post" action="admin.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="editar">
                                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                <label><span>Rubro</span><input type="text" name="nombre" maxlength="120" required value="<?= htmlspecialchars($category['nombre'], ENT_QUOTES, 'UTF-8') ?>"></label>
                                <label><span>Rubro padre</span>
                                    <select name="rubro_padre" required>
                                        <?php foreach ($categories as $parent): ?>
                                            <?php if ((int) $parent['id'] !== (int) $category['id']): ?>
                                                <option value="<?= (int) $parent['id'] ?>" <?= (int) $category['rubro_padre'] === (int) $parent['id'] ? 'selected' : '' ?>><?= htmlspecialchars($parent['nombre'] . ($parent['rubro_padre'] !== null ? ' · ' . $parent['nombre_padre'] : ''), ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <div class="category-actions"><button class="small-button" type="submit">Guardar</button></div>
                            </form>
                            <form class="category-delete-form" method="post" action="admin.php" data-confirm="<?= htmlspecialchars('¿Estás seguro de eliminar el subrubro «' . $category['nombre'] . '»? El rubro padre se conservará.', ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm(this.dataset.confirm)">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                <button class="delete-button" type="submit">Eliminar</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site-footer"><p>ServiClick</p></footer>
</body>
</html>
