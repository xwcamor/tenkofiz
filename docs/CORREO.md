# Configuración del correo (SMTP)

## Qué correos envía el sistema

| Evento | Destinatario |
|---|---|
| Recuperación de contraseña ("olvidé mi contraseña") | El usuario que la pidió |
| Creación de usuario para un empleado (credenciales iniciales) | El empleado |
| Nueva solicitud de vacaciones / justificación | Todos los usuarios con permiso de aprobar/revisar |
| Aprobación o rechazo de vacaciones / justificaciones | El empleado solicitante |

**Importante**: si el SMTP falla o no está configurado, la operación **no se interrumpe** —
el sistema guarda igual y deja una advertencia en `storage/logs/laravel.log`. O sea: sin correo
todo funciona, solo que nadie recibe avisos.

## Variables en `.env`

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tucorreo@gmail.com
MAIL_PASSWORD=xxxxxxxxxxxxxxxx      # contraseña de aplicación, NO tu contraseña normal
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="tucorreo@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Después de editar el `.env` ejecuta:

```bash
php artisan config:clear
```

## Ejemplo: Gmail

1. Activa la **verificación en 2 pasos** en tu cuenta Google.
2. Ve a <https://myaccount.google.com/apppasswords> y genera una **contraseña de aplicación**
   (16 caracteres). Esa va en `MAIL_PASSWORD`.
3. Usa `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_ENCRYPTION=tls`.

> Gmail limita ~500 correos/día. Para producción seria considera un proveedor transaccional
> (Brevo, Mailgun, SES, Resend…): mismo bloque de variables, cambian host/usuario/clave.

## Para pruebas sin enviar nada real

- **Mailtrap** (<https://mailtrap.io>): te da host/usuario/clave de un buzón de pruebas;
  todos los correos caen ahí y nadie real los recibe.
- **Log driver**: `MAIL_MAILER=log` — los correos se escriben en `storage/logs/laravel.log`
  en lugar de enviarse. Útil en desarrollo.

## Probar que funciona

```bash
php artisan tinker
>>> safe_mail('tu-correo-personal@gmail.com', 'Prueba', 'Hola desde el sistema');
```

Si no llega, revisa `storage/logs/laravel.log`: ahí aparece la razón exacta
(`Could not send email: ...`).
