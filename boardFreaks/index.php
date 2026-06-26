<?php
declare(strict_types=1);

session_start();

const DB_PATH = __DIR__ . '/storage/boardfreaks.sqlite';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $storagePath = dirname(DB_PATH);
    if (! is_dir($storagePath)) {
        mkdir($storagePath, 0775, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            account_type TEXT NOT NULL DEFAULT "business",
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS affiliate_clients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            phone TEXT,
            membership_level TEXT NOT NULL DEFAULT "Bronze",
            status TEXT NOT NULL DEFAULT "Activo",
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            category TEXT NOT NULL,
            description TEXT,
            image_url TEXT,
            price REAL NOT NULL DEFAULT 0,
            stock INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT "Disponible",
            created_at TEXT NOT NULL
        )'
    );
    ensure_column($pdo, 'products', 'description', 'TEXT');
    ensure_column($pdo, 'products', 'image_url', 'TEXT');

    return $pdo;
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $existingColumn) {
        if ($existingColumn['name'] === $column) {
            return;
        }
    }

    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function check_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (! is_string($token) || ! hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        flash('danger', 'La sesion expiro. Intentalo nuevamente.');
        redirect('login');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

function redirect(string $page, string $anchor = ''): never
{
    header('Location: ?page=' . urlencode($page) . $anchor);
    exit;
}

function redirect_for_role(string $role): never
{
    if (in_array($role, ['admin', 'staff', 'business'], true)) {
        redirect('dashboard');
    }

    redirect('catalog');
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, account_type, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function require_auth(): array
{
    $user = current_user();
    if (! $user) {
        redirect('login');
    }

    return $user;
}

function can_access_dashboard(array $user): bool
{
    return can_assign_roles($user) || in_array((string) $user['account_type'], ['admin', 'staff', 'business'], true);
}

function require_dashboard_access(): array
{
    $user = require_auth();
    if (! can_access_dashboard($user)) {
        flash('warning', 'Tu rol actual tiene acceso al catalogo de juegos.');
        redirect('catalog');
    }

    return $user;
}

function can_assign_roles(array $user): bool
{
    return strtolower(trim((string) $user['name'])) === 'esteban';
}

function guest_only(): void
{
    $user = current_user();
    if ($user) {
        redirect_for_role((string) $user['account_type']);
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function money(float $value): string
{
    return '$' . number_format($value, 2);
}

function all_users(): array
{
    return db()->query('SELECT id, name, email, account_type, created_at FROM users ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
}

function all_clients(): array
{
    return db()->query('SELECT * FROM affiliate_clients ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
}

function all_products(): array
{
    return db()->query('SELECT * FROM products ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
}

function catalog_products(): array
{
    return db()->query('SELECT * FROM products WHERE status = "Disponible" ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
}

function product_image(array $product): string
{
    if (! empty($product['image_url'])) {
        return (string) $product['image_url'];
    }

    $fallbacks = [
        'Cartas' => 'https://images.unsplash.com/photo-1606503153255-59d8b8b82176?auto=format&fit=crop&w=900&q=80',
        'TCG' => 'https://images.unsplash.com/photo-1613771404784-3a5686aa2be3?auto=format&fit=crop&w=900&q=80',
        'Estrategia' => 'https://images.unsplash.com/photo-1610890716171-6b1bb98ffd09?auto=format&fit=crop&w=900&q=80',
        'Familiar' => 'https://images.unsplash.com/photo-1611371805429-8b5c1b2c34ba?auto=format&fit=crop&w=900&q=80',
        'Rol' => 'https://images.unsplash.com/photo-1605870445919-838d190e8e1b?auto=format&fit=crop&w=900&q=80',
        'Party' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=900&q=80',
        'Cooperativo' => 'https://images.unsplash.com/photo-1523875194681-bedd468c58bf?auto=format&fit=crop&w=900&q=80',
    ];

    return $fallbacks[(string) $product['category']] ?? 'https://images.unsplash.com/photo-1610890716171-6b1bb98ffd09?auto=format&fit=crop&w=900&q=80';
}

function cart_count(): int
{
    return array_sum($_SESSION['cart'] ?? []);
}

function handle_register(): void
{
    check_csrf();

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        flash('danger', 'Completa todos los campos para crear tu cuenta.');
        redirect('register');
    }

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Ingresa un correo electronico valido.');
        redirect('register');
    }

    if (strlen($password) < 8) {
        flash('danger', 'La contrasena debe tener al menos 8 caracteres.');
        redirect('register');
    }

    if ($password !== $passwordConfirmation) {
        flash('danger', 'Las contrasenas no coinciden.');
        redirect('register');
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO users (name, email, password, account_type, created_at)
             VALUES (:name, :email, :password, "non_affiliate", :created_at)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('c'),
        ]);
    } catch (PDOException $exception) {
        flash('danger', 'Ya existe una cuenta con ese correo.');
        redirect('register');
    }

    $_SESSION['user_id'] = (int) db()->lastInsertId();
    flash('success', 'Cuenta creada correctamente. Bienvenido al catalogo BoardFreaks.');
    redirect('catalog');
}

function handle_login(): void
{
    check_csrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (! $user || ! password_verify($password, (string) $user['password'])) {
        flash('danger', 'Credenciales incorrectas. Revisa tu correo y contrasena.');
        redirect('login');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    redirect_for_role((string) $user['account_type']);
}

function handle_logout(): void
{
    check_csrf();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    session_start();
    flash('success', 'Sesion cerrada correctamente.');
    redirect('catalog');
}

function handle_save_user(): void
{
    $currentUser = require_dashboard_access();
    check_csrf();

    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $accountType = trim((string) ($_POST['account_type'] ?? 'business'));
    $password = (string) ($_POST['password'] ?? '');
    $allowedRoles = ['affiliate', 'non_affiliate', 'staff', 'business', 'admin'];
    if (! in_array($accountType, $allowedRoles, true)) {
        $accountType = 'non_affiliate';
    }
    if (! can_assign_roles($currentUser)) {
        if ($id > 0) {
            $stmt = db()->prepare('SELECT account_type FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $accountType = (string) ($stmt->fetchColumn() ?: 'non_affiliate');
        } else {
            $accountType = 'non_affiliate';
        }
    }

    if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Completa el nombre y un correo valido para el usuario.');
        redirect('dashboard', '#usuarios');
    }

    try {
        if ($id > 0) {
            if ($password !== '') {
                $stmt = db()->prepare('UPDATE users SET name = :name, email = :email, account_type = :account_type, password = :password WHERE id = :id');
                $stmt->execute([
                    'id' => $id,
                    'name' => $name,
                    'email' => $email,
                    'account_type' => $accountType,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                ]);
            } else {
                $stmt = db()->prepare('UPDATE users SET name = :name, email = :email, account_type = :account_type WHERE id = :id');
                $stmt->execute(['id' => $id, 'name' => $name, 'email' => $email, 'account_type' => $accountType]);
            }
            flash('success', 'Usuario actualizado correctamente.');
        } else {
            if (strlen($password) < 8) {
                flash('danger', 'La contrasena del usuario debe tener al menos 8 caracteres.');
                redirect('dashboard', '#usuarios');
            }
            $stmt = db()->prepare(
                'INSERT INTO users (name, email, password, account_type, created_at)
                 VALUES (:name, :email, :password, :account_type, :created_at)'
            );
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'account_type' => $accountType,
                'created_at' => date('c'),
            ]);
            flash('success', 'Usuario creado correctamente.');
        }
    } catch (PDOException $exception) {
        flash('danger', 'No se pudo guardar el usuario. Revisa si el correo ya existe.');
    }

    redirect('dashboard', '#usuarios');
}

function handle_delete_user(): void
{
    require_dashboard_access();
    check_csrf();

    $id = (int) ($_POST['id'] ?? 0);
    if ($id === 1) {
        flash('warning', 'El usuario con ID 1 esta protegido y no se puede eliminar.');
        redirect('dashboard', '#usuarios');
    }

    $stmt = db()->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    flash('success', 'Usuario eliminado correctamente.');
    redirect('dashboard', '#usuarios');
}

function handle_save_client(): void
{
    require_dashboard_access();
    check_csrf();

    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $membership = trim((string) ($_POST['membership_level'] ?? 'Bronze'));
    $status = trim((string) ($_POST['status'] ?? 'Activo'));

    if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Completa el nombre y correo del cliente afiliado.');
        redirect('dashboard', '#clientes');
    }

    try {
        if ($id > 0) {
            $stmt = db()->prepare(
                'UPDATE affiliate_clients
                 SET name = :name, email = :email, phone = :phone, membership_level = :membership, status = :status
                 WHERE id = :id'
            );
            $stmt->execute(['id' => $id, 'name' => $name, 'email' => $email, 'phone' => $phone, 'membership' => $membership, 'status' => $status]);
            flash('success', 'Cliente afiliado actualizado correctamente.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO affiliate_clients (name, email, phone, membership_level, status, created_at)
                 VALUES (:name, :email, :phone, :membership, :status, :created_at)'
            );
            $stmt->execute(['name' => $name, 'email' => $email, 'phone' => $phone, 'membership' => $membership, 'status' => $status, 'created_at' => date('c')]);
            flash('success', 'Cliente afiliado registrado correctamente.');
        }
    } catch (PDOException $exception) {
        flash('danger', 'No se pudo guardar el cliente. Revisa si el correo ya existe.');
    }

    redirect('dashboard', '#clientes');
}

function handle_delete_client(): void
{
    require_dashboard_access();
    check_csrf();

    $stmt = db()->prepare('DELETE FROM affiliate_clients WHERE id = :id');
    $stmt->execute(['id' => (int) ($_POST['id'] ?? 0)]);
    flash('success', 'Cliente afiliado eliminado correctamente.');
    redirect('dashboard', '#clientes');
}

function handle_save_product(): void
{
    require_dashboard_access();
    check_csrf();

    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? 'Estrategia'));
    $description = trim((string) ($_POST['description'] ?? ''));
    $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
    $price = max(0, (float) ($_POST['price'] ?? 0));
    $stock = max(0, (int) ($_POST['stock'] ?? 0));
    $status = trim((string) ($_POST['status'] ?? 'Disponible'));

    if ($name === '' || $category === '') {
        flash('danger', 'Completa el nombre y categoria del producto.');
        redirect('dashboard', '#productos');
    }

    if ($id > 0) {
        $stmt = db()->prepare(
            'UPDATE products
             SET name = :name, category = :category, description = :description, image_url = :image_url, price = :price, stock = :stock, status = :status
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'name' => $name, 'category' => $category, 'description' => $description, 'image_url' => $imageUrl, 'price' => $price, 'stock' => $stock, 'status' => $status]);
        flash('success', 'Producto actualizado correctamente.');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO products (name, category, description, image_url, price, stock, status, created_at)
             VALUES (:name, :category, :description, :image_url, :price, :stock, :status, :created_at)'
        );
        $stmt->execute(['name' => $name, 'category' => $category, 'description' => $description, 'image_url' => $imageUrl, 'price' => $price, 'stock' => $stock, 'status' => $status, 'created_at' => date('c')]);
        flash('success', 'Producto registrado correctamente.');
    }

    redirect('dashboard', '#productos');
}

function handle_delete_product(): void
{
    require_dashboard_access();
    check_csrf();

    $stmt = db()->prepare('DELETE FROM products WHERE id = :id');
    $stmt->execute(['id' => (int) ($_POST['id'] ?? 0)]);
    flash('success', 'Producto eliminado correctamente.');
    redirect('dashboard', '#productos');
}

function handle_add_to_cart(): void
{
    $user = require_auth();
    check_csrf();

    if ((string) $user['account_type'] !== 'affiliate') {
        flash('warning', 'Solo los clientes afiliados pueden agregar juegos al carrito.');
        redirect('catalog');
    }

    $productId = (int) ($_POST['product_id'] ?? 0);
    $stmt = db()->prepare('SELECT id, name FROM products WHERE id = :id AND status = "Disponible" AND stock > 0 LIMIT 1');
    $stmt->execute(['id' => $productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (! $product) {
        flash('danger', 'Este juego no esta disponible para agregar al carrito.');
        redirect('catalog');
    }

    $_SESSION['cart'][$productId] = (int) ($_SESSION['cart'][$productId] ?? 0) + 1;
    flash('success', 'Agregaste ' . (string) $product['name'] . ' al carrito. El checkout lo trabajaremos despues.');
    redirect('catalog');
}

$action = $_GET['action'] ?? null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    match ($action) {
        'register' => handle_register(),
        'login' => handle_login(),
        'logout' => handle_logout(),
        'save_user' => handle_save_user(),
        'delete_user' => handle_delete_user(),
        'save_client' => handle_save_client(),
        'delete_client' => handle_delete_client(),
        'save_product' => handle_save_product(),
        'delete_product' => handle_delete_product(),
        'add_to_cart' => handle_add_to_cart(),
        default => redirect('login'),
    };
}

$page = $_GET['page'] ?? 'catalog';
if (! in_array($page, ['catalog', 'login', 'register', 'dashboard'], true)) {
    $page = 'catalog';
}

$flash = pull_flash();
?>
<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BoardFreaks | Autenticacion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<?php if ($page === 'catalog'): ?>
    <?php
        $catalogUser = current_user();
        $catalogProducts = catalog_products();
        $categories = array_values(array_unique(array_map(fn (array $product): string => (string) $product['category'], $catalogProducts)));
        sort($categories);
        $maxPrice = count($catalogProducts) > 0 ? max(array_map(fn (array $product): float => (float) $product['price'], $catalogProducts)) : 100;
    ?>
    <main class="catalog-page">
        <nav class="catalog-navbar">
            <a class="catalog-brand" href="?page=catalog">
                <span class="brand-mark"><i class="bi bi-dice-5"></i></span>
                <span>BoardFreaks</span>
            </a>
            <div class="catalog-nav-actions">
                <?php if ($catalogUser && can_access_dashboard($catalogUser)): ?>
                    <a class="btn btn-light" href="?page=dashboard"><i class="bi bi-speedometer2"></i> Admin</a>
                <?php endif; ?>
                <?php if ($catalogUser): ?>
                    <span class="role-pill"><i class="bi bi-person-badge"></i> <?= e((string) $catalogUser['account_type']) ?></span>
                    <span class="cart-pill"><i class="bi bi-cart3"></i> <?= cart_count() ?></span>
                    <form method="post" action="?action=logout">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button class="btn btn-dark" type="submit"><i class="bi bi-box-arrow-right"></i> Salir</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-light" href="?page=login"><i class="bi bi-box-arrow-in-right"></i> Login</a>
                    <a class="btn btn-dark" href="?page=register"><i class="bi bi-person-plus"></i> Registrarme</a>
                <?php endif; ?>
            </div>
        </nav>

        <?php if ($flash): ?>
            <div class="catalog-alert">
                <div class="alert alert-<?= e($flash['type']) ?> shadow-sm border-0 mb-0" role="alert">
                    <?= e($flash['message']) ?>
                </div>
            </div>
        <?php endif; ?>

        <section class="catalog-hero">
            <div>
                <span class="eyebrow"><i class="bi bi-stars"></i> Catalogo publico</span>
                <h1>Juegos de mesa para cada tipo de mesa.</h1>
                <p>Explora cartas, estrategia, TCG, rol y juegos familiares con filtros rapidos, disponibilidad y precios claros.</p>
            </div>
            <div class="catalog-hero-card">
                <i class="bi bi-controller"></i>
                <strong><?= count($catalogProducts) ?></strong>
                <span>juegos disponibles</span>
            </div>
        </section>

        <section class="catalog-layout">
            <aside class="catalog-filters" aria-label="Filtros del catalogo">
                <div class="filter-panel">
                    <h2>Categorias</h2>
                    <button class="category-filter active" type="button" data-category-filter="all"><i class="bi bi-grid"></i> Todos</button>
                    <?php foreach ($categories as $category): ?>
                        <button class="category-filter" type="button" data-category-filter="<?= e($category) ?>">
                            <i class="bi bi-tag"></i> <?= e($category) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="filter-panel">
                    <h2>Precio</h2>
                    <label class="form-label" for="catalogMaxPrice">Hasta <span data-price-output><?= e(money((float) $maxPrice)) ?></span></label>
                    <input id="catalogMaxPrice" class="form-range" type="range" min="0" max="<?= e((string) ceil((float) $maxPrice)) ?>" step="1" value="<?= e((string) ceil((float) $maxPrice)) ?>" data-price-filter>
                </div>
            </aside>

            <div class="catalog-main">
                <div class="catalog-toolbar">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input class="form-control" type="search" placeholder="Buscar juegos en tiempo real" data-catalog-search>
                    </div>
                    <span class="table-counter" data-catalog-counter></span>
                </div>
                <div class="catalog-grid" data-catalog-grid data-page-size="6">
                    <?php foreach ($catalogProducts as $product): ?>
                        <?php
                            $available = (int) $product['stock'] > 0;
                            $role = $catalogUser ? (string) $catalogUser['account_type'] : 'guest';
                        ?>
                        <article class="game-card" data-catalog-card data-category="<?= e((string) $product['category']) ?>" data-price="<?= e((string) $product['price']) ?>" data-search="<?= e(strtolower((string) $product['name'] . ' ' . (string) $product['category'] . ' ' . (string) ($product['description'] ?? ''))) ?>">
                            <div class="game-media">
                                <img src="<?= e(product_image($product)) ?>" alt="<?= e((string) $product['name']) ?>">
                                <span class="game-category"><?= e((string) $product['category']) ?></span>
                            </div>
                            <div class="game-body">
                                <div>
                                    <h2><?= e((string) $product['name']) ?></h2>
                                    <p><?= e((string) ($product['description'] ?: 'Juego destacado para sumar a tu coleccion BoardFreaks.')) ?></p>
                                </div>
                                <div class="game-meta">
                                    <strong><?= e(money((float) $product['price'])) ?></strong>
                                    <span class="<?= $available ? 'available' : 'sold-out' ?>">
                                        <i class="bi <?= $available ? 'bi-check-circle' : 'bi-x-circle' ?>"></i>
                                        <?= $available ? 'Disponible' : 'Agotado' ?>
                                    </span>
                                </div>
                                <?php if ($catalogUser && $role === 'affiliate' && $available): ?>
                                    <form method="post" action="?action=add_to_cart">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-cart-plus"></i> Anadir juego al carrito</button>
                                    </form>
                                <?php elseif ($catalogUser && can_access_dashboard($catalogUser)): ?>
                                    <a class="btn btn-outline-dark w-100" href="?page=dashboard#productos"><i class="bi bi-pencil-square"></i> Administrar catalogo</a>
                                <?php elseif ($catalogUser): ?>
                                    <button class="btn btn-light w-100" type="button" disabled><i class="bi bi-lock"></i> Solo afiliados</button>
                                <?php else: ?>
                                    <a class="btn btn-outline-primary w-100" href="?page=login"><i class="bi bi-box-arrow-in-right"></i> Login para agregar</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="table-empty" data-catalog-empty>No hay juegos que coincidan con los filtros.</div>
                <nav class="table-pagination catalog-pagination" data-catalog-pagination aria-label="Paginacion catalogo"></nav>
            </div>
        </section>
    </main>
<?php elseif ($page === 'dashboard'): ?>
    <?php
        $user = require_dashboard_access();
        $users = all_users();
        $clients = all_clients();
        $products = all_products();
        $canAssignRoles = can_assign_roles($user);
        $inventoryValue = array_reduce($products, fn (float $sum, array $product): float => $sum + ((float) $product['price'] * (int) $product['stock']), 0.0);
    ?>
    <div class="app-shell">
        <aside id="sidebar" class="sidebar">
            <div class="sidebar-brand">
                <span class="brand-mark"><i class="bi bi-kanban"></i></span>
                <span class="brand-text">BoardFreaks</span>
            </div>
            <nav class="sidebar-nav" aria-label="Menu principal">
                <a class="nav-link active" href="#resumen"><i class="bi bi-grid-1x2"></i><span>Dashboard</span></a>
                <a class="nav-link" href="#usuarios"><i class="bi bi-person-gear"></i><span>Usuarios</span></a>
                <a class="nav-link" href="#clientes"><i class="bi bi-people"></i><span>Afiliados</span></a>
                <a class="nav-link" href="#productos"><i class="bi bi-dice-5"></i><span>Productos</span></a>
                <a class="nav-link" href="#resumen"><i class="bi bi-bar-chart-line"></i><span>Analitica</span></a>
                <a class="nav-link" href="#resumen"><i class="bi bi-gear"></i><span>Ajustes</span></a>
            </nav>
            <div class="sidebar-footer">
                <div class="mini-profile">
                    <span class="avatar"><?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?></span>
                    <span>
                        <strong><?= e((string) $user['name']) ?></strong>
                        <small><?= e((string) $user['account_type']) ?></small>
                    </span>
                </div>
            </div>
        </aside>

        <div id="sidebarBackdrop" class="sidebar-backdrop"></div>

        <main class="main-panel">
            <nav class="navbar navbar-expand-lg app-navbar">
                <div class="container-fluid px-0">
                    <button id="sidebarToggle" class="btn btn-icon" type="button" aria-label="Alternar menu lateral">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="navbar-title">
                        <span>Panel business</span>
                        <small><?= e((string) $user['email']) ?></small>
                    </div>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <button class="btn btn-light btn-icon" type="button" aria-label="Notificaciones">
                            <i class="bi bi-bell"></i>
                        </button>
                        <form method="post" action="?action=logout">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button class="btn btn-dark d-none d-sm-inline-flex align-items-center gap-2" type="submit">
                                <i class="bi bi-box-arrow-right"></i>
                                Salir
                            </button>
                            <button class="btn btn-dark btn-icon d-sm-none" type="submit" aria-label="Cerrar sesion">
                                <i class="bi bi-box-arrow-right"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </nav>

            <section id="resumen" class="dashboard-content">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?> shadow-sm border-0" role="alert">
                        <?= e($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <div class="hero-panel">
                    <div>
                        <span class="eyebrow"><i class="bi bi-stars"></i> Tienda de juegos de mesa</span>
                        <h1>Hola, <?= e((string) $user['name']) ?>.</h1>
                        <p>Administra usuarios, clientes afiliados y productos desde una sola pantalla con formularios en modales Bootstrap.</p>
                    </div>
                    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#productModal">
                        <i class="bi bi-plus-circle"></i>
                        Nuevo producto
                    </button>
                </div>

                <div class="row g-4">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="metric-card">
                            <span class="metric-icon bg-primary-subtle text-primary"><i class="bi bi-person-gear"></i></span>
                            <small>Usuarios</small>
                            <strong><?= count($users) ?></strong>
                            <span class="trend neutral"><i class="bi bi-shield-lock"></i> ID 1 protegido</span>
                        </article>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="metric-card">
                            <span class="metric-icon bg-success-subtle text-success"><i class="bi bi-people"></i></span>
                            <small>Afiliados</small>
                            <strong><?= count($clients) ?></strong>
                            <span class="trend up"><i class="bi bi-person-check"></i> clientes</span>
                        </article>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="metric-card">
                            <span class="metric-icon bg-warning-subtle text-warning"><i class="bi bi-dice-5"></i></span>
                            <small>Productos</small>
                            <strong><?= count($products) ?></strong>
                            <span class="trend neutral"><i class="bi bi-box-seam"></i> inventario</span>
                        </article>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="metric-card">
                            <span class="metric-icon bg-info-subtle text-info"><i class="bi bi-cash-coin"></i></span>
                            <small>Valor stock</small>
                            <strong><?= e(money($inventoryValue)) ?></strong>
                            <span class="trend up"><i class="bi bi-graph-up-arrow"></i> tienda</span>
                        </article>
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-12 col-xl-7">
                        <article class="content-card h-100">
                            <div class="card-heading">
                                <div>
                                    <h2>Operacion BoardFreaks</h2>
                                    <p>Gestion comercial enfocada en juegos de mesa.</p>
                                </div>
                            </div>
                            <div class="activity-list">
                                <div><span class="dot bg-primary"></span><strong>Usuarios del equipo</strong><small><?= count($users) ?> registros</small></div>
                                <div><span class="dot bg-success"></span><strong>Clientes afiliados activos</strong><small><?= count(array_filter($clients, fn (array $client): bool => $client['status'] === 'Activo')) ?> activos</small></div>
                                <div><span class="dot bg-warning"></span><strong>Productos disponibles</strong><small><?= count(array_filter($products, fn (array $product): bool => $product['status'] === 'Disponible')) ?> disponibles</small></div>
                                <div><span class="dot bg-info"></span><strong>Inventario valorizado</strong><small><?= e(money($inventoryValue)) ?></small></div>
                            </div>
                        </article>
                    </div>
                    <div class="col-12 col-xl-5">
                        <article class="content-card h-100">
                            <div class="card-heading">
                                <div>
                                    <h2>Acciones rapidas</h2>
                                    <p>Registra datos sin salir del dashboard.</p>
                                </div>
                            </div>
                            <div class="quick-actions">
                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#userModal"><i class="bi bi-person-plus"></i> Usuario</button>
                                <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#clientModal"><i class="bi bi-person-heart"></i> Afiliado</button>
                                <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#productModal"><i class="bi bi-dice-5"></i> Producto</button>
                            </div>
                        </article>
                    </div>
                </div>

                <section id="usuarios" class="crud-section">
                    <div class="section-heading">
                        <div>
                            <span class="eyebrow"><i class="bi bi-person-gear"></i> Administracion</span>
                            <h2>Usuarios</h2>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal"><i class="bi bi-plus-lg"></i> Nuevo usuario</button>
                    </div>
                    <article class="content-card">
                        <div class="table-toolbar">
                            <div class="search-box">
                                <i class="bi bi-search"></i>
                                <input class="form-control" type="search" data-table-search="usersTable" placeholder="Buscar usuarios en tiempo real">
                            </div>
                            <span class="table-counter" data-table-counter="usersTable"></span>
                        </div>
                        <div class="table-responsive">
                            <table id="usersTable" class="table align-middle app-table" data-page-size="5">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Tipo</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $row): ?>
                                        <tr>
                                            <td><?= (int) $row['id'] ?></td>
                                            <td><?= e((string) $row['name']) ?></td>
                                            <td><?= e((string) $row['email']) ?></td>
                                            <td><span class="badge rounded-pill text-bg-light"><?= e((string) $row['account_type']) ?></span></td>
                                            <td class="text-end">
                                                <button class="btn btn-light btn-icon btn-edit-user" type="button" data-bs-toggle="modal" data-bs-target="#userModal" data-id="<?= (int) $row['id'] ?>" data-name="<?= e((string) $row['name']) ?>" data-email="<?= e((string) $row['email']) ?>" data-account-type="<?= e((string) $row['account_type']) ?>" aria-label="Editar usuario"><i class="bi bi-pencil"></i></button>
                                                <form class="d-inline" method="post" action="?action=delete_user" data-confirm="Eliminar este usuario?">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                    <button class="btn btn-light btn-icon text-danger" type="submit" <?= (int) $row['id'] === 1 ? 'disabled title="El ID 1 esta protegido"' : '' ?> aria-label="Eliminar usuario"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-empty" data-table-empty="usersTable">No hay usuarios para mostrar.</div>
                        <nav class="table-pagination" data-table-pagination="usersTable" aria-label="Paginacion usuarios"></nav>
                    </article>
                </section>

                <section id="clientes" class="crud-section">
                    <div class="section-heading">
                        <div>
                            <span class="eyebrow"><i class="bi bi-people"></i> Comunidad</span>
                            <h2>Clientes afiliados</h2>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#clientModal"><i class="bi bi-plus-lg"></i> Nuevo afiliado</button>
                    </div>
                    <article class="content-card">
                        <div class="table-toolbar">
                            <div class="search-box">
                                <i class="bi bi-search"></i>
                                <input class="form-control" type="search" data-table-search="clientsTable" placeholder="Buscar afiliados en tiempo real">
                            </div>
                            <span class="table-counter" data-table-counter="clientsTable"></span>
                        </div>
                        <div class="table-responsive">
                            <table id="clientsTable" class="table align-middle app-table" data-page-size="5">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Telefono</th>
                                        <th>Membresia</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $row): ?>
                                        <tr>
                                            <td><?= (int) $row['id'] ?></td>
                                            <td>
                                                <strong><?= e((string) $row['name']) ?></strong>
                                                <small><?= e((string) $row['email']) ?></small>
                                            </td>
                                            <td><?= e((string) $row['phone']) ?></td>
                                            <td><span class="badge rounded-pill text-bg-light"><?= e((string) $row['membership_level']) ?></span></td>
                                            <td><span class="badge rounded-pill <?= $row['status'] === 'Activo' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e((string) $row['status']) ?></span></td>
                                            <td class="text-end">
                                                <button class="btn btn-light btn-icon btn-edit-client" type="button" data-bs-toggle="modal" data-bs-target="#clientModal" data-id="<?= (int) $row['id'] ?>" data-name="<?= e((string) $row['name']) ?>" data-email="<?= e((string) $row['email']) ?>" data-phone="<?= e((string) $row['phone']) ?>" data-membership-level="<?= e((string) $row['membership_level']) ?>" data-status="<?= e((string) $row['status']) ?>" aria-label="Editar cliente"><i class="bi bi-pencil"></i></button>
                                                <form class="d-inline" method="post" action="?action=delete_client" data-confirm="Eliminar este cliente afiliado?">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                    <button class="btn btn-light btn-icon text-danger" type="submit" aria-label="Eliminar cliente"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-empty" data-table-empty="clientsTable">No hay clientes afiliados para mostrar.</div>
                        <nav class="table-pagination" data-table-pagination="clientsTable" aria-label="Paginacion afiliados"></nav>
                    </article>
                </section>

                <section id="productos" class="crud-section">
                    <div class="section-heading">
                        <div>
                            <span class="eyebrow"><i class="bi bi-dice-5"></i> Catalogo</span>
                            <h2>Productos</h2>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal"><i class="bi bi-plus-lg"></i> Nuevo producto</button>
                    </div>
                    <article class="content-card">
                        <div class="table-toolbar">
                            <div class="search-box">
                                <i class="bi bi-search"></i>
                                <input class="form-control" type="search" data-table-search="productsTable" placeholder="Buscar productos en tiempo real">
                            </div>
                            <span class="table-counter" data-table-counter="productsTable"></span>
                        </div>
                        <div class="table-responsive">
                            <table id="productsTable" class="table align-middle app-table" data-page-size="5">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Producto</th>
                                        <th>Categoria</th>
                                        <th>Precio</th>
                                        <th>Stock</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $row): ?>
                                        <tr>
                                            <td><?= (int) $row['id'] ?></td>
                                            <td><strong><?= e((string) $row['name']) ?></strong></td>
                                            <td><?= e((string) $row['category']) ?></td>
                                            <td><?= e(money((float) $row['price'])) ?></td>
                                            <td><?= (int) $row['stock'] ?></td>
                                            <td><span class="badge rounded-pill <?= $row['status'] === 'Disponible' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e((string) $row['status']) ?></span></td>
                                            <td class="text-end">
                                                <button class="btn btn-light btn-icon btn-edit-product" type="button" data-bs-toggle="modal" data-bs-target="#productModal" data-id="<?= (int) $row['id'] ?>" data-name="<?= e((string) $row['name']) ?>" data-category="<?= e((string) $row['category']) ?>" data-description="<?= e((string) ($row['description'] ?? '')) ?>" data-image-url="<?= e((string) ($row['image_url'] ?? '')) ?>" data-price="<?= e((string) $row['price']) ?>" data-stock="<?= (int) $row['stock'] ?>" data-status="<?= e((string) $row['status']) ?>" aria-label="Editar producto"><i class="bi bi-pencil"></i></button>
                                                <form class="d-inline" method="post" action="?action=delete_product" data-confirm="Eliminar este producto?">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                    <button class="btn btn-light btn-icon text-danger" type="submit" aria-label="Eliminar producto"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-empty" data-table-empty="productsTable">No hay productos para mostrar.</div>
                        <nav class="table-pagination" data-table-pagination="productsTable" aria-label="Paginacion productos"></nav>
                    </article>
                </section>
            </section>
        </main>
    </div>

    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="?action=save_user">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" data-field="id">
                <div class="modal-header">
                    <h1 id="userModalTitle" class="modal-title fs-5"><i class="bi bi-person-gear"></i> Usuario</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body form-grid">
                    <div>
                        <label class="form-label" for="userName">Nombre</label>
                        <input id="userName" class="form-control rounded-control" name="name" data-field="name" required>
                    </div>
                    <div>
                        <label class="form-label" for="userEmail">Correo</label>
                        <input id="userEmail" class="form-control rounded-control" type="email" name="email" data-field="email" required>
                    </div>
                    <div>
                        <label class="form-label" for="userType">Tipo</label>
                        <select id="userType" class="form-select rounded-control" name="account_type" data-field="accountType" <?= $canAssignRoles ? '' : 'disabled' ?>>
                            <option value="affiliate">Afiliado</option>
                            <option value="non_affiliate">No afiliado</option>
                            <option value="staff">Staff</option>
                            <option value="business">Business</option>
                            <option value="admin">Admin</option>
                        </select>
                        <?php if (! $canAssignRoles): ?>
                            <small class="form-hint">Solo Esteban puede asignar roles.</small>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label" for="userPassword">Contrasena</label>
                        <input id="userPassword" class="form-control rounded-control" type="password" name="password" data-field="password" minlength="8" placeholder="Minimo 8 caracteres">
                        <small class="form-hint">En edicion puedes dejarla vacia para conservarla.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="?action=save_client">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" data-field="id">
                <div class="modal-header">
                    <h1 id="clientModalTitle" class="modal-title fs-5"><i class="bi bi-person-heart"></i> Cliente afiliado</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body form-grid">
                    <div>
                        <label class="form-label" for="clientName">Nombre</label>
                        <input id="clientName" class="form-control rounded-control" name="name" data-field="name" required>
                    </div>
                    <div>
                        <label class="form-label" for="clientEmail">Correo</label>
                        <input id="clientEmail" class="form-control rounded-control" type="email" name="email" data-field="email" required>
                    </div>
                    <div>
                        <label class="form-label" for="clientPhone">Telefono</label>
                        <input id="clientPhone" class="form-control rounded-control" name="phone" data-field="phone">
                    </div>
                    <div>
                        <label class="form-label" for="membershipLevel">Membresia</label>
                        <select id="membershipLevel" class="form-select rounded-control" name="membership_level" data-field="membershipLevel">
                            <option>Bronze</option>
                            <option>Silver</option>
                            <option>Gold</option>
                            <option>Platinum</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="clientStatus">Estado</label>
                        <select id="clientStatus" class="form-select rounded-control" name="status" data-field="status">
                            <option>Activo</option>
                            <option>Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="?action=save_product">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" data-field="id">
                <div class="modal-header">
                    <h1 id="productModalTitle" class="modal-title fs-5"><i class="bi bi-dice-5"></i> Producto</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body form-grid">
                    <div>
                        <label class="form-label" for="productName">Nombre del juego</label>
                        <input id="productName" class="form-control rounded-control" name="name" data-field="name" required>
                    </div>
                    <div>
                        <label class="form-label" for="productCategory">Categoria</label>
                        <select id="productCategory" class="form-select rounded-control" name="category" data-field="category">
                            <option>Estrategia</option>
                            <option>Familiar</option>
                            <option>Cartas</option>
                            <option>TCG</option>
                            <option>Rol</option>
                            <option>Party</option>
                            <option>Cooperativo</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="productDescription">Descripcion</label>
                        <textarea id="productDescription" class="form-control rounded-control" name="description" data-field="description" rows="3" placeholder="Resumen corto para el catalogo"></textarea>
                    </div>
                    <div>
                        <label class="form-label" for="productImage">URL de foto</label>
                        <input id="productImage" class="form-control rounded-control" type="url" name="image_url" data-field="imageUrl" placeholder="https://...">
                    </div>
                    <div>
                        <label class="form-label" for="productPrice">Precio</label>
                        <input id="productPrice" class="form-control rounded-control" type="number" name="price" data-field="price" min="0" step="0.01" required>
                    </div>
                    <div>
                        <label class="form-label" for="productStock">Stock</label>
                        <input id="productStock" class="form-control rounded-control" type="number" name="stock" data-field="stock" min="0" step="1" required>
                    </div>
                    <div>
                        <label class="form-label" for="productStatus">Estado</label>
                        <select id="productStatus" class="form-select rounded-control" name="status" data-field="status">
                            <option>Disponible</option>
                            <option>Agotado</option>
                            <option>Oculto</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <?php guest_only(); ?>
    <main class="auth-page">
        <section class="auth-visual">
            <div class="auth-brand">
                <span class="brand-mark"><i class="bi bi-kanban"></i></span>
                <span>BoardFreaks</span>
            </div>
            <div>
                <span class="eyebrow"><i class="bi bi-lightning-charge"></i> Business by default</span>
                <h1>Gestion moderna para equipos que se mueven rapido.</h1>
                <p>Accede a un panel limpio, responsivo y listo para administrar tu tienda de juegos de mesa.</p>
            </div>
        </section>
        <section class="auth-card-wrap">
            <div class="auth-card">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?> border-0 shadow-sm" role="alert">
                        <?= e($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <?php if ($page === 'register'): ?>
                    <div class="auth-heading">
                        <span class="auth-icon"><i class="bi bi-person-plus"></i></span>
                        <h2>Crear cuenta</h2>
                        <p>Tu perfil se activara automaticamente como business.</p>
                    </div>
                    <form method="post" action="?action=register" class="auth-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <label class="form-label" for="name">Nombre completo</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input id="name" name="name" class="form-control" type="text" autocomplete="name" required>
                        </div>
                        <label class="form-label" for="registerEmail">Correo electronico</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input id="registerEmail" name="email" class="form-control" type="email" autocomplete="email" required>
                        </div>
                        <label class="form-label" for="registerPassword">Contrasena</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input id="registerPassword" name="password" class="form-control" type="password" autocomplete="new-password" minlength="8" required>
                        </div>
                        <label class="form-label" for="passwordConfirmation">Confirmar contrasena</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                            <input id="passwordConfirmation" name="password_confirmation" class="form-control" type="password" autocomplete="new-password" minlength="8" required>
                        </div>
                        <button class="btn btn-primary btn-lg w-100" type="submit">
                            <i class="bi bi-arrow-right-circle"></i>
                            Registrarme
                        </button>
                    </form>
                    <p class="auth-switch">Ya tienes cuenta? <a href="?page=login">Inicia sesion</a></p>
                <?php else: ?>
                    <div class="auth-heading">
                        <span class="auth-icon"><i class="bi bi-box-arrow-in-right"></i></span>
                        <h2>Iniciar sesion</h2>
                        <p>Bienvenido de vuelta. Entra a tu dashboard business.</p>
                    </div>
                    <form method="post" action="?action=login" class="auth-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <label class="form-label" for="loginEmail">Correo electronico</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input id="loginEmail" name="email" class="form-control" type="email" autocomplete="email" required>
                        </div>
                        <label class="form-label" for="loginPassword">Contrasena</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input id="loginPassword" name="password" class="form-control" type="password" autocomplete="current-password" required>
                        </div>
                        <button class="btn btn-primary btn-lg w-100" type="submit">
                            <i class="bi bi-unlock"></i>
                            Entrar
                        </button>
                    </form>
                    <p class="auth-switch">No tienes cuenta? <a href="?page=register">Crear cuenta business</a></p>
                <?php endif; ?>
            </div>
        </section>
    </main>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
