# Reglas de negocio — referencia para programadores

Este documento describe **todas las reglas que el sistema aplica automáticamente**,
dónde vive cada una en el código y cómo cambiarla. Si vas a modificar el
comportamiento del sistema, empieza aquí en lugar de leer los controladores completos.

> Convención: `archivo :: método/constante`. Las rutas son relativas a la raíz del proyecto.

---

## 1. Marcado en el kiosco

Flujo completo en `app/Http/Controllers/KioskController.php`.

### 1.1 Reconocimiento facial
- El navegador (tablet) descarga los descriptores de TODOS los empleados activos con
  rostro enrolado (`KioskController::descriptors`) y hace el matching localmente con
  face-api.js. El servidor nunca recibe la imagen de la cámara en el flujo facial.
- **Umbral de similitud**: distancia euclidiana máxima `0.55`
  (`KioskController::THRESHOLD`). Menor = más estricto. Si el mejor match supera el
  umbral, el kiosco ofrece el marcado por DNI.
- La lista de descriptores se refresca sola: el kiosco consulta `/kiosk/version`
  cada 5 minutos (huella md5 de cantidad + último `updated_at`) y solo re-descarga
  si cambió (`public/js/kiosk.js`).

### 1.2 Marcado por DNI (respaldo)
- Si la cámara no reconoce el rostro, el empleado teclea su documento
  (`KioskController::markByDni`). Se guarda una **foto de evidencia** del momento
  en `public/uploads/kiosk_evidence/` y la marca queda con método `DNI` para que
  un supervisor la verifique en Asistencias (badge amarillo + foto).
- Las evidencias se purgan a los 90 días (`kiosk:purge-evidence`, ver §6).

### 1.3 Reglas comunes a toda marca (`KioskController::performMark`)
Se evalúan en este orden:

1. **Feriado** (`Holiday::onDate`) → marca rechazada.
2. **Vacaciones aprobadas** que cubren hoy (`Employee::onVacation`) → rechazada.
3. **Turno nocturno abierto**: si el horario de AYER cruza medianoche
   (`ScheduleDay::crossesMidnight`), el registro de ayer sigue sin salida y la marca
   ocurre **antes de las 12:00**, la marca cierra el turno de ayer como SALIDA
   (no abre un registro nuevo). Regla completa en §3.
4. **Primera marca del día = ENTRADA**. Estado:
   - Se busca el horario del día de la semana actual (`Schedule::worksOn(weekday)`).
   - `TARDANZA` si hora actual > hora de inicio + `tolerance_minutes` del horario.
   - `PUNTUAL` en caso contrario (también si hoy no es día laborable para él).
   - **Ventana de marcado anticipado** (`settings.early_check_in_minutes`): si es
     > 0, se rechaza la marca hecha más de X minutos antes de la hora de inicio
     (mensaje: "entra a las 08:00, puede marcar desde las 07:00"). **0 = sin
     restricción** (marca a cualquier hora). Configurable en Ajustes.
5. **Segunda marca = SALIDA**, con dos reglas:
   - **Regla dura**: deben pasar al menos **30 minutos** desde la entrada
     (`KioskController::MIN_MINUTES_BEFORE_CHECKOUT`) para evitar dobles marcas.
   - **Aviso de salida anticipada** (`settings.early_departure_minutes`): si es
     > 0 y la salida ocurre más de X minutos antes de la hora de fin, la marca se
     guarda igual pero con una observación automática ("Salida anticipada (N min
     antes del fin de turno)") para el supervisor. **Nunca bloquea la salida**;
     **0 = desactivado** (`KioskController::earlyDepartureNote`). Se registra
     siempre la **hora real** de salida.
6. **Tercera marca en adelante** → rechazada ("ya registró entrada y salida hoy").

Cada marca guarda auditoría del dispositivo: IP y user-agent (`attendances.ip`,
`attendances.user_agent`).

### 1.4 Seguridad del kiosco
Dos capas (`app/Http/Middleware/VerifyKioskToken.php`):
- **Vinculación de dispositivo** (más fuerte): un admin genera un **código de un
  solo uso** en Ajustes (`SettingController::generatePairCode`, vence en 15 min).
  En la tablet se abre `/kiosk/pair` y se ingresa; el servidor guarda el hash de
  un secreto de dispositivo (`settings.kiosk_device_hash`) y entrega una **cookie
  de 10 años** (`KioskController::pair`). Desde entonces solo esa tablet (que
  lleva la cookie) abre el kiosco; una URL copiada en otro dispositivo → 403.
  "Desvincular" (`unpairDevice`) limpia el hash para volver a emparejar.
- **Token de dispositivo** (respaldo, cuando NO hay dispositivo vinculado): la URL
  `/kiosk?token=...` (configurado en Ajustes) queda en sesión tras el primer
  acceso. Sin token válido → 403. Si no hay ni dispositivo ni token, el kiosco
  queda abierto (se avisa al admin en Ajustes).

### 1.5 Multi-sede (sedes)
- Cada empleado puede pertenecer a una **sede** (`employees.site_id`). Las sedes se
  administran en el módulo `settings` (`SiteController`).
- El enlace de kiosco de una sede es `/kiosk?token=<global>&site=<id>`. Al entrar,
  la sede queda en sesión (`kiosk_site`) y el kiosco **solo reconoce y marca** a
  los empleados de esa sede (`KioskController::scopedEmployees`, aplica a
  descriptores faciales y marcado por DNI). Sin `site`, el kiosco ve a todos.
- Empleados y reportes se pueden filtrar por sede.
- El **auto-enrolamiento** desde el kiosco está protegido por PIN
  (`settings.kiosk_enroll_pin`); el PIN desbloquea el modo por 15 minutos
  (`KioskController::ENROLL_SESSION_MINUTES`) y exige aceptar el consentimiento
  de datos biométricos antes de guardar (Ley 29733).

---

## 2. Horarios semanales

Modelo en `app/Models/Schedule.php` + `app/Models/ScheduleDay.php`.

- Un horario tiene filas por día de semana (`schedule_days.weekday`, 0=domingo…6=sábado)
  con `start_time` y `end_time`. **Si un día no tiene fila, es día de descanso**.
- `end_time < start_time` significa **turno nocturno** (cruza medianoche), p. ej.
  22:00–06:00 (`ScheduleDay::crossesMidnight`).
- `tolerance_minutes` vive en el horario (no por día) y aplica a la entrada.
- Todo empleado debe tener horario asignado (validación en `EmployeeController`):
  sin horario no se puede calcular tardanza ni falta.

---

## 3. Turnos nocturnos (regla de cierre)

Un empleado del turno 22:00–06:00 marca entrada el jueves 22:05 y salida el viernes
06:02. Para que ambas queden en el MISMO registro (el del jueves):

- Si el horario de ayer cruza medianoche, ayer quedó una entrada sin salida, y la
  marca de hoy es **antes de las 12:00** → esa marca es la SALIDA de ayer.
- El corte de las 12:00 es arbitrario pero seguro: nadie sale de un turno nocturno
  pasado el mediodía, y nadie del turno nocturno entra antes del mediodía.
- Test de referencia: `tests/Feature/SchedulesAndBalanceTest::test_overnight_shift_checkout_closes_previous_day`.

---

## 4. Faltas automáticas

`app/Models/Attendance.php :: markAbsences($date)` — usado por dos vías:

- **Automática**: comando `attendances:mark-absences` programado **todos los días a
  las 23:50 hora de la empresa** (`routes/console.php`). ⚠️ Requiere el scheduler
  de Laravel corriendo (`php artisan schedule:work` en desarrollo, cron
  `* * * * * php artisan schedule:run` en producción — ver docs/CONFIGURACION.md).
- **Manual**: botón "Generar faltas" en Asistencias (elige la fecha), útil si el
  scheduler no corrió.

Marca `FALTA` a todo empleado **activo** que ese día tenía horario laborable y no
tiene ningún registro, **saltando**: feriados, días de descanso (sin fila de
horario), vacaciones aprobadas que cubren la fecha, y días ya `JUSTIFICADO`.
Es idempotente: correrlo dos veces no duplica.

---

## 5. Estados de asistencia

`attendances.status`: `ON_TIME` (puntual), `LATE` (tardanza), `ABSENT` (falta),
`EXCUSED` (justificado). `attendances.method`: `FACIAL`, `DNI`, `MANUAL`.

- `EXCUSED` lo pone la aprobación de una justificación
  (`JustificationController::changeStatus` → crea/actualiza la asistencia del día).
- Las ediciones manuales (Asistencias → editar) cambian `method` a `MANUAL` y quedan
  registradas en Auditoría con el antes/después.
- Los **minutos de tardanza** de los reportes se calculan contra la hora de inicio
  del día de semana correspondiente (`ReportController::buildRows`), no se almacenan.

---

## 6. Tareas programadas

Definidas en `routes/console.php`, todas en hora de la empresa:

| Comando | Cuándo | Qué hace |
|---|---|---|
| `attendances:mark-absences` | Diario 23:50 | Faltas automáticas (§4) |
| `system:backup` | Diario 02:00 | ZIP de BD + uploads en `storage/app/backups`, conserva 14 |
| `kiosk:purge-evidence --days=90` | Domingos 03:00 | Borra fotos de evidencia DNI antiguas |

---

## 7. Vacaciones

`app/Http/Controllers/VacationController.php` + `app/Models/Employee.php`.

- Cada empleado tiene `vacation_days_per_year` (por defecto 30, editable).
- **Saldo** = asignación − días de solicitudes `APPROVED` cuyo `start_date` cae en
  el año (`Employee::remainingVacationDays`).
- El saldo se valida **dos veces**: al solicitar (`store`) y de nuevo **al aprobar**
  (`changeStatus`), porque dos solicitudes pendientes pueden competir por los
  mismos días. Test: `test_approval_rechecks_the_balance`.
- Días de la solicitud = diferencia inclusiva entre fechas (sin descontar feriados).
- Un no-gestor solo puede solicitar para su propio empleado vinculado.
- Aprobación/rechazo notifica por correo al empleado y a los aprobadores
  (módulo `vacations_manage`) por correo/Telegram al crearse (§10).

---

## 8. Permisos (perfiles y módulos)

`app/Models/Profile.php :: MODULES` define los 11 módulos. Un perfil guarda un array
JSON de módulos habilitados (`profiles.permissions`).

- `User::hasModule('x')` → el middleware `module:x` (`CheckModule`) protege cada
  grupo de rutas (`routes/web.php`) y el sidebar solo pinta los módulos permitidos
  (`layouts/app.blade.php`). **Ocultar del menú y bloquear la URL van juntos.**
- `User::isManager()` = tiene alguno de: employees, attendances, reports,
  vacations_manage, justifications_manage. Los gestores ven datos de toda la
  empresa (dashboard global, calendario de cualquiera, etc.).
- La vista de **eliminados** y el botón **restaurar** exigen el módulo `settings`
  (en la práctica: administradores).

---

## 9. Borrado lógico (soft delete) con motivo

Aplica a **empleados, usuarios y justificaciones** (migración
`2026_01_05_000001`). Catálogos (horarios, feriados, perfiles, áreas) se borran de
verdad.

- Todo borrado exige **motivo** (el diálogo global lo pide:
  `layouts/app.blade.php`, handler `.delete-form`) y queda en `delete_reason` + en
  el log de auditoría.
- Los registros borrados desaparecen de listas, kiosco, faltas automáticas y
  login (scope global de `SoftDeletes`), pero su historial (asistencias, etc.) se
  conserva y las relaciones de historial usan `withTrashed()` para seguir
  mostrando el nombre (p. ej. `Attendance::employee`).
- **Unicidad**: `document_number` y `email` ya no son únicos a nivel de BD; la
  validación exige unicidad **solo entre registros vivos**
  (`Rule::unique(...)->withoutTrashed()`). Esto permite recontratar un DNI cuyo
  registro anterior fue eliminado. Si restauras un registro cuyo documento/correo
  fue re-registrado después, tendrás dos vivos iguales: restaurar avisado.
- Un usuario eliminado **no puede iniciar sesión** (el lookup por email lo excluye).

---

## 10. Notificaciones

`app/Support/helpers.php`:

- `safe_mail($to, $subject, $body)` — envía y **nunca lanza excepción** (si el SMTP
  no está configurado, lo loguea y sigue). Por eso crear usuarios funciona sin correo.
- `notify_module_users($module, $subject, $body)` — correo a todos los usuarios
  activos cuyo perfil tenga ese módulo (se usa al crear vacaciones/justificaciones).
- `notify_telegram($text)` — no-op si `TELEGRAM_BOT_TOKEN`/`TELEGRAM_CHAT_ID` no
  están en `.env`.
- Correos que envía el sistema: credenciales al crear usuario (con enlace de
  acceso), resultado de vacaciones/justificaciones, aviso a aprobadores,
  recuperación de contraseña.

---

## 11. Zonas horarias (regla de oro)

- `APP_TIMEZONE=UTC` — **nunca cambiarlo**. La BD guarda UTC.
- `settings.timezone` = zona operativa de la empresa (`company_now()`,
  `company_timezone()`): TODA regla de negocio (tardanza, faltas, feriados,
  cortes) corre en esta zona.
- `users.timezone` = solo para **mostrar** fechas a ese usuario (`to_user_tz()`).
- `attendances.date/check_in/check_out` se guardan como fecha/hora **de la empresa**
  (wall-clock), no UTC — así los reportes leen directo.

---

## 12. Período de corte (estilo planilla)

`app/Support/helpers.php :: current_period()`.

- `settings.cutoff_day` (p. ej. 19) define el período: del día siguiente al corte
  del mes anterior hasta el día de corte del mes actual (20 jun → 19 jul).
- Si es null, el período es el mes calendario.
- Lo usan por defecto Asistencias y Reportes (el filtro manual lo puede sobrescribir).

---

## 13. Validación de documentos y RENIEC

`app/Http/Controllers/EmployeeController.php :: validated()`:

| Tipo | Regla |
|---|---|
| DNI | exactamente 8 dígitos |
| CE | alfanumérico 9–12 |
| PASSPORT | alfanumérico 6–12 |

- Todo se normaliza a mayúsculas sin espacios antes de validar.
- Con tipo DNI, al completar 8 dígitos el formulario consulta RENIEC vía Decolecta
  (`DniLookupController`, token en `.env`, caché de 1 día, sin botón — automático
  y sin repetir consultas).
- El import de Excel clasifica: 8 dígitos = DNI, otro = CE
  (`EmployeeImportController`).

---

## 14. Otras reglas rápidas

- Un usuario **no puede eliminarse ni desactivarse a sí mismo** (`UserController`).
- Crear usuario desde Empleados ("Habilitar acceso"): contraseña inicial = número
  de documento, `must_change_password = true` (se le obliga a cambiarla al entrar),
  perfil por defecto **Employee**.
- El avatar se recorta al centro y se reduce a JPEG 256×256 al subirlo.
- Todos los inputs se auto-recortan (espacios al inicio/fin) al perder foco y al
  enviar (`public/js/trim-inputs.js`); los textarea conservan sus saltos de línea.
- Listas ilimitadas (asistencias, auditoría, empleados, usuarios, vacaciones,
  justificaciones) usan paginación de servidor; los selectores de empleado usan
  autocompletado AJAX (`/lookup/employees`, solo gestores, 20 por página).
- El calendario carga una ventana móvil de 12 meses.

---

*Si cambias una regla, actualiza este documento y el test correspondiente en
`tests/Feature/`. La suite completa corre con `php artisan test`.*
