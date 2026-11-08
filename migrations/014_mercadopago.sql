-- Ejecutar una sola vez sobre la base e_commerce existente.
-- Mantiene pagos.confirmacion por compatibilidad y guarda cada intento de checkout.
ALTER TABLE pagos
  ADD COLUMN estado VARCHAR(30) NOT NULL DEFAULT 'pendiente' AFTER confirmacion,
  ADD COLUMN proveedor VARCHAR(30) NOT NULL DEFAULT 'mercado_pago' AFTER estado,
  ADD COLUMN preferencia_id VARCHAR(100) NULL AFTER proveedor,
  ADD COLUMN pago_externo_id VARCHAR(100) NULL AFTER preferencia_id,
  ADD COLUMN metodo_pago VARCHAR(80) NULL AFTER pago_externo_id,
  ADD COLUMN monto DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER metodo_pago,
  ADD COLUMN moneda CHAR(3) NOT NULL DEFAULT 'ARS' AFTER monto,
  ADD COLUMN detalle_estado VARCHAR(120) NULL AFTER moneda,
  ADD COLUMN fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER detalle_estado,
  ADD INDEX idx_pagos_reserva_estado (id_reserva, estado),
  ADD UNIQUE INDEX uq_pagos_preferencia (preferencia_id),
  ADD UNIQUE INDEX uq_pagos_externo (pago_externo_id);
