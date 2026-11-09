# Configuración de notificaciones de reservas

La aplicación lee la configuración desde variables de entorno del proceso de PHP/Apache. No guardes claves ni tokens en los archivos públicos del proyecto.

## Correo electrónico

- `MAIL_FROM`: dirección remitente válida.
- PHP debe tener configurado un servidor de correo para que `mail()` pueda entregar mensajes. En XAMPP esto requiere configurar el transporte SMTP/sendmail usado por PHP.
- `APP_BASE_URL` (opcional): URL pública del proyecto para incluir en los correos de calificación, por ejemplo `http://localhost/Programaci%C3%B3n%20II/Proyecto%20Final` en una instalación local.

Cuando el prestador marca una reserva como completada, la aplicación solicita al cliente que califique el servicio por correo. El aviso se envía una vez, al concretarse el cambio de estado de pendiente a completada.

## WhatsApp Business Cloud API

Configura estas variables en Apache antes de iniciar el servicio:

- `WHATSAPP_ACCESS_TOKEN`: token de acceso de la aplicación de Meta.
- `WHATSAPP_PHONE_NUMBER_ID`: identificador del número emisor registrado.
- `WHATSAPP_GRAPH_VERSION`: versión Graph habilitada para la aplicación, formato `vN.N`.
- `WHATSAPP_TEMPLATE_NAME`: nombre de una plantilla aprobada para notificar nuevas reservas.
- `WHATSAPP_TEMPLATE_LANGUAGE`: idioma de esa plantilla, por ejemplo `es_AR` si esa es la variante aprobada.

La plantilla debe tener tres parámetros de texto en el cuerpo, en este orden: nombre del cliente, servicio y fecha/hora. El teléfono del prestador es opcional y se carga desde su perfil en formato internacional con código de país, como `+...`. Si queda vacío, la aplicación no intenta enviar WhatsApp ni muestra el número al cliente.

## Mercado Pago (Checkout Pro)

Configura estas variables de entorno en Apache antes de iniciar XAMPP. No guardes credenciales en PHP ni las publiques en el repositorio.

- `MP_ACCESS_TOKEN`: Access Token privado de la aplicación de Mercado Pago.
- `MP_WEBHOOK_SECRET`: clave secreta de Webhooks para validar notificaciones firmadas.
- `MP_PUBLIC_BASE_URL`: URL pública HTTPS del proyecto, sin barra final; por ejemplo `https://tu-dominio.example`. No uses `localhost`.
- `MP_ENVIRONMENT`: usar `test` durante el desarrollo y `production` solamente al habilitar cobros reales. Si no se define, se usa `test`.

En Tus integraciones, configura la URL de Webhooks como `https://tu-dominio.example/webhook_mercadopago.php` y selecciona eventos de pagos. Para probar desde XAMPP local hace falta exponer temporalmente el servidor mediante una URL HTTPS pública. El cobro de esta versión se acredita en la cuenta vinculada al Access Token.

## Modo de demostración académica

`PAYMENT_DEMO_MODE` habilita el botón para simular pagos con débito, crédito o transferencia y descargar un PDF. El modo está habilitado por defecto para facilitar la presentación local y no envía solicitudes a Mercado Pago ni mueve dinero. Cada registro queda identificado como `simulacion` y el PDF aclara que es una demostración, no una transacción real ni un comprobante fiscal. Configura `PAYMENT_DEMO_MODE=0` en Apache antes de publicar el sitio.

Mientras `PAYMENT_DEMO_MODE=1`, la interfaz oculta Mercado Pago y el servidor rechaza intentos directos de iniciar un cobro real, incluso si hubiera un Access Token configurado.
