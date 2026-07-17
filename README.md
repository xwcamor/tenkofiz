# Sistema Web de Control de Asistencia mediante Reconocimiento Facial

Proyecto de titulación — IESTP "María Rosario Araoz Pinto"
Autor: Carlos Alberto Morales Larrañaga

## Stack
- **Laravel 11+** (PHP 8.2+)
- **MySQL / MariaDB**
- **face-api.js** (@vladmandic/face-api) — reconocimiento facial en el navegador
- **AdminLTE 3** (template de administración) con **DataTables** (tablas con buscador/paginación en español), **SweetAlert2** (confirmaciones) y **Chart.js** (gráficos) — todo vía CDN, sin instalación

## Módulos
| Módulo | Perfil con acceso |
|---|---|
| Login / Logout | Todos |
| Dashboard con indicadores del día | Todos los autenticados |
| CRUD de Usuarios | Administrador |
| CRUD de Perfiles | Administrador |
| CRUD de Horarios (con tolerancia de tardanza) | Administrador |
| CRUD de Empleados + Enrolamiento facial | Administrador, Supervisor |
| Kiosco de marcado facial (entrada/salida) | Pantalla pública |
| Asistencias (listado, filtros, registro manual) | Administrador, Supervisor |
| Mis asistencias | Empleado |
| Vacaciones (solicitud / aprobación) | Todos según perfil |
| Reportes: horas y días trabajados, tardanzas, faltas (exportable a Excel) | Administrador, Supervisor |
| CRUD de Feriados (bloquean el marcado en el kiosco) | Administrador |
| Catálogos de Áreas y Cargos con alta rápida desde el formulario | Administrador, Supervisor |

## Instalación (en tu PC)

```bash
# 1. Crear el proyecto Laravel base
composer create-project laravel/laravel sistema-asistencia
cd sistema-asistencia

# 2. Copiar ENCIMA los archivos de este paquete
#    (app/, database/, resources/, routes/, public/js/, bootstrap/app.php, descargar_modelos.sh)

# 3. Configurar la base de datos en .env
#    DB_CONNECTION=mysql
#    DB_DATABASE=asistencia_facial
#    DB_USERNAME=root
#    DB_PASSWORD=

# 4. Migrar y sembrar datos iniciales
php artisan migrate --seed

# 5. Descargar los modelos de reconocimiento facial
bash descargar_modelos.sh

# 6. Levantar el servidor
php artisan serve
```

## Actualización desde una versión anterior
Si ya tenías el sistema instalado: copia todos los archivos encima y ejecuta
`php artisan migrate:fresh --seed`
**ADVERTENCIA:** esto borra los datos de prueba (deberás volver a enrolar rostros). Es necesario porque la tabla `empleados` ahora usa claves foráneas hacia `areas` y `cargos`.

## Reglas de negocio implementadas
- Entrada/salida con intervalo mínimo configurable (anti doble marcado)
- Bloqueo de marcado en feriados y para empleados con vacaciones aprobadas
- Tardanza automática según tolerancia del horario
- Unicidad: DNI, correo, nombres de perfil/horario/área/cargo, fecha de feriado, un usuario por empleado
- Botones con bloqueo anti doble submit y páginas sin caché del navegador

## Precisión del reconocimiento
- Enrolamiento con **3 muestras** por persona (el matcher compara contra todas)
- Umbral de distancia euclidiana: **0.55** (configurable en `KioscoController::UMBRAL` y `public/js/kiosco.js`)
- Detector con `inputSize: 416` para mayor exactitud
- Si un empleado no es reconocido con frecuencia: re-enrolar con buena iluminación frontal

## Zona horaria
El proyecto incluye `config/app.php` con `America/Lima` por defecto (hora del Perú para marcas y tardanzas).

## Correo (Gmail) — recuperación de contraseña y notificaciones
En el archivo `.env` configura:
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tucorreo@gmail.com
MAIL_PASSWORD=xxxxxxxxxxxxxxxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tucorreo@gmail.com
MAIL_FROM_NAME="Sistema de Asistencia"
```
**IMPORTANTE:** `MAIL_PASSWORD` NO es tu contraseña de Gmail: es una **Contraseña de Aplicación** de 16 caracteres.
Se genera en https://myaccount.google.com/apppasswords (requiere tener verificación en 2 pasos activa).
Luego: `php artisan config:clear`.

El sistema envía correos al: recuperar contraseña, crear usuario de empleado (credenciales), aprobar/rechazar vacaciones y justificaciones. Si el SMTP falla, la operación continúa y el error queda en `storage/logs/laravel.log`.

## Faltas automáticas
- Manual: botón "Generar faltas" en el módulo Asistencias (elige la fecha).
- Comando: `php artisan asistencias:marcar-faltas [fecha]`
- Automático diario (23:50): dejar corriendo `php artisan schedule:work` (o configurar un cron en producción).
- Excluye: domingos, feriados, vacaciones aprobadas y días justificados.

## Datos de demostración (para la sustentación)
`php artisan db:seed --class=DemoSeeder`
Crea 8 empleados con ~1 mes de asistencias simuladas, vacaciones y una justificación pendiente.
Usuario demo con perfil Empleado: **empleado@demo.test / demo1234**

## Seguridad de cuentas
- Cambio de contraseña desde el sidebar ("Cambiar contraseña").
- Los usuarios creados desde Empleados nacen con contraseña = DNI y **cambio obligatorio en el primer ingreso**.
- Recuperación de contraseña por correo desde el login ("¿Olvidó su contraseña?").

## Credenciales iniciales (seeder)
- **Usuario:** admin@sistema.test
- **Contraseña:** admin123

## Flujo de uso
1. Crear horarios (Turno Mañana ya viene creado).
2. Registrar empleados y asignarles horario.
3. Enrolar el rostro de cada empleado (botón de cámara en la lista de empleados).
4. Abrir `/kiosco` en la pantalla de marcado: el empleado se para frente a la cámara y el sistema registra entrada o salida automáticamente, marcando TARDANZA si supera la tolerancia del horario.
5. Los empleados con usuario pueden ver sus asistencias y solicitar vacaciones; el Supervisor/Administrador las aprueba.

## Nota técnica (para el informe)
El reconocimiento se realiza con face-api.js: la cámara captura el rostro, la red neuronal genera un **descriptor de 128 dimensiones**, y el sistema lo compara contra los descriptores enrolados usando **distancia euclidiana** (umbral 0.5). No se almacenan fotografías, solo el vector matemático, lo que favorece la privacidad. La cámara requiere servir la app en `localhost` o HTTPS.
