<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/categorymodel.php';
require_once __DIR__ . '/../config/security.php';

class AdminController
{
    public function categories(): void
    {
        startSecureSession();
        $currentUser = $_SESSION['usuario'] ?? null;

        if ($currentUser === null) {
            header('Location: login.php');
            exit;
        }
        if (($currentUser['rol'] ?? '') !== 'Adm') {
            http_response_code(403);
            echo 'No tenés permiso para ingresar al panel de administración.';
            return;
        }

        $categoryModel = new CategoryModel();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                $this->redirectWithMessage('error', 'El formulario venció. Recargá la página e intentá de nuevo.');
            }

            $action = (string) ($_POST['action'] ?? '');
            $name = trim((string) ($_POST['nombre'] ?? ''));
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

            try {
                $parentId = $this->parentIdFromPost();
                if ($action === 'crear') {
                    $this->validateCategory($name, $parentId, null, $categoryModel);
                    $categoryModel->create($name, $parentId);
                    $this->redirectWithMessage('mensaje', 'Categoría creada.');
                }

                if ($action === 'editar' && $id !== false && $id > 0) {
                    $this->validateCategory($name, $parentId, (int) $id, $categoryModel);
                    $categoryModel->update((int) $id, $name, $parentId);
                    $this->redirectWithMessage('mensaje', 'Categoría actualizada.');
                }

                if ($action === 'eliminar' && $id !== false && $id > 0) {
                    $category = $categoryModel->find((int) $id);
                    if ($category === null) {
                        throw new DomainException('La categoría ya no existe.');
                    }
                    if ($category['rubro_padre'] !== null) {
                        $categoryModel->deleteSubcategory((int) $id, (int) $category['rubro_padre']);
                        $this->redirectWithMessage('mensaje', 'Subrubro eliminado. El rubro padre se conservó.');
                    }
                    if ($categoryModel->hasServices((int) $id)) {
                        throw new DomainException('No se puede borrar: el rubro padre tiene servicios asociados.');
                    }
                    if ($categoryModel->hasChildren((int) $id)) {
                        throw new DomainException('No se puede borrar: primero eliminá o reasigná sus subrubros.');
                    }
                    $categoryModel->delete((int) $id);
                    $this->redirectWithMessage('mensaje', 'Categoría eliminada.');
                }

                throw new DomainException('La acción no es válida.');
            } catch (DomainException $exception) {
                $this->redirectWithMessage('error', $exception->getMessage());
            } catch (PDOException $exception) {
                error_log('Error al guardar categorías: ' . $exception->getMessage());
                $this->redirectWithMessage('error', 'No se pudo guardar el cambio. Revisá los datos e intentá de nuevo.');
            }
        }

        try {
            $categories = $categoryModel->listAll();
        } catch (PDOException $exception) {
            error_log('Error al listar categorías: ' . $exception->getMessage());
            http_response_code(500);
            echo 'No se pudieron cargar las categorías.';
            return;
        }

        $message = trim((string) ($_GET['mensaje'] ?? ''));
        $error = trim((string) ($_GET['error'] ?? ''));
        $parentCategories = array_values(array_filter($categories, static fn(array $category): bool => $category['rubro_padre'] === null));
        $childCategories = array_values(array_filter($categories, static fn(array $category): bool => $category['rubro_padre'] !== null));
        require __DIR__ . '/../views/admin/categories.php';
    }

    private function parentIdFromPost(): ?int
    {
        $value = trim((string) ($_POST['rubro_padre'] ?? ''));
        if ($value === '') {
            return null;
        }
        $parentId = filter_var($value, FILTER_VALIDATE_INT);
        if ($parentId === false || $parentId < 1) {
            throw new DomainException('Elegí un rubro padre válido o dejalo vacío.');
        }
        return (int) $parentId;
    }

    private function validateCategory(string $name, ?int $parentId, ?int $id, CategoryModel $model): void
    {
        if ($name === '' || mb_strlen($name) > 120) {
            throw new DomainException('El nombre es obligatorio y admite hasta 120 caracteres.');
        }
        if ($parentId !== null && !$model->exists($parentId)) {
            throw new DomainException('El rubro padre seleccionado no existe.');
        }
        if ($id !== null && !$model->exists($id)) {
            throw new DomainException('La categoría que querés editar ya no existe.');
        }
        if ($id !== null && $model->wouldCreateCycle($id, $parentId)) {
            throw new DomainException('Ese rubro padre crearía un ciclo en las categorías.');
        }
    }

    private function redirectWithMessage(string $key, string $message): never
    {
        header('Location: admin.php?' . $key . '=' . rawurlencode($message));
        exit;
    }
}
