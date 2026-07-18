# Instalación desde cero

> "Descargo la repo… ¿y ahora qué?" — Esta guía es exactamente eso, paso a paso.

## El stack (qué necesitas tener instalado)

| Componente | Versión | Para qué |
|---|---|---|
| **PHP** | 8.3 o superior | El lenguaje del backend (Laravel 13) |
| **Composer** | 2.x | Instala las dependencias de PHP |
| Extensiones PHP | `gd`, `zip`, `mbstring`, `xml`, `curl`, `sqlite3` (o `pdo_mysql`) | `gd` y `zip` son para la plantilla Excel; el resto es estándar de Laravel |
| **Base de datos** | SQLite (incluida) o MySQL/MariaDB | Ver [BASE_DE_DATOS.md](BASE_DE_DATOS.md) |
| **Node.js + npm** | 18+ (opcional) | Solo si usas `composer run setup` completo; la app funciona sin build de assets porque usa CDNs |
| Navegador moderno | Chrome/Edge/Firefox | El reconocimiento facial corre en el navegador (face-api.js) |

En Windows lo más simple es **XAMPP** (trae PHP y MySQL) o **Laragon**. Verifica las extensiones en `php.ini`: deben estar descomentadas las líneas `extension=gd` y `extension=zip`.

## Pasos

```bash
# 1. Clonar
git clone https://github.com/xwcamor/tenkofiz.git
cd tenkofiz

# 2. Dependencias PHP
composer install

# 3. Archivo de entorno
cp .env.example .env        # Windows: copy .env.example .env
php artisan key:generate

# 4. Base de datos (SQLite por defecto: cero configuración)
touch database/database.sqlite   # Windows: type nul > database\database.sqlite
php artisan migrate --seed

# (opcional) datos de demostración: 8 empleados con un mes de asistencias
php artisan db:seed --class=DemoSeeder

# 5. Modelos de reconocimiento facial (¡obligatorio para el kiosco!)
bash download_models.sh
# En Windows sin bash: descarga a mano los 6 archivos listados en el script
# desde https://github.com/vladmandic/face-api/tree/master/model
# y colócalos en public/models/

# 6. (Recomendado) Assets locales: la app funciona sin internet/CDNs
npm install --ignore-scripts
npm run vendor

# 7. Levantar el servidor
php artisan serve
# → http://127.0.0.1:8000
```

## Credenciales iniciales

| Usuario | Contraseña | Perfil |
|---|---|---|
| `admin@test.com` | `123456` | Administrator (acceso total) |
| `aprobador@test.com` | `123456` | Supervisor (aprueba vacaciones/justificaciones) |
| `empleado@test.com` | `123456` | Employee (autoservicio: ve sus marcas y pide vacaciones) |

⚠️ **Cambia estas contraseñas apenas entres** (Mi cuenta → Cambiar contraseña).
Para probar como empleado con marcas propias, enlaza `empleado@test.com` a un
empleado desde **Empleados → botón de enlace (🔗)**.

## Después de instalar

Sigue el **[checklist de configuración](CONFIGURACION.md)** — ahí está todo lo que se olvida:
correo, token de Decolecta, zona horaria, día de corte, seguridad del kiosco, cron de faltas, etc.

## Problemas comunes

| Error | Causa / solución |
|---|---|
| `Class "PhpOffice\PhpSpreadsheet\Spreadsheet" not found` | Falta `composer install` después de actualizar la repo |
| `ext-gd is missing` al hacer composer install | Descomenta `extension=gd` (y `zip`) en `php.ini` y reinicia |
| El kiosco se queda en "Cargando modelos..." | No están los archivos en `public/models/` (paso 5) |
| `could not find driver` al migrar | Falta la extensión `pdo_sqlite` o `pdo_mysql` en `php.ini` |
| La cámara no enciende | El navegador exige **HTTPS** (o `localhost`) para dar acceso a la cámara |
| Los correos no llegan | Ver [CORREO.md](CORREO.md) — sin configurar SMTP el sistema no se rompe, solo lo anota en el log |
| La validación de DNI o el correo fallan con error SSL (cURL error 60), pero `curl` en la terminal sí funciona | PHP no tiene certificados configurados: ver la sección **"Windows: certificados SSL de PHP"** en [CONFIGURACION.md](CONFIGURACION.md) (`curl.cainfo` + `openssl.cafile` en `php.ini`) |
| El import de Excel falla o la plantilla no descarga | Faltan las extensiones `gd` y `zip` en `php.ini`, o falta `composer install` (ver primera fila) |
