<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class UserModel
{
    public function findByEmail(string $email): ?array
    {
        $statement = getDatabaseConnection()->prepare(
            'SELECT u.id, u.nombre, u.email, u.password, u.id_rol, r.rol
             FROM usuario AS u
             INNER JOIN roles AS r ON r.id = u.id_rol
             WHERE u.email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        return $user ?: null;
    }

    public function createCustomer(string $name, string $email, string $password): array
    {
        return $this->createUser('User', $name, $email, $password);
    }

    public function createProvider(string $name, string $email, string $password): array
    {
        $connection = getDatabaseConnection();
        $connection->beginTransaction();

        try {
            $roleId = $this->roleId('prestador');
            $userId = $this->insertUser($roleId, $name, $email, $password);
            $statement = $connection->prepare(
                'INSERT INTO prestador (monto, id_user, contacto, horarios, bloqueos)
                 VALUES (NULL, :id_user, NULL, NULL, NULL)'
            );
            $statement->execute([
                'id_user' => $userId,
            ]);
            $connection->commit();
            return $this->findByEmail($email) ?? throw new RuntimeException('No se pudo recuperar la cuenta creada.');
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public function hasAdministrator(): bool
    {
        $statement = getDatabaseConnection()->prepare(
            "SELECT COUNT(*) FROM usuario AS u INNER JOIN roles AS r ON r.id = u.id_rol WHERE r.rol = 'Adm'"
        );
        $statement->execute();
        return (int) $statement->fetchColumn() > 0;
    }

    public function createAdministrator(string $name, string $email, string $password): array
    {
        $connection = getDatabaseConnection();
        $connection->beginTransaction();

        try {
            $roleId = $this->roleId('Adm', true);
            $statement = $connection->prepare('SELECT COUNT(*) FROM usuario WHERE id_rol = :role_id');
            $statement->execute(['role_id' => $roleId]);
            if ((int) $statement->fetchColumn() > 0) {
                throw new RuntimeException('administrator_already_exists');
            }

            $userId = $this->insertUser($roleId, $name, $email, $password);
            $connection->commit();
            return $this->findByEmail($email) ?? throw new RuntimeException('No se pudo recuperar la cuenta creada.');
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    private function createUser(string $role, string $name, string $email, string $password): array
    {
        $roleId = $this->roleId($role);
        $this->insertUser($roleId, $name, $email, $password);
        return $this->findByEmail($email) ?? throw new RuntimeException('No se pudo recuperar la cuenta creada.');
    }

    private function roleId(string $role, bool $lock = false): int
    {
        $sql = 'SELECT id FROM roles WHERE rol = :role' . ($lock ? ' FOR UPDATE' : '');
        $statement = getDatabaseConnection()->prepare($sql);
        $statement->execute(['role' => $role]);
        $roleId = $statement->fetchColumn();
        if ($roleId === false) {
            throw new RuntimeException('El rol solicitado no está configurado.');
        }
        return (int) $roleId;
    }

    private function insertUser(int $roleId, string $name, string $email, string $password): int
    {
        $statement = getDatabaseConnection()->prepare(
            'INSERT INTO usuario (nombre, email, id_rol, password)
             VALUES (:nombre, :email, :id_rol, :password)'
        );
        $statement->execute([
            'nombre' => $name,
            'email' => $email,
            'id_rol' => $roleId,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        return (int) getDatabaseConnection()->lastInsertId();
    }
}
