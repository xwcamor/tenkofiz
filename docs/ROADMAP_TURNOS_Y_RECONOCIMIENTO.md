# Roadmap: turnos rotativos + reconocimiento facial

Estos dos temas tocan el **core de asistencia**. No se construyen a ciegas: aquí
está el plan y mi recomendación para ejecutar juntos y rápido.

---

## A) Turnos rotativos / múltiples franjas (#2)

Hoy: el horario es **una franja por día** y la asistencia es **una fila por
(empleado, fecha)** (`attendances` con único `employee_id + date`). Antes de
programar hay que elegir **cuál de los dos casos** necesitas, porque cambian el
alcance por completo:

### Caso 1 — "El turno **varía por fecha**" (rotación)
Ej.: esta semana mañana, la próxima tarde; o guardias rotativas.
- Cada día la persona trabaja **UNA** franja → **NO** rompe "una fila por día". ✅
- Modelo posible:
  - (a) **Patrón de rotación** en el horario (secuencia de franjas que gira cada N días/semanas), o
  - (b) **Asignación por fecha** (un mini-calendario que dice qué franja aplica a quién cada día).
- Esfuerzo: **medio**. Aditivo, con tests que prueben que el caso de una sola
  franja sigue idéntico.

### Caso 2 — "**Dos franjas el MISMO día**" (partido)
Ej.: colegio 08–12 y 14–18 con almuerzo largo; el profesor marca 4 veces.
- Necesita **varias filas por día** (entrada/salida por franja) → **rompe** el
  supuesto actual de `attendances`.
- Cambios: tabla `attendances` (quitar el único por fecha, o tabla de sesiones),
  `performMark` (elegir la franja por hora, abrir/cerrar por franja), reportes
  (esperadas = suma de franjas del día), horas, turno nocturno por franja.
- Esfuerzo: **alto** (estructural). Se puede hacer aditivo (una franja = caso
  particular de N) pero requiere una fase dedicada y datos de prueba nuevos.

> **Lo que necesito de ti:** ¿Caso 1, Caso 2, o ambos? En Perú, colegios/
> universidades suelen ser **Caso 2** (turno mañana y tarde el mismo día);
> seguridad/salud suele ser **Caso 1** (rotación). Mi recomendación: empezar por
> **Caso 1 (rotación por fecha, modelo b)** porque es más común, más barato y no
> arriesga el core; dejar Caso 2 como fase 2 cuando de verdad apuntes a colegios.

### Plan de ejecución (cuando elijas)
1. Migración + modelo (patrón o asignación por fecha).
2. `Schedule::shiftFor(fecha)` / `worksOn(weekday, hora)` que devuelva la franja
   correcta; una sola franja sigue devolviendo lo mismo (test de regresión).
3. `performMark`: elegir franja por hora de marca.
4. Reportes/horas: esperadas = franja(s) del día.
5. UI de horarios: definir franjas/rotación.
6. Datos demo + tests + docs.

---

## B) Reconocimiento facial, liveness y evidencia (tus dudas)

### B.1 "Puse una foto cualquiera y me dejó pasar" — **no es un bug, es el respaldo por documento**
El flujo es: **facial (con reto de vida) → si falla, respaldo por documento con
foto de evidencia**. El respaldo **a propósito NO verifica identidad**: solo
exige que haya *un* rostro en cámara y guarda una foto para que un supervisor la
revise. Por eso una foto impresa "pasa": el sistema no la valida, la **registra
como evidencia**. Es el equilibrio para no bloquear a la persona honesta que tuvo
un problema de cara/lentes/luz (tu propia filosofía: "acelerar, no multar").

**El hueco real:** cuando `kiosk_liveness` está ON, el respaldo por documento la
**esquiva** (una foto no puede hacer el gesto, pero igual pasa por documento).

### B.2 Mis recomendaciones (para que elijas)
- **Umbral `kiosk_face_threshold`: NO subir a 0.60.** 0.6 hace el facial **más
  permisivo** (acepta más parecidos y hasta fotos). Lo recomendable es **~0.5**
  (0.5–0.55). Si algo, para más seguridad se **baja** (0.45), no se sube. Tu
  problema de "la foto pasó" fue el respaldo, no el umbral.
- **Este sistema vs ZKTeco:** honesto — es reconocimiento por navegador
  (face-api, un vector de 128 valores), **no** hardware con infrarrojo/3D. Es muy
  bueno para el precio y el reto de vida (gesto) bloquea fotos **en la vía
  facial**. No es grado bancario; el respaldo por documento es su punto débil por
  diseño. Con lo que hiciste, está muy bien logrado.

### B.3 Opciones para el respaldo (elige una; yo recomiendo la 2)
1. **Liveness también en el respaldo:** si `kiosk_liveness` ON, el respaldo por
   documento exige el gesto → una foto ya no pasa. Contra: la persona honesta con
   problemas de cara igual tiene que hacer el gesto (roza tu "no penalizar").
2. **(Recomendada) Respaldo permisivo pero MARCADO para revisión:** la marca por
   documento queda etiquetada distinto y su **foto se muestra destacada** al
   supervisor (ya se guarda; falta resaltarla + un filtro "marcas por documento").
   No bloquea a nadie y deja rastro claro de quién marcó sin reconocimiento.
3. **Tu idea del botón (en vez de temporizador):** cuando la cámara **no** logra
   reconocer, mostrar un botón **"Tomar foto para marcar"** que **solo se habilita
   en verde** (rostro bien encuadrado). Así el respaldo es explícito y humano.
   - Sobre tu dilema "¿los segundos sirven para que aparezca el botón?": sí, esa
     es la mejor forma — el temporizador de reconocimiento corre; si vence sin
     match, **aparece el botón** (habilitado solo en verde). Une lo mejor de
     ambos: intenta reconocer X segundos y, si no, la persona confirma con un
     toque. **Muy buena idea, la recomiendo combinada con la opción 2.**

**Mi recomendación final:** Opción **2 + 3** juntas — el reconocimiento intenta
15s; si no logra, sale el botón "Tomar foto" (solo en verde), la marca se guarda
**etiquetada "por documento"** y su foto se resalta para el supervisor. Acelera,
no multa, y deja auditoría. La opción 1 (liveness obligatorio en respaldo) solo
para clientes que pidan mano dura.

> **Lo que necesito de ti:** ¿Opción 1, 2, 3, o 2+3? Con eso lo implemento.

---

## Estado (lo que YA quedó hecho de esta tanda)
- ✅ Perfiles base protegidos (`is_system`): no se borran/renombran/desactivan;
  Admin siempre conserva todos los módulos; se crean en cada workspace. Custom
  siguen libres.
- ✅ Reconocimiento a **15s** por defecto para todos (configurable por el super).
- ⏳ Turnos rotativos (#2): **este plan**, a decidir Caso 1/2.
- ⏳ Respaldo facial / liveness: **este plan**, a decidir Opción 1/2/3.
