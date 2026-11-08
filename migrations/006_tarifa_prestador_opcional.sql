-- La tarifa base se configura desde el perfil del prestador después del registro.
ALTER TABLE prestador
  MODIFY monto DECIMAL(10,2) NULL;
