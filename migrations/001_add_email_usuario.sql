-- Ejecutar una sola vez sobre la base e_commerce ya creada.
ALTER TABLE usuario
  ADD COLUMN email VARCHAR(254) NOT NULL UNIQUE AFTER nombre;
