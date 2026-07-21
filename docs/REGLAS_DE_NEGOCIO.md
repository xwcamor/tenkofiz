# Reglas de negocio — referencia para programadores

Este documento describe **todas las reglas que el sistema aplica automáticamente**,
dónde vive cada una en el código y cómo cambiarla. Si vas a modificar el
comportamiento del sistema, empieza aquí en lugar de leer los controladores completos.

> Convención: `archivo :: método/constante`. Las rutas son relativas a la raíz del proyecto.

---

## 1. Marcado en el kiosco

Flujo completo en `app/Http/Controllers/KioskController.php`.

### 1.1 Flujo por páginas (documento primero, luego cámara)
El kiosco funciona en **páginas separadas** (nada de modales encima de la cámara):

1. **`/kiosk` (teclado)**: solo el reloj y el teclado numérico. La persona digita su
   documento y `POST /kiosk/lookup` lo valida contra los empleados activos **de esa
   sede**. Documento inválido → mensaje, sin cámara. Válido → la persona queda en
   sesión 3 minutos (`kiosk_verify_doc`) y pasa a la página de cámara.
2. **`/kiosk/verify` (cámara)**: muestra el nombre de la persona y abre la cámara.
   - **Con rostro enrolado**: verificación 1:1 durante `kiosk_verify_seconds`
     segundos (5–60, por defecto **10**) con conteo grande sobre el video y chip
     de "rostro detectado". Identidad confirmada (+ reto de vida si está activo,
     §1.2c) → marca facial y vuelve al teclado en 4 s.
   - **Se agotó el tiempo → fase de evidencia AUTOMÁTICA** (8 s): la cámara busca
     **cualquier** rostro; al primero que aparece marca por documento con foto de
     evidencia (aunque sea la foto de un tramposo: la evidencia lo delata). Si en
     8 s no aparece ningún rostro, **no se registra nada** y vuelve al teclado.
     El mensaje en pantalla es deliberadamente **neutro** ("Reintentando
     detección...") para no avisarle al tramposo el momento de esconderse.
   - **Sin rostro enrolado**: el ÚNICO camino es **enrolarse ahí mismo**
     (consentimiento + 3 muestras; con PIN de supervisor si está configurado) y
     marcar de inmediato. No hay marcado por documento para no enrolados (§1.2).
   - **Sin cámara disponible**: un enrolado puede marcar por documento (sin foto);
     un no enrolado no puede marcar (ver §1.2).
3. **`/kiosk/enroll` (supervisor)**: página propia de enrolamiento (PIN → documento
   → consentimiento → captura), con la cámara siempre visible arriba.

- **Calibración core (solo super-admin, §14)**: el umbral de similitud
  (`kiosk_face_threshold`, 0.35–0.65; 0.50 recomendado; menor = más estricto) y la
  ventana de verificación (`kiosk_verify_seconds`) se editan ÚNICAMENTE desde la
  consola de Workspaces (botón "Calibración del reconocimiento"). El administrador
  de la empresa **no ve estos campos**: un umbral mal puesto deja pasar a
  cualquiera como cualquiera.
- **Reto de vida (`kiosk_liveness`)**: si está activo, tras confirmar la identidad
  se exige UN gesto aleatorio de cabeza (§1.2c). Este toggle sí es del admin de la
  empresa (es un balance fricción/seguridad de su negocio).
- El antiguo "modo rápido" 1:N fue retirado de la interfaz: el flujo es siempre
  documento → confirmación 1:1 (filtra antes de abrir la cámara). Los endpoints
  `/kiosk/descriptors` y `/kiosk/version` se conservan por compatibilidad.
- JS: `public/js/kiosk-home.js`, `kiosk-verify.js`, `kiosk-enroll.js`.

### 1.2 Regla: el marcado por documento es SOLO para rostros ya enrolados
Decisión de negocio (Carlos): el respaldo "documento + foto de evidencia" existe
únicamente para quien **ya tiene rostro enrolado** y el reconocimiento falló.
- **Sin rostro enrolado → no hay marcado por documento** (el botón ni aparece y el
  servidor lo rechaza con 422 en `KioskController::markByDni`). El único camino es
  **enrolarse ahí mismo** en `/kiosk/verify` y marcar facialmente.
- **Auto-enrolamiento abierto**: si NO hay PIN configurado en Ajustes, la persona
  validada en el teclado puede enrolarse a sí misma (el servidor verifica que el
  `employee_id` coincida con el documento validado en sesión — no puede enrolar a
  otro). Con PIN configurado, el supervisor desbloquea la tablet 15 minutos.
- **Cámara rota**: un enrolado puede marcar por documento (sin foto); un NO
  enrolado no puede marcar (necesita la cámara para enrolarse) — el supervisor
  registra la marca manualmente en Asistencias.

### 1.2b Regla FIJA: sin rostro no hay marca ni foto (sin interruptor)
Decisión de negocio (Carlos): el propósito del kiosco es registrar asistencia
**con evidencia**; una marca cuya "evidencia" es una foto del techo es peor que no
marcar. Por eso la antigua opción `kiosk_require_face` **fue eliminada** — el
comportamiento es fijo:
- En la fase de evidencia (8 s tras fallar la verificación), si la cámara **nunca
  detecta un rostro** (dedo en el lente, se fue), **no se registra nada** y el
  kiosco vuelve al teclado.
- Aplica también al botón manual de respaldo: antes de guardar la foto de
  evidencia se exige un rostro en cámara (5 s de gracia, `waitForAnyFace`).
- **Si se ve un rostro pero no coincide** con el enrolado, la marca sale por
  documento con foto de evidencia para revisión del supervisor. Una foto sostenida
  por un tramposo PASA a propósito: la evidencia lo expone (el sistema encarece y
  evidencia la trampa, no promete impedirla al 100 % sin hardware 3D/IR).

### 1.2c Reto de vida con gestos aleatorios (`kiosk_liveness`)
El antiguo parpadeo (EAR) fue reemplazado: fallaba con lentes y obligaba a pegarse
a la tablet (los ojos son pocos píxeles a distancia de fila). Ahora, tras
confirmar la identidad y ver la cara de frente unos instantes, el kiosco pide **un
gesto al azar** con orden grande sobre el video:
- **Gira la cabeza hacia un lado** (cualquier dirección, `'turn'`): pose por
  geometría de los 68 landmarks (posición de la punta de la nariz entre los extremos
  de la mandíbula). Es **agnóstico a la dirección** a propósito: algunas tablets
  espejan la cámara y otras no, lo que invertiría un "izquierda/derecha" fijo y
  confunde a la gente; "gira a un lado" funciona igual sin importar el espejo. Una
  cabeza real 3D gira con perspectiva ASIMÉTRICA (`yaw` cruza `YAW_TURN` por
  cualquier lado); una foto plana girada solo se comprime uniformemente y no dispara.
- **Asiente (arriba/abajo)**: proporción vertical ojos→nariz vs nariz→mentón contra
  la línea base propia de la persona; inclinar o mover una foto escala ambas por
  igual y no dispara.
- El gesto debe realizarse en ~3.5 s tras la orden; si no, se sortea OTRO gesto
  distinto (nueva oportunidad para la persona real, ruido extra contra un video en
  loop, que no puede saber qué gesto ni cuándo se pedirá).
- Sin modelos adicionales (ni Python, ni MediaPipe): todo con los landmarks que ya
  están cargados. Diagnóstico en vivo con `/kiosk/verify?debug=1` (yaw, pitch,
  reto activo, identidad).
- Si el reto no se completa dentro de la ventana → fase de evidencia (§1.1).
- Se descartó el reto de "mostrar dedos/mano": la mano no está amarrada a la cara
  (foto de Juan + la mano del tramposo pasaría). Los gestos de cara los debe hacer
  la MISMA cara que se verifica.

**Espejo de la vista**: el video y el canvas del óvalo se muestran espejados
(selfie natural, `transform: scaleX(-1)` en `kiosk/partials/style`) de forma
consistente en TODA tablet — así se ve parejo aunque el hardware no espeje. Las
capas de texto (orden del reto, conteo) son divs encima, no se voltean, y quedan
legibles. La detección lee los píxeles crudos de la cámara, así que el espejo de la
vista no afecta el reconocimiento ni el reto.

**Pantalla de cámara limpia (fondo blanco + círculo)**: la página de verificación
(`<body class="kiosk-cam">`) usa un fondo blanco sin distracciones y la cámara
**recortada a un círculo** (RENIEC/banco), no el rectángulo con todo el cuarto
detrás. El video y el canvas comparten caja y `object-fit: cover`, así el óvalo guía
sigue alineado con el rostro dentro del círculo; el borde del círculo se pone verde
cuando el encuadre es bueno. El teclado y las demás páginas del kiosko mantienen el
tema oscuro.

### 1.2d Óvalo guía de encuadre (tipo RENIEC)
Sobre el video se dibuja un **óvalo punteado** (canvas `#overlay`): blanco mientras
no hay rostro o está mal encuadrado, **verde** cuando está centrado y a buen tamaño
(`faceWellPlaced`, calculado respecto al círculo visible con `object-fit: cover`).
En la página de cámara el **encuadre SÍ es obligatorio**: hasta que la cara está
bien dentro del círculo no se confirma identidad ni se corre el gesto (el
reconocimiento lee el cuadro completo, así que sin este chequeo una cara a medio
salir del círculo igual validaría — eso es lo que se evita). Los textos
(instrucción del gesto y conteo) van **debajo del círculo**, sobre el fondo blanco,
para que se lean; ya no hay texto encima del video. Aparece en verificación, en la fase de evidencia, en el auto-enrolamiento
(`/kiosk/verify`) y en el enrolamiento de supervisor (`/kiosk/enroll`, con un bucle
guía continuo). En el enrolamiento importa doble: muestras bien centradas producen
una plantilla facial mejor para siempre.

### 1.3 Marcado por DNI (respaldo) y política de fotos
- **Marca FACIAL → NO guarda foto** (decisión de negocio para ahorrar disco): la
  distancia de coincidencia registrada + el gesto de vida completado ya son prueba
  suficiente; una instantánea por cada marca exitosa solo acumularía bytes. El
  cliente ni siquiera la envía y `KioskController::mark` no la acepta.
- **Marca DNI (respaldo) → SÍ guarda foto de evidencia**: cuando corresponde (ver
  §1.2), la marca sale por `KioskController::markByDni` con una foto en
  `public/uploads/kiosk_evidence/` y método `DNI`, para que un supervisor la
  verifique en Asistencias (badge amarillo + foto). La foto existe **solo cuando
  hay algo que revisar**.
- Las evidencias se purgan a los 90 días (`kiosk:purge-evidence`, ver §6).
- **Enrolamiento sin tapar la cámara**: tanto el auto-enrolamiento en `/kiosk/verify`
  como el modo supervisor en `/kiosk/enroll` son páginas con la cámara fija arriba y
  los pasos debajo — no hay modales encima del video.

### 1.4 Reglas comunes a toda marca (`KioskController::performMark`)
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
   - **Mínimo entre entrada y salida** (`settings.min_checkout_minutes`, por defecto
     **30**): una segunda marca antes de ese lapso se ignora (sería un duplicado).
     Configurable por empresa — bajarlo (ej. 5, o 0) permite **salidas reales
     anticipadas** (emergencias, marca temprana por error) sin bloquear a la persona.
     El default `KioskController::MIN_MINUTES_BEFORE_CHECKOUT` es el respaldo.
   - **Aviso de salida anticipada** (`settings.early_departure_minutes`): si es
     > 0 y la salida ocurre más de X minutos antes de la hora de fin, la marca se
     guarda igual pero con una observación automática ("Salida anticipada (N min
     antes del fin de turno)") para el supervisor. **Nunca bloquea la salida**;
     **0 = desactivado** (`KioskController::earlyDepartureNote`). Se registra
     siempre la **hora real** de salida.
6. **Tercera marca en adelante** → rechazada ("ya registró entrada y salida hoy").

Cada marca guarda auditoría del dispositivo: IP y user-agent (`attendances.ip`,
`attendances.user_agent`).

**Pre-aviso en el teclado (fallar rápido)**: las tres razones de "no puedes marcar
ahora" — **feriado**, **vacaciones** y **demasiado temprano** (ventana anticipada,
solo aplica a la ENTRADA) — se revisan también en `POST /kiosk/lookup`, **antes**
de abrir la cámara. Si aplica, se rechaza en el teclado con el mensaje, sin hacerle
perder los segundos de reconocimiento. La validación autoritativa sigue en
`performMark` (nadie puede saltársela); son helpers compartidos
(`hardBlockMessage`, `earlyCheckInMessage`, `keypadPreCheck`). Para decidir si la
próxima marca es ENTRADA (y aplicar la ventana), el pre-aviso mira que no haya
registro hoy ni un turno nocturno abierto de ayer.

### 1.4c Tipo de horario: Fijo vs Flexible (`schedules.type`)
El tipo vive en el **horario** (no en la empresa), así un mismo workspace mezcla
ambos (p.ej. colegio: admins fijos + profesores flexibles).
- **Fijo** (`fixed`, por defecto): hora de entrada + tolerancia deciden PUNTUAL/
  TARDANZA; aplica la ventana de marcado anticipado; las horas se recortan al turno
  si `clamp_worked_hours` está activo. Es todo lo descrito arriba.
- **Flexible** (`flexible`): **sin hora de entrada fija → sin tardanza** (siempre
  PUNTUAL) y **sin ventana anticipada**. Solo importa cumplir una **meta de horas
  por día** (`schedules.target_minutes`). Las horas NO se recortan (no hay ventana
  de turno). Los `ScheduleDay` se guardan solo para saber QUÉ días trabaja (marcado
  de ausencias); sus horas 00:00 son marcador de posición. Cubre profesores,
  consultores y medio tiempo. `Schedule::isFlexible()/isFixed()`.
- **Pendiente/futuro**: bloques separados el mismo día (profesor con un curso y otro
  3 h después) y breaks tipo ZKTeco (entrada / salida a break / retorno / salida)
  requieren el modelo de **marcas múltiples** por día — otra fase.

### 1.4f Control de breaks (marcas múltiples, `settings.kiosk_breaks_enabled`)
Por workspace (Ajustes). **Apagado por defecto** → el flujo sigue idéntico (1
entrada + 1 salida). Encendido:
- Secuencia acotada: ENTRADA → (el kiosco **pregunta** "¿Salir a break o Marcar
  salida?" en la 2ª marca) → si break: SALIDA-BREAK, RETORNO, SALIDA. Máximo 4
  marcas (1 break). Nada de N marcas.
- `break_required`: la 2ª marca es siempre el break (sin preguntar).
- **Las horas del break se restan** de las trabajadas (`Attendance::workedMinutes`
  resta `breakMinutes`).
- `break_limit_minutes`: si el break supera el límite, el reporte/lista solo marca
  **"tiempo excedido (Nmin)"** — nunca penaliza, solo para análisis
  (`breakExceededMinutes`).
- **Guarda de salida anticipada**: si la próxima marca sería una SALIDA final y es
  claramente antes del fin (usa `early_departure_minutes`), el kiosco pide
  **confirmación** ("¿Seguro que es tu salida? No podrás volver a marcar hoy") antes
  de la cámara (`earlyExitWarning`).
- **Día abierto**: si hay ENTRADA pero nunca SALIDA en un día pasado, la lista lo
  marca en **rojo "Abierta"** (distinto de FALTA) → revisar o justificar
  (`Attendance::isOpen`).
- La secuencia real de cada punch queda en el log (`attendance_marks`, §1.4e):
  CHECK_IN / BREAK_OUT / BREAK_IN / CHECK_OUT.
- **Pendiente (fase aparte)**: doble turno el mismo día (mañana + noche) — cambia la
  regla de "cerrar el día" y roza dos horarios por persona.

### 1.4e Log de marcas (ZKTeco-style, `attendance_marks`)
Cada marca exitosa del kiosco se guarda además como una fila en `attendance_marks`
(empleado, `marked_at`, `kind`, `method`, ip/agente), enlazada a la asistencia del
día. Es **aditivo**: NO cambia cómo se calcula la asistencia (sigue el modelo
entrada/salida), solo preserva la **secuencia cruda de marcas** por empleado por
día — el fundamento para (futuro) detección de breaks y ya hoy la vista de logs.
En Asistencias, cada fila puede **expandir** ("Marcas del día") y mostrar cada punch
con hora, tipo y método (`Attendance::marks()`, `AttendanceMark`). El registro es
best-effort (un fallo al loguear el punch nunca bloquea la marca).

### 1.4g Geolocalización de la marca (`settings.kiosk_geolocation`)
Opcional, **por workspace, desactivado por defecto**. Cuando está activo, al marcar el
kiosco pide permiso de ubicación al navegador y guarda las coordenadas (`lat`, `lng`,
`accuracy` en metros) en cada punch de `attendance_marks`. El caso de uso: personal que
marca desde **otra sede** o trabaja **en campo** (el "tercero" que marca desde otro sitio).
Reglas:
- Es **best-effort y no bloqueante**: si la persona niega el permiso o el GPS falla, la
  marca se registra igual, solo sin ubicación (`hasLocation()` = false).
- El backend solo guarda coordenadas si el ajuste está activo **y** `lat`/`lng` son
  numéricos (`KioskController::recordMark`); si el ajuste está apagado, aunque el cliente
  envíe coordenadas, se ignoran.
- En Asistencias, cada punch con ubicación muestra un pin (📍) que abre Google Maps en las
  coordenadas, con la precisión en el tooltip.
- Aplica a marca facial y a marca por DNI por igual.

### 1.4d Reporte de cumplimiento: Esperadas vs Trabajadas vs Saldo
El reporte de horas (`ReportController::buildRows` y la ficha `sheet`) compara tres
cosas por empleado en el periodo:
- **Horas esperadas** (`Schedule::expectedMinutesFor(weekday)`): la "jornada" que
  debía — largo del turno en horario **fijo**, o la **meta diaria** en **flexible**.
  Se suma **solo en los días efectivamente trabajados** (con entrada y salida), para
  que un día corto salga como déficit y no se mezcle con las faltas.
- **Horas trabajadas**: las realmente cumplidas (recortadas al turno en fijo, §1.4b).
- **Saldo** = trabajadas − esperadas (negativo = llegó tarde / salió antes de forma
  habitual; positivo = trabajó de más).

**Horas esperadas congeladas**: al registrar la ENTRADA se guarda
`attendances.expected_minutes` (la jornada de ese día), igual que se congela el
estado. Así, cambiar el horario después **no reescribe** el saldo de días pasados
(los reportes usan el valor congelado; filas viejas sin snapshot caen al cálculo en
vivo). Con esto, ni la tardanza ni las horas esperadas del pasado se mueven al
reasignar un horario.

Punto clave a entender: **la tolerancia solo afecta la ETIQUETA** (PUNTUAL/TARDANZA),
no las horas. Las horas siempre se cuentan desde la marca real (por el recorte), así
un profesor que entra 4 min tarde sale PUNTUAL pero su Saldo baja 4 min/día — el
reporte lo delata. El control del tiempo se hace **a posteriori en Reportes** (no en
tiempo real en la fila): el supervisor observa puntualidad (tardanzas + minutos) y
cumplimiento (esperadas vs trabajadas + saldo).

### 1.4k Ordenamiento por columna (todos los listados)
Todas las tablas de listado (Empleados, Usuarios, Asistencias, Vacaciones,
Justificaciones, Reportes, Sedes, Horarios, Perfiles, Auditoría, Workspaces) tienen
**encabezados clicables** que ordenan en **servidor** vía `?sort=<clave>&dir=<asc|desc>`
(trait `Concerns\Sortable` + partial `partials/th-sort`). Es en servidor (no un
DataTables de cliente) para que ordene **todo el conjunto**, no solo la página visible
— las listas grandes siguen paginadas por escala. Las claves de orden están en una
**lista blanca** por controlador (columna propia o subconsulta correlacionada para
relaciones como el nombre del empleado o la sede), así el query string nunca inyecta
una columna arbitraria. Un clic alterna asc/desc y la flecha del encabezado lo refleja.

### 1.4h Reporte de análisis de breaks (`ReportController::breaks`)
Vista **solo de análisis** para RH/supervisor (módulo Reportes), aparece cuando
`kiosk_breaks_enabled` está activo. Responde "quién tardó cuánto en su break y quién
se pasó del límite" **sin desplegar detalle fila por fila**:
- **KPIs de cabecera**: breaks tomados, break promedio, días sobre el límite, tiempo
  total en exceso.
- **Resumen por empleado** (el "dashboard"): días con break, total, promedio, break
  más largo, días sobre el límite y tiempo en exceso; las filas con exceso se resaltan.
- **Detalle por día**: hora de inicio, hora de fin, duración y una bandera
  `Dentro del límite` / `Tiempo excedido (+N)`.
- **Filtros**: periodo, **sede** y empleado. Export a **Excel** (con autofiltro).

Regla de oro (§1.4f): **pasarse del límite nunca penaliza** — el tiempo de break se
descuenta de las horas trabajadas, pero el exceso solo se marca para revisión. El
límite se configura en Ajustes (`break_limit_minutes`; 0 = sin límite).

### 1.4i Columna y filtro por sede + autofiltro en Excel
- **Asistencias** y **Reportes** muestran la **columna Sede** y un **filtro por sede**
  (además del filtro por empleado/periodo). Un usuario atado a una sede
  (`isSiteBound`) solo ve su sede; los demás pueden elegir "Todas las sedes".
- El **dashboard** de gerencia trae un **selector de sede** que enfoca todos los KPIs
  y gráficos, y un **desglose por sede** (headcount y presentes de hoy por sucursal).
- Todos los **Excel** generados (resumen, detalle, breaks) traen **autofiltro**
  activado sobre la fila de encabezados para ordenar/filtrar dentro de Excel.

### 1.4j Datos de demostración (`demo:workforce`)
`php artisan demo:workforce` crea una plantilla realista para evaluar el sistema: 4
empleados en 2 sedes, **uno con horario flexible**, con breaks activados, y genera
asistencia de **mayo–junio** (con marcas crudas, breaks y ~15% de días que exceden el
límite, para que el análisis tenga qué mostrar). Idempotente. Acepta `--company`,
`--from`, `--to`. Reutiliza el generador día-a-día de `attendances:seed-demo`, que
ahora también congela `expected_minutes`/turno y crea el log de marcas por día.

### 1.4b Horas trabajadas: recorte al horario (`settings.clamp_worked_hours`)
Las horas de los reportes se calculan en `Attendance::workedMinutes(?$shift)`:
- **ACTIVADO (por defecto)**: las horas se **recortan al turno** — ventana pagada =
  `máx(entrada, inicio del horario)` → `mín(salida, fin del horario)`. Así, marcar
  antes (p.ej. 6am con turno 8am) o quedarse después **no infla** las horas. La
  **puntualidad se sigue midiendo con la marca real** (esto no cambia). Cierra el
  hueco del "marco temprano para hacer horas".
- **DESACTIVADO**: horas crudas = `salida − entrada`.
- Días sin turno (marca en día no laborable) y turnos nocturnos: se cuenta crudo
  (no hay ventana de horario contra la cual recortar). Configurable en Ajustes.
- Los **minutos tarde** se miden desde la hora de inicio del horario (no desde el
  fin de la tolerancia): 8:15 con tolerancia 10 → TARDANZA y **15 min** tarde.

### 1.5 Seguridad del kiosco (POR SEDE)
La seguridad es **por sede**: cada sede (tablet) tiene su propio token y su propio
dispositivo vinculado (`app/Http/Middleware/VerifyKioskToken.php`). El middleware
resuelve la sede así: `?site=<id>` → sesión `kiosk_site` → si la empresa tiene una
sola sede activa, esa. Dos capas sobre la sede resuelta:
- **Vinculación de dispositivo** (más fuerte): un admin genera un **código de un
  solo uso** para la sede en la pantalla **Sedes** (`SiteController::generatePairCode`,
  vence en 15 min). En la tablet se abre `/kiosk/pair` y se ingresa; el código
  identifica la sede, el servidor guarda el hash de un secreto (`sites.kiosk_device_hash`)
  y entrega una **cookie de 10 años** (`KioskController::pair`). Desde entonces solo
  esa tablet abre el kiosco de **esa** sede; una URL copiada en otro dispositivo → 403.
  "Desvincular" (`unpairDevice`) limpia el hash para volver a emparejar.
- **Token de sede** (respaldo, cuando NO hay dispositivo vinculado): el enlace
  autorizado `/kiosk?site=<id>&token=<token de la sede>` (`Site::kioskLink`) queda en
  sesión tras el primer acceso. Sin token válido → 403. Sin dispositivo ni token, el
  kiosco de esa sede queda abierto.
- Migración `2026_01_11_000003`: al actualizar, el token y el dispositivo globales
  anteriores se copian a la **primera sede** para no romper la tablet ya autorizada.

### 1.6 Multi-sede (sedes) y alcance por sede
- Cada empleado puede pertenecer a una **sede** (`employees.site_id`); cada usuario
  puede estar **atado a una sede** (`users.site_id`, ver §8.1). Las sedes se
  administran en el módulo `settings` (`SiteController`).
- El enlace de kiosco de una sede es `/kiosk?site=<id>&token=<token de la sede>`. Al
  entrar, la sede queda en sesión (`kiosk_site`) y el kiosco **solo reconoce y marca**
  a los empleados de esa sede (`KioskController::scopedEmployees`, aplica a
  descriptores faciales y marcado por DNI). Sin `site`, el kiosco ve a todos.
- Empleados y reportes se pueden filtrar por sede; los reportes muestran **sede y
  dirección** (pantalla, Excel y ficha imprimible).
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

### 8.1 Alcance por sede (`users.site_id`)
Un usuario puede estar **atado a una sede** (`users.site_id`). Regla de oro:
- `site_id = NULL` → **acceso a toda la empresa** (admin de empresa / sistema): ve
  todas las sedes. Los usuarios de prueba y el admin principal quedan en NULL.
- `site_id = X` → el usuario **solo ve la sede X**: empleados, asistencias, reportes,
  vacaciones y justificaciones de esa sede.

Implementación (mínima invasión, difícil de saltarse):
- `Employee` lleva un **global scope** `App\Models\Scopes\SiteScope`: si el usuario
  autenticado tiene sede, filtra `employees.site_id`. **Invitados (kiosco) y comandos
  de consola no se filtran** → el kiosco y las tareas programadas siguen operando en
  todas las sedes. Un empleado de otra sede da 404 por *route-model binding*.
- Asistencias, vacaciones y justificaciones (no tienen `site_id` propio) usan el
  trait `App\Models\Concerns\BelongsToScopedSite` con el scope local `inCurrentSite()`
  (filtra vía `whereHas('employee')`, incluyendo eliminados). Se aplica en las
  listas y en el dashboard de gestor.
- Al crear un empleado, un usuario atado a sede lo asigna **automáticamente a su
  sede** (`Employee::creating`). En el alta de usuarios, un admin atado a sede solo
  puede crear usuarios **dentro de su propia sede** (`UserController::resolveSiteId`;
  el selector de sede solo lo ve el admin de empresa).
- Tests: `SiteScopingTest`.

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

---

## 15. Términos y condiciones (deslinde legal)

- Todo usuario autenticado debe **aceptar los términos y condiciones** antes de usar
  el sistema (`EnsureTermsAccepted`): sin aceptación, cualquier pantalla redirige a
  `/terms` (solo se permite ver los términos, cambiar idioma y cerrar sesión).
- La aceptación queda **registrada como evidencia legal**: fecha/hora
  (`users.terms_accepted_at`), **IP** (`users.terms_ip`) y **versión** aceptada
  (`users.terms_version`), más una entrada en el log de auditoría.
- La versión vigente vive en `User::TERMS_VERSION`. **Subir la versión obliga a todos
  a re-aceptar** (la aceptación de una versión vieja no cuenta).
- El texto (es/en) cubre el deslinde: la **empresa** es la responsable del banco de
  datos (Ley 29733: consentimientos, derechos ARCO); la plataforma es solo la
  herramienta de tratamiento; sistema "tal cual", sin garantía de disponibilidad ni
  responsabilidad por decisiones laborales tomadas con los reportes; el
  reconocimiento facial es apoyo y las marcas en disputa se revisan con la evidencia.
- La exigencia se controla con `config('terms.enforced')` (`TERMS_ENFORCED`); la
  suite de tests la desactiva globalmente y `TermsAcceptanceTest` la prueba aparte.
