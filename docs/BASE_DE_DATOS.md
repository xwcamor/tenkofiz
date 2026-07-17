# Base de datos: SQLite ↔ MySQL/MariaDB

## SQLite (por defecto)

La repo viene lista para SQLite: cero instalación, la BD es un archivo.

```env
DB_CONNECTION=sqlite
# Las demás variables DB_* pueden quedar comentadas
```

```bash
touch database/database.sqlite
php artisan migrate --seed
```

Perfecto para desarrollo y para instalaciones pequeñas (una sede, decenas de empleados).
**Respaldo** = copiar el archivo `database/database.sqlite`.

## Cambiar a MySQL / MariaDB (recomendado en producción)

1. Crea la base de datos (una sola vez):

```sql
CREATE DATABASE tenkofiz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'tenkofiz'@'localhost' IDENTIFIED BY 'UNA_CLAVE_SEGURA';
GRANT ALL PRIVILEGES ON tenkofiz.* TO 'tenkofiz'@'localhost';
FLUSH PRIVILEGES;
```

2. Edita el `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tenkofiz
DB_USERNAME=tenkofiz
DB_PASSWORD=UNA_CLAVE_SEGURA
```

3. Ejecuta:

```bash
php artisan config:clear
php artisan migrate --seed        # BD nueva
# o solo: php artisan migrate     # si ya tenías datos y solo hay migraciones nuevas
```

Requiere la extensión `pdo_mysql` habilitada en `php.ini` (en XAMPP ya viene activa).

> PostgreSQL también funciona (`DB_CONNECTION=pgsql` + extensión `pdo_pgsql`), mismas variables.

## Migrar datos de SQLite a MySQL

No hay conversión automática. El camino simple si aún estás empezando:
configura MySQL y vuelve a correr `migrate --seed` + la importación de empleados por Excel.
Si ya tienes historial valioso, expórtalo/impórtalo con una herramienta como
`sqlite3 .dump` + ajustes manuales, o pide apoyo antes de cambiar.

## Comandos útiles

```bash
php artisan migrate:status        # qué migraciones están aplicadas
php artisan migrate               # aplicar migraciones pendientes (NO borra datos)
php artisan migrate:fresh --seed  # ⚠️ BORRA TODO y recrea desde cero
php artisan db:seed --class=DemoSeeder   # datos de demo (agrega, no borra)
```

## Respaldos en producción (MySQL)

```bash
mysqldump -u tenkofiz -p tenkofiz > backup_$(date +%F).sql
```

Prográmalo en un cron diario y guarda copias fuera del servidor. Recuerda respaldar
también `public/uploads/` (logo, documentos de justificaciones, fotos de evidencia).
