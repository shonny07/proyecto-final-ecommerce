<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class CategoryModel
{
    public function listAll(): array
    {
        $statement = getDatabaseConnection()->query(
            'SELECT c.id, c.nombre, c.rubro_padre, padre.nombre AS nombre_padre
             FROM categoria AS c
             LEFT JOIN categoria AS padre ON padre.id = c.rubro_padre
             ORDER BY COALESCE(padre.nombre, c.nombre), c.rubro_padre IS NOT NULL, c.nombre'
        );
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = getDatabaseConnection()->prepare('SELECT id, nombre, rubro_padre FROM categoria WHERE id = :id');
        $statement->execute(['id' => $id]);
        $category = $statement->fetch();
        return $category ?: null;
    }

    public function exists(int $id): bool
    {
        $statement = getDatabaseConnection()->prepare('SELECT 1 FROM categoria WHERE id = :id');
        $statement->execute(['id' => $id]);
        return (bool) $statement->fetchColumn();
    }

    public function wouldCreateCycle(int $categoryId, ?int $parentId): bool
    {
        $visited = [];
        while ($parentId !== null) {
            if ($parentId === $categoryId || isset($visited[$parentId])) {
                return true;
            }
            $visited[$parentId] = true;
            $statement = getDatabaseConnection()->prepare('SELECT rubro_padre FROM categoria WHERE id = :id');
            $statement->execute(['id' => $parentId]);
            $nextParent = $statement->fetchColumn();
            if ($nextParent === false) {
                return true;
            }
            $parentId = $nextParent === null ? null : (int) $nextParent;
        }
        return false;
    }

    public function create(string $name, ?int $parentId): void
    {
        $statement = getDatabaseConnection()->prepare(
            'INSERT INTO categoria (nombre, rubro_padre) VALUES (:nombre, :rubro_padre)'
        );
        $statement->execute(['nombre' => $name, 'rubro_padre' => $parentId]);
    }

    public function update(int $id, string $name, ?int $parentId): void
    {
        $statement = getDatabaseConnection()->prepare(
            'UPDATE categoria SET nombre = :nombre, rubro_padre = :rubro_padre WHERE id = :id'
        );
        $statement->execute(['nombre' => $name, 'rubro_padre' => $parentId, 'id' => $id]);
    }

    public function hasServices(int $id): bool
    {
        $statement = getDatabaseConnection()->prepare('SELECT 1 FROM servicio WHERE rubro = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return (bool) $statement->fetchColumn();
    }

    public function hasChildren(int $id): bool
    {
        $statement = getDatabaseConnection()->prepare('SELECT 1 FROM categoria WHERE rubro_padre = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return (bool) $statement->fetchColumn();
    }

    public function delete(int $id): void
    {
        $statement = getDatabaseConnection()->prepare('DELETE FROM categoria WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function deleteSubcategory(int $id, int $parentId): void
    {
        $connection = getDatabaseConnection();
        $connection->beginTransaction();
        try {
            $category = $connection->prepare('SELECT rubro_padre FROM categoria WHERE id = :id FOR UPDATE');
            $category->execute(['id' => $id]);
            $actualParent = $category->fetchColumn();
            if ($actualParent === false || $actualParent === null || (int) $actualParent !== $parentId) {
                throw new DomainException('El subrubro ya no existe o cambió de rubro padre.');
            }

            // Keep services and any deeper children; only remove the selected subcategory.
            $moveServices = $connection->prepare('UPDATE servicio SET rubro = :parent_id WHERE rubro = :id');
            $moveServices->execute(['parent_id' => $parentId, 'id' => $id]);
            $moveChildren = $connection->prepare('UPDATE categoria SET rubro_padre = :parent_id WHERE rubro_padre = :id');
            $moveChildren->execute(['parent_id' => $parentId, 'id' => $id]);
            $delete = $connection->prepare('DELETE FROM categoria WHERE id = :id');
            $delete->execute(['id' => $id]);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }
}
