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
| `DECOLECTA_API_TOKEN` | Token de la API de Decolecta para el botón **RENIEC** del formulario de empleados (autocompleta nombres por DNI). Se obtiene en <https://decolecta.com> → panel → API keys. Sin token, el botón avisa que no está configurado; todo lo demás funciona | Opcional |

Tras cualquier cambio en `.env`: `php artisan config:clear`.

## 2. Dentro de la app (menú Ajustes, perfil Administrator)

- [ ] **Razón social, RUC, dirección, teléfono, logo** — salen en los PDF (fichas, vacaciones, justificaciones).
- [ ] **Zona horaria de la empresa** (ej. `America/Lima`) — manda sobre marcados del kiosco, tardanzas y generación de faltas.
- [ ] **Día de corte** (ej. 19 → periodos del 20 al 19) — Asistencias y Reportes se abren en ese periodo por defecto. Vacío = mes calendario.
- [ ] **Token del kiosco** — genera el token y abre el enlace autorizado UNA VEZ en la tablet; así nadie marca desde su celular. Guarda el enlace.
- [ ] **PIN de enrolamiento** (4-8 dígitos) — habilita el modo auto-enrolamiento en el kiosco (el empleado digita su DNI, acepta el consentimiento y captura su rostro).

## 3. Perfiles y usuarios

- [ ] Revisa los permisos de cada perfil (Perfiles → editar → checkboxes de módulos). Ejemplo: decide si *Supervisor* ve **Ajustes del sistema** o no.
- [ ] Cambia la contraseña del admin por defecto.
- [ ] Importa los empleados con la **plantilla Excel** (Empleados → Importar) y luego enrola rostros (desde el admin o con el modo enrolamiento del kiosco).

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

## 6. Kiosco (la tablet)

- [ ] Abrir el **enlace autorizado** (con token) una vez en la tablet.
- [ ] Servir la app por **HTTPS** (la cámara no funciona por HTTP salvo en `localhost`).
- [ ] Activar el modo kiosco del dispositivo (fijado de app en Android / Acceso guiado en iPad).
- [ ] Dar permiso de cámara al navegador y dejarlo recordado.
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
