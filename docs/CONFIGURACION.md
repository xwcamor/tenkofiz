# Checklist de configuración — todo lo que se olvida

Marca cada punto al montar una instalación nueva. Los detalles de cada tema están en su doc.

## 1. Archivo `.env` (secretos y entorno)

| Variable | Qué es | ¿Obligatoria? |
|---|---|---|
| `APP_KEY` | Clave de cifrado (la genera `php artisan key:generate`) | ✅ |
| `APP_URL` | URL pública real (ej. `https://asistencia.miempresa.com`) | ✅ en producción |
| `APP_ENV=production` + `APP_DEBUG=false` | Nunca dejes debug activo en producción | ✅ en producción |
| `APP_TIMEZONE=UTC` | **No la cambies.** El servidor trabaja en UTC; la zona horaria "real" de la empresa se configura DENTRO de la app (Ajustes) | ✅ |
| `DB_*` | Conexión a la base de datos → [BASE_DE_DATOS.md](BASE_DE_DATOS.md) | ✅ |
| `MAIL_*` | SMTP para los avisos → [CORREO.md](CORREO.md) | Recomendada |
| `TELEGRAM_BOT_TOKEN` + `TELEGRAM_CHAT_ID` | Alertas por Telegram al grupo de aprobadores cuando se registra una vacación/justificación. Crea el bot con @BotFather, agrégalo al grupo y obtén el chat id (ej. con @getidsbot). Sin configurar = no envía nada | Opcional |
| `DECOLECTA_API_TOKEN` | Token de la API de Decolecta para el botón **RENIEC** del formulario de empleados (autocompleta nombres por DNI). Se obtiene en <https://decolecta.com> → panel → API keys. Sin token, el botón avisa que no está configurado; todo lo demás funciona | Opcional |

Tras cualquier cambio en `.env`: `php artisan config:clear`.

### ⚠️ Windows (Laragon/XAMPP): certificados SSL de PHP

Si el `curl` de la terminal SÍ llega a la API de Decolecta pero el sistema muestra
*"No se pudo conectar con el servicio RENIEC"* o un error SSL, es que **PHP no tiene
configurado su paquete de certificados** (cURL error 60). Solución:

1. Descarga <https://curl.se/ca/cacert.pem> y guárdalo, por ejemplo, en `C:\laragon\etc\ssl\cacert.pem`
   (en Laragon ese archivo suele existir ya).
2. Edita tu `php.ini` (Laragon: clic derecho → PHP → php.ini) y configura ambas líneas:

   ```ini
   curl.cainfo="C:\laragon\etc\ssl\cacert.pem"
   openssl.cafile="C:\laragon\etc\ssl\cacert.pem"
   ```

3. Reinicia Apache/Laragon.

Esto también arregla el envío de correos por SMTP con TLS. **Nunca** desactives la
verificación SSL en el código como "solución".

## 2. Dentro de la app (menú Ajustes, perfil Administrator)

- [ ] **Razón social, RUC, dirección, teléfono, logo** — salen en los PDF (fichas, vacaciones, justificaciones).
- [ ] **Zona horaria de la empresa** (ej. `America/Lima`) — manda sobre marcados del kiosco, tardanzas y generación de faltas.
- [ ] **Día de corte** (ej. 19 → periodos del 20 al 19) — Asistencias y Reportes se abren en ese periodo por defecto. Vacío = mes calendario.
- [ ] **Seguridad del kiosco por sede** (en *Sedes*, no en Ajustes) — cada sede tiene su enlace, su token y sus **tablets vinculadas**:
  - *Token*: abre el enlace autorizado UNA VEZ en la tablet; así nadie marca desde su celular con solo copiar la URL.
  - *Vinculación de dispositivo (recomendado)*: genera un código de un solo uso y actívalo en la tablet. Desde ese momento **solo esa tablet** (que guarda una cookie) abre el kiosco de esa sede; una URL copiada en otro equipo se rechaza. Puedes vincular **varias tablets por sede** (una por área) y revocar cada una por separado.
- [ ] **Enrolamiento facial guiado (sin PIN)** — ya no hay PIN. Al digitar el DNI, si la persona no tiene rostro, acepta el consentimiento y la cámara la guía (círculo verde, acércate/aléjate) y captura sola en unos segundos; enseguida marca su asistencia.
- [ ] **Geolocalización (opcional)** — en *Ajustes → Kiosco* puedes registrar dónde se hizo cada marca. Con **"Exigir ubicación para marcar"** activado, la cámara no se abre sin ubicación y una marca sin coordenadas se rechaza (para empresas cuyos trabajadores marcan desde cualquier lugar con el enlace compartido).

## 3. Perfiles y usuarios

- [ ] Revisa los permisos de cada perfil (Perfiles → editar → checkboxes de módulos). Ejemplo: decide si *Supervisor* ve **Ajustes del sistema** o no.
- [ ] Cambia la contraseña del admin por defecto.
- [ ] Importa los empleados con la **plantilla Excel** (Empleados → Importar; incluye columna **Sede**) y luego enrola rostros (desde el admin o con el modo enrolamiento del kiosco). Con **Empleados → Exportar** bajas el padrón (con los filtros actuales) en Excel, con las mismas columnas del import.

## 4. Modelos de reconocimiento facial

- [ ] `bash download_models.sh` → deben existir 6 archivos en `public/models/`.
  Sin ellos el kiosco y el enrolamiento se quedan en "Cargando modelos...".

## 5. Tareas programadas (faltas automáticas)

El comando `attendances:mark-absences` marca FALTA a quien no registró asistencia,
todos los días a las **23:50 hora de la empresa** (salta feriados, domingos y vacaciones).
Necesita el planificador de Laravel corriendo:

```bash
# Desarrollo:
php artisan schedule:work

# Producción (crontab -e):
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

También puedes generarlas a mano: botón "Generar faltas" en Asistencias, o
`php artisan attendances:mark-absences 2026-07-15`.

Con el mismo planificador corren automáticamente:

- **`system:backup`** (diario 02:00): respalda BD + `public/uploads` en `storage/app/backups`
  (zip, conserva los últimos 14). SQLite se copia tal cual; MySQL usa `mysqldump` si está instalado.
- **`kiosk:purge-evidence --days=90`** (domingos 03:00): borra las fotos de evidencia de marcados
  por DNI con más de 90 días (minimización de datos); los registros de asistencia se conservan.

## 5b. Assets locales (recomendado)

Por defecto las librerías (AdminLTE, Chart.js, face-api, etc.) cargan desde CDNs. Para que la app
—especialmente la tablet del kiosco— funcione **sin internet**:

```bash
npm install --ignore-scripts
npm run vendor        # copia todo a public/vendor
```

Las vistas detectan automáticamente los archivos locales (`vendor_asset()`); si no existen, usan el CDN.
Repite `npm run vendor` después de cada `git pull` que cambie versiones.

## 6. Kiosco (la tablet)

- [ ] Abrir el **enlace autorizado** (con token) una vez en la tablet, o mejor **vincular la tablet** con un código (Sedes → esa sede). Si la sede tiene varias áreas, vincula una tablet por área.
- [ ] Servir la app por **HTTPS** (la cámara —y la geolocalización— no funcionan por HTTP salvo en `localhost`).
- [ ] Activar el modo kiosco del dispositivo (fijado de app en Android / Acceso guiado en iPad).
- [ ] Dar permiso de cámara (y de ubicación, si se exige) al navegador y dejarlo recordado.
- [ ] **Si se filtra el enlace/token:** mientras la sede tenga al menos una tablet vinculada, el enlace copiado en otro equipo **se rechaza** (falta la cookie del dispositivo). Por eso la vinculación es la protección recomendada frente a un token filtrado. Si solo usas token (sin vincular), un token filtrado sí permitiría abrir el kiosco: en ese caso, rota el token en Sedes.
- [ ] Los marcados por DNI guardan foto de evidencia en `public/uploads/kiosk_evidence/` — revisa el espacio en disco de vez en cuando.

## 7. Permisos de archivos (Linux)

```bash
chown -R www-data:www-data storage bootstrap/cache public/uploads
chmod -R 775 storage bootstrap/cache public/uploads
```

## 8. Optimización en producción

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

(Repite estos tres comandos después de cada despliegue; `php artisan optimize:clear` los limpia.)
