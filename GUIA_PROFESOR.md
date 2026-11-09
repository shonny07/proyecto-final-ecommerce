# ServiClick: guía breve para la demo

## Iniciar el proyecto

1. Abrir el **XAMPP Control Panel** e iniciar **Apache** y **MySQL**.
2. La base que usa la aplicación se llama `e_commerce`. En una instalación limpia, crearla desde phpMyAdmin con cotejamiento `utf8mb4_unicode_ci`, seleccionarla e importar `schema.sql`.
3. Abrir en el navegador:

   `http://localhost/Programaci%C3%B3n%20II/Proyecto%20Final/index.php`

La configuración local de `config/database.php` usa por defecto `localhost`, base `e_commerce`, usuario `root` y contraseña vacía, como en una instalación estándar de XAMPP. Si esos datos cambian, configurar las variables `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` para Apache.

> En la computadora de desarrollo la base ya existe y tiene las migraciones aplicadas. No volver a ejecutar las migraciones sobre esa base: varias modifican tablas existentes. Para una instalación nueva, usar `schema.sql`.

## Crear las cuentas de prueba

- **Administrador:** en una base limpia, entrar una sola vez a `http://localhost/Programaci%C3%B3n%20II/Proyecto%20Final/setup_admin.php` y crear la cuenta. Después, iniciar sesión desde `login.php`; la configuración inicial se bloquea cuando ya existe un administrador. En la base de desarrollo ya hay uno.
- **Prestador:** desde el catálogo, elegir **Crear cuenta** y luego **Prestador**. Completar nombre, correo y contraseña de al menos 8 caracteres. Los correos de prueba pueden usar `@example.com`; el sistema valida el formato y que el dominio tenga DNS, pero eso no crea una casilla ni garantiza que se reciban mensajes.
- **Cliente:** registrarlo desde **Crear cuenta → Cliente** o desde la pantalla de inicio de sesión. Para reservar es necesario tener una cuenta e iniciar sesión.

Usar nombres y contraseñas exclusivos para la demo. No reutilizar contraseñas personales.

## Recorrido sugerido

1. Iniciar sesión como administrador y crear un rubro padre y al menos un subrubro en **Categorías / Rubros**.
2. Cerrar sesión y entrar como prestador. En **Mis servicios**, revisar el perfil de contacto (WhatsApp es opcional), configurar **Horario de atención** y, si hace falta, agregar un período no disponible.
3. Crear un servicio dentro de un rubro, indicar tarifa (**por visita**, **por hora** o **por turno**), precio y duración. Después cargar una imagen de tarjeta de presentación. Cada prestador puede tener una sola; al hacer clic en el banner se muestran sus servicios.
4. Cerrar sesión y entrar como cliente. Desde el catálogo, buscar y elegir el servicio, seleccionar uno de los turnos disponibles y enviar la reserva. La reserva aparece en **Mis reservas** y ocupa automáticamente ese intervalo para el prestador.
5. Abrir **Conversación** desde la reserva para ver la mensajería entre cliente y prestador.
6. En la reserva pendiente, elegir un medio de pago simulado (débito, crédito o transferencia) y pulsar **Simular pago y generar comprobante**. Descargar el PDF desde **Mis reservas**.
7. Cuando termina el horario reservado, el prestador puede marcar la reserva como completada desde **Reservas**. El cliente puede calificarla con estrellas desde **Mis reservas**.

## Pago y notificaciones en la demo

El modo académico está activo por defecto (`PAYMENT_DEMO_MODE=1`). No hacen falta cuenta ni credenciales de Mercado Pago y no se realiza ningún cobro. El PDF identifica el pago como **simulación académica** y aclara que no es una transacción real ni un comprobante fiscal.

La aplicación intenta enviar avisos por correo al crear una reserva y al pedir una calificación. Para recibirlos hacen falta `MAIL_FROM` y un servicio de correo configurado en XAMPP; con direcciones ficticias no llegarán. El aviso por WhatsApp es opcional y requiere credenciales de la API. Aunque esos avisos no estén configurados, se puede recorrer la reserva y las acciones desde los paneles.

## Verificación realizada

Se completó una prueba con una cuenta cliente temporal: reserva #4 de **Cardon Eventos**, el 09/11/2026 de 08:00 a 12:00, por $200.000 ARS. El turno dejó de aparecer como disponible, el pago quedó aprobado en modo de simulación académica y se descargó el comprobante PDF. La conversación de la reserva también abrió correctamente.

La reserva #4 y su pago simulado quedan en la base actual para la demo; ese turno ya está ocupado. La notificación por correo indicó que no está configurada y no se pudo enviar. Para probar la calificación, el prestador podrá marcar la reserva como completada una vez que haya pasado su horario; luego el cliente verá el formulario en **Mis reservas**.

Además, el catálogo y el registro de prestador cargaron, el sistema confirmó que el administrador inicial ya existe y los 41 archivos PHP pasaron la comprobación de sintaxis.
