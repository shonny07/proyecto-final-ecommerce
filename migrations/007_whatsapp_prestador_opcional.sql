-- El teléfono de WhatsApp se configura opcionalmente desde el perfil del prestador.
ALTER TABLE prestador
  MODIFY contacto VARCHAR(255) NULL;
