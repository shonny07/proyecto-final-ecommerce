<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/usermodel.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/email.php';

class AuthController
{
    private UserModel $users;

    public function __construct()
    {
        $this->users = new UserModel();
    }

    public function login(): void
    {
        startSecureSession();
        $error = '';
        $email = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');

            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                $error = 'La sesión del formulario venció. Recargá e intentá de nuevo.';
            } else {
                try {
                    $user = $this->users->findByEmail($email);
                    if ($user && password_verify($password, $user['password'])) {
                        signInUser($user);
                        header('Location: index.php');
                        exit;
                    }
                    $error = 'El correo o la contraseña no son correctos.';
                } catch (PDOException $exception) {
                    error_log('Error al iniciar sesión: ' . $exception->getMessage());
                    $error = 'No se pudo iniciar sesión. Intentá nuevamente.';
                }
            }
        }

        $title = 'Iniciar sesión';
        require __DIR__ . '/../views/auth/form.php';
    }

    public function register(): void
    {
        startSecureSession();
        $type = (string) ($_GET['tipo'] ?? $_POST['tipo'] ?? 'cliente');
        if (!in_array($type, ['cliente', 'prestador'], true)) {
            $type = 'cliente';
        }
        $error = '';
        $name = '';
        $email = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim((string) ($_POST['nombre'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $confirmation = (string) ($_POST['password_confirmation'] ?? '');

            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                $error = 'La sesión del formulario venció. Recargá e intentá de nuevo.';
            } elseif ($name === '' || !isValidRegistrationEmail($email)) {
                $error = 'Completá tu nombre y un correo con formato y dominio válidos.';
            } elseif (strlen($password) < 8) {
                $error = 'La contraseña debe tener al menos 8 caracteres.';
            } elseif ($password !== $confirmation) {
                $error = 'Las contraseñas no coinciden.';
            } else {
                try {
                    if ($type === 'prestador') {
                        $user = $this->users->createProvider($name, $email, $password);
                    } else {
                        $user = $this->users->createCustomer($name, $email, $password);
                    }

                    if ($error === '') {
                        signInUser($user);
                        header('Location: index.php');
                        exit;
                    }
                } catch (PDOException $exception) {
                    if ($exception->getCode() === '23000') {
                        $error = 'Ya existe una cuenta con ese correo electrónico.';
                    } else {
                        error_log('Error al registrar una cuenta: ' . $exception->getMessage());
                        $error = 'No se pudo completar el registro. Intentá nuevamente.';
                    }
                }
            }
        }

        $title = $type === 'prestador' ? 'Registro de prestador' : 'Registro de cliente';
        require __DIR__ . '/../views/auth/form.php';
    }

    public function setupAdministrator(): void
    {
        startSecureSession();
        $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $isLocal = in_array($remoteAddress, ['127.0.0.1', '::1'], true);
        if (!$isLocal) {
            http_response_code(403);
            echo 'La configuración inicial del administrador solo está habilitada desde esta computadora.';
            return;
        }

        $error = '';
        $complete = false;
        $name = '';
        $email = '';

        try {
            if ($this->users->hasAdministrator()) {
                http_response_code(403);
                echo 'La cuenta inicial del administrador ya fue creada.';
                return;
            }
        } catch (PDOException $exception) {
            error_log('Error al comprobar el administrador inicial: ' . $exception->getMessage());
            http_response_code(500);
            echo 'No se pudo comprobar el estado de la configuración.';
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim((string) ($_POST['nombre'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $confirmation = (string) ($_POST['password_confirmation'] ?? '');

            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                $error = 'La sesión del formulario venció. Recargá e intentá de nuevo.';
            } elseif ($name === '' || !isValidRegistrationEmail($email)) {
                $error = 'Completá tu nombre y un correo con formato y dominio válidos.';
            } elseif (strlen($password) < 8) {
                $error = 'La contraseña debe tener al menos 8 caracteres.';
            } elseif ($password !== $confirmation) {
                $error = 'Las contraseñas no coinciden.';
            } else {
                try {
                    $user = $this->users->createAdministrator($name, $email, $password);
                    signInUser($user);
                    $complete = true;
                } catch (RuntimeException $exception) {
                    $error = $exception->getMessage() === 'administrator_already_exists'
                        ? 'La cuenta inicial del administrador ya fue creada.'
                        : 'No se pudo crear la cuenta. Intentá nuevamente.';
                } catch (PDOException $exception) {
                    $error = $exception->getCode() === '23000'
                        ? 'Ya existe una cuenta con ese correo electrónico.'
                        : 'No se pudo crear la cuenta. Intentá nuevamente.';
                    error_log('Error al crear el administrador inicial: ' . $exception->getMessage());
                }
            }
        }

        $title = 'Crear administrador';
        require __DIR__ . '/../views/auth/form.php';
    }

    public function logout(): void
    {
        startSecureSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
        }
        session_destroy();
        header('Location: index.php');
        exit;
    }
}
