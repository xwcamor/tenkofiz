# Arquitectura multi-sede y camino a SaaS — decisiones y dilemas

> **Estado (Fase 1 IMPLEMENTADA).** Ya existe multi-empresa real: tabla `companies`,
> `company_id` en todas las tablas de negocio, aislamiento por `CompanyScope`,
> super-admin (`users.is_super_admin`) que crea y administra workspaces, y settings
> por empresa. Se sembró **Empresa 1 (SENATI)** con sus zonales y **Empresa Demo**
> con los datos previos. Ver §4 al final para el detalle de lo implementado. Lo que
> sigue (facturación, registro self-service, subdominios) es Fase 3+.
>
> **Fase 2 IMPLEMENTADA:** controles comerciales del super-admin — **suspender** un
> workspace (ej. falta de pago: usuarios desconectados al instante y kioscos
> bloqueados, datos intactos), **eliminarlo** (borrado lógico con motivo,
> restaurable), **plan por workspace** (módulos contratados + límite de empleados y
> sedes; acceso efectivo = plan ∧ perfil), y **auditoría de seguridad global**:
> cada inicio de sesión (y cada intento fallido) queda registrado con dispositivo,
> IP y ubicación GPS real si la persona da permiso al navegador (enlace al mapa);
> `audit_logs` quedó aislado por empresa y el super ve el registro global.


Este documento explica **los dilemas** que aparecieron al crecer el sistema con
sedes, tokens y cookies, **qué se decidió** (ya implementado) y **qué se recomienda**
para el siguiente salto: convertirlo en un SaaS con un administrador de sistema y
administradores de empresa. No es código: es la guía de diseño para no equivocar el
rumbo con la información que ya está creciendo.

---

## 1. Los dilemas que planteaste (y la respuesta)

### 1.1 "El usuario de la sede en sesión debería ver solo su propia sede"
**Hecho.** Se agregó `users.site_id`:
- `NULL` = administrador de **toda la empresa** (ve todas las sedes).
- Con sede = solo ve **su** sede (empleados, asistencias, reportes, vacaciones,
  justificaciones).

Se implementó con un *global scope* en `Employee` y un scope `inCurrentSite()` en
asistencias/vacaciones/justificaciones. Es la forma menos invasiva y más difícil de
saltarse: si mañana agregas una consulta nueva sobre empleados, **ya queda filtrada
sola**. Detalle técnico en `REGLAS_DE_NEGOCIO.md` §8.1.

### 1.2 "¿El token ya no funcionaría con varias sedes?"
**Correcto: un token global no servía.** Si todas las sedes comparten un token, la
tablet de la sede A podría abrir el kiosco de la sede B. Se **movió la seguridad a la
sede**: cada sede tiene su **propio token** y su **propio dispositivo vinculado**
(`sites.kiosk_token`, `sites.kiosk_device_hash`). El enlace de cada tablet es
`/kiosk?site=<id>&token=<token de esa sede>`. Detalle en `REGLAS_DE_NEGOCIO.md` §1.5.

Al actualizar, una migración copia el token/dispositivo global anterior a la primera
sede, así **la tablet que ya tenías autorizada sigue funcionando**.

### 1.3 "La cookie, ojo ahí"
La cookie `kiosk_device` sigue siendo el secreto del dispositivo, pero ahora se valida
contra el hash **de la sede** que la tablet abre. Una tablet queda atada a **una sede a
la vez** (volver a emparejarla a otra sede sobreescribe su cookie). Es el modelo
correcto: **una tablet = una sede**.

### 1.4 "En Ajustes se volvió muy restrictivo, ¿o no?"
Sí, tenías razón: mezclar la seguridad del kiosco (que es por tablet/sede) dentro de
Ajustes globales confundía. Se **separó**:
- **Ajustes** conserva lo que es de **toda la empresa**: datos de la empresa, zona
  horaria, corte de planilla, ventanas de marcado, y la **configuración de
  reconocimiento** (modo rápido, parpadeo, exigir rostro, umbral, PIN de enrolamiento).
- **Sedes** ahora tiene la **seguridad del kiosco por sede** (enlace, token,
  vinculación de dispositivo). Ajustes solo deja un acceso directo a Sedes.

> Decisión de diseño: la **afinación del reconocimiento** (umbral, parpadeo, exigir
> rostro) se dejó **global** a propósito — es política de la empresa y aplica igual a
> todas las tablets. Si en el futuro una sede necesita un umbral distinto, se puede
> mover a la sede sin romper nada (los campos ya viven en `settings`; sería replicarlos
> en `sites` y leer "sede → si null, empresa").

### 1.5 "En los reportes debe salir la sede y la dirección"
**Hecho.** El reporte en pantalla, el Excel y la ficha imprimible muestran ahora la
**sede** y su **dirección**.

---

## 2. El dilema grande: "¿un admin de sistema y un admin de empresa? como un SaaS"

Tu intuición es la correcta y es el rumbo natural del producto. Hoy el sistema es
**mono-empresa con multi-sede**. Un SaaS es **multi-empresa** (cada cliente es una
empresa aislada) con un dueño del sistema que reparte módulos y da de alta clientes.

Esto **no se debe construir de un tirón y sin supervisión**, porque toca autenticación,
aislamiento de datos y decisiones de producto/precio. Por eso aquí queda **el diseño
recomendado**, no el código.

### 2.1 Los tres niveles que tendrías

| Nivel | Quién es | Qué hace | Alcance |
|------|----------|----------|---------|
| **Super-admin (dueño del SaaS)** | Tú / el distribuidor | Da de alta empresas, activa/desactiva **módulos** por empresa, define límites (nº de empleados, sedes), ve facturación | **Todas** las empresas |
| **Admin de empresa** | El cliente | Administra SU empresa: sedes, empleados, usuarios, perfiles, ajustes | **Una** empresa (todas sus sedes) |
| **Admin/usuario de sede** | Encargado local | Lo que ya existe hoy con `users.site_id` | **Una** sede |

El tercer nivel **ya está listo** (§1.1). Lo nuevo sería el primero y "empresa" como
frontera de datos.

### 2.2 Modelo de datos recomendado (el corazón del asunto)

1. Tabla **`companies`** (o `tenants`): `name`, `tax_id`, `is_active`, `plan`,
   `modules` (JSON con los módulos habilitados por el super-admin), límites.
2. Agregar **`company_id`** a todo lo que es datos de negocio: `sites`, `employees`,
   `users`, `schedules`, `areas`, `positions`, `holidays`, `settings`,
   `attendances`… (asistencias/vacaciones/justificaciones pueden heredar vía el
   empleado, igual que hoy heredan la sede).
3. Los `settings` dejan de ser **una fila global** y pasan a ser **una fila por
   empresa** (hoy `Setting::instance()` asume `id = 1`; pasaría a
   `Setting::forCompany($id)`).

### 2.3 Aislamiento entre empresas (lo más crítico)

La regla de oro de un SaaS: **una empresa jamás debe ver datos de otra**. La forma más
segura y con menos código es **exactamente el patrón que ya usamos para sedes**, pero a
nivel empresa:

- Un **global scope `CompanyScope`** sobre cada modelo con `company_id`, que filtra por
  la empresa del usuario autenticado. Es el mismo enfoque de `SiteScope`, subido un
  nivel. Así, aunque se agregue una consulta nueva, **queda aislada por defecto**
  (seguridad por omisión, no por disciplina).
- El **super-admin** es el único que puede "salir" del scope (o cambiar de empresa
  con un selector), igual que hoy un admin de empresa (site_id NULL) ve todas las
  sedes.
- La resolución de empresa puede ser por **subdominio** (`empresaA.tudominio.com`) o
  por la empresa del usuario logueado. Subdominio es lo más limpio para un SaaS y
  para el kiosco (cada empresa su URL).

### 2.4 Módulos "que el distribuidor reparte"

Ya tienes media pieza: los **perfiles** guardan qué módulos ve cada usuario
(`profiles.permissions`). Falta la capa de arriba: **qué módulos tiene contratados la
empresa** (`companies.modules`). La regla efectiva sería:

> módulo visible = está en el **plan de la empresa** **Y** el **perfil** del usuario lo
> permite.

Así el super-admin "da los módulos" a la empresa y el admin de empresa los reparte a
su gente.

### 2.5 Orden recomendado de implementación (por fases, supervisado)

1. **Fase 0 (ya hecho)**: multi-sede + alcance por usuario + seguridad de kiosco por
   sede. Es la base y valida el patrón de *scoping*.
2. **Fase 1 — Empresa como entidad**: crear `companies`, `company_id` en las tablas,
   `settings` por empresa, backfill de los datos actuales a una empresa "por defecto".
   Sin cambiar todavía la experiencia de nadie.
3. **Fase 2 — Aislamiento**: `CompanyScope` global + middleware que fija la empresa
   (por subdominio o por usuario). Pruebas duras de que empresa A no ve a B.
4. **Fase 3 — Super-admin**: panel para alta de empresas, activar módulos y límites,
   suspender clientes. `companies.modules` manda sobre los perfiles.
5. **Fase 4 — Comercial**: planes, límites por plan, facturación, y si aplica,
   registro *self-service* de nuevas empresas.

### 2.6 Riesgos a cuidar (por qué no hacerlo a ciegas)

- **Migrar datos existentes**: tu información ya está creciendo. El backfill a la
  empresa por defecto debe ser idempotente y probado en copia antes de producción.
- **Fugas de aislamiento**: cualquier consulta que olvide el scope filtra datos entre
  empresas. Por eso el *global scope* (seguro por omisión) y **tests de aislamiento**
  son obligatorios antes de abrir el SaaS.
- **El kiosco es público**: hoy no tiene usuario logueado. Con multi-empresa, la
  empresa del kiosco debe salir del **subdominio o de la sede** del enlace, nunca de
  una sesión de admin.
- **Decisiones de producto**: planes, límites y precios son tuyas, no técnicas. Por eso
  esta parte se **recomienda y se hace por fases contigo**, no de un tirón.

---

## 3. Resumen

- Lo que pediste para **sedes, alcance por usuario, tokens y cookie**: **implementado y
  probado**.
- Lo de **SaaS multi-empresa (super-admin vs admin de empresa)**: **es el rumbo
  correcto**, y aquí queda el plano para hacerlo por fases y con seguridad, sin arriesgar
  los datos que ya tienes. El patrón de *scoping* que ya montamos para sedes es
  justamente el que se reutiliza para empresas — subido un nivel.

---

## 4. Fase 1 — lo que YA está implementado

Modelo de datos y aislamiento:
- Tabla **`companies`** (workspaces). Cada tabla de negocio lleva `company_id`:
  `users, sites, employees, settings, schedules, areas, positions, holidays,
  holiday_templates` (asistencias/vacaciones/justificaciones heredan la empresa vía
  el empleado). Migración `2026_01_13_000001` crea todo y **mueve los datos previos
  a una empresa por defecto ("Empresa Demo")** — no se pierde nada.
- **`App\Models\Scopes\CompanyScope`**: global scope que aísla cada modelo a la
  empresa actual. La empresa actual sale de: `users.company_id` (usuario normal); la
  empresa que el super-admin **entró** (`session('acting_company_id')`, null = ver
  todas); o la empresa de la sede para el kiosco (invitado). Consola/seeders usan
  `CompanyScope::actingAs()`. El trait `BelongsToCompany` asigna la empresa al crear.
- **Unicidad por empresa** (`2026_01_13_000002`): dos workspaces pueden repetir
  nombres de sede/área/cargo/horario y los mismos feriados/documentos.
- **Ojo (auth):** el modelo `User` **no** lleva el global scope (el guard consulta
  usuarios al resolver el login y un scope que lea `auth()` haría recursión). `User`
  se filtra explícito con `scopeInCompany()` (lista de usuarios, notificaciones).

Super-admin y workspaces:
- **`users.is_super_admin`**: dueño de todos los workspaces; pasa cualquier chequeo de
  módulo. Cuenta sembrada: `super@test.com` / `123456`.
- Consola en **`/admin/companies`** (middleware `super_admin`): lista workspaces con
  conteos, **crea** un workspace (empresa + settings + plantillas de feriados + primer
  admin), edita y **entra/sale** de un workspace. Al entrar, todo queda scopeado a esa
  empresa; un banner muestra "Administrando: X" con botón Salir.
- Settings **por empresa**: `app_setting()` resuelve la fila de la empresa actual
  (reportes/branding/kiosco por workspace).

Datos sembrados:
- **Empresa Demo**: los usuarios de prueba (`admin/aprobador/empleado@test.com`),
  su sede, catálogos y feriados.
- **Empresa 1 (SENATI)**: settings propios + 10 zonales (Central Independencia,
  Lima-Callao, Arequipa, La Libertad, Áncash, Junín, Lambayeque, Piura, Cusco, Ica).
  Direcciones aproximadas — ajústalas en la pantalla Sedes.

Pruebas: `CompanyIsolationTest` (empresa A no ve a empresa B, mismo documento en dos
empresas, entrar/salir del super-admin, creación de workspace) + `SiteScopingTest`.

Límites conocidos de la Fase 1 (para fases siguientes): los **perfiles** siguen siendo
globales (plantillas de permisos compartidas); el acceso directo por ID a un usuario de
otra empresa no está bloqueado (las listas sí están aisladas); falta facturación,
límites por plan, registro self-service y resolución por subdominio.

---

## 5. DECIDIDO E IMPLEMENTADO — Enrolamiento y marcado por documento

**Decisión de Carlos (IMPLEMENTADA):** el marcado por documento es SOLO el plan B de quien ya tiene rostro enrolado. Detalle original del dilema:

> El respaldo "marcar por documento + foto de evidencia" quizá debería existir SOLO
> para quien **ya tiene rostro enrolado** (como plan B cuando el reconocimiento
> falla). Quien **no** tiene rostro enrolado **no debería poder marcar por
> documento**: debería enrolarse primero (el PIN del kiosco existe justamente para
> que se registren desde ahí) y recién entonces marcar.

Estado actual (DECIDIDO e implementado): quien no tiene rostro enrolado SOLO puede
enrolarse ahí mismo en `/kiosk/verify` (PIN si está configurado; el servidor
rechaza su marcado por documento con 422). Además la regla "sin rostro en cámara
no hay marca ni foto" es FIJA (el toggle `kiosk_require_face` fue eliminado), y la
calibración del reconocimiento (umbral + segundos) es exclusiva del super-admin
desde la consola de Workspaces (ver REGLAS_DE_NEGOCIO §1.1, §1.2b y §1.2c).

Análisis para decidir:
- **A favor de la regla propuesta**: obliga a enrolar el día 1; elimina las marcas
  "solo documento" de gente nunca enrolada; la foto de evidencia deja de ser la vía
  normal y vuelve a ser una excepción.
- **Riesgos**: si la cámara falla o no hay supervisor con el PIN a mano, la persona
  **no podría marcar** (asistencia bloqueada el primer día); hoy ese caso cae al
  respaldo por documento. Habría que definir el procedimiento para "cámara rota".
- **Implementación sugerida si se aprueba**: un ajuste
  `kiosk_document_fallback = solo_enrolados | todos | nunca` en Ajustes → Facial,
  para que cada empresa elija su rigidez. Cambio pequeño (una condición en
  `/kiosk/verify` + el ajuste).

---

## 6. EL MODELO DE OPERACIÓN SaaS (definición formal — implementada)

La analogía del edificio de Carlos es exactamente el modelo:

| Nivel | Analogía | Quién | Qué hace | Dónde |
|---|---|---|---|---|
| **Super-admin** | Dueño del edificio | Tú | Crea los "pisos" (empresas) con su **primer admin**, reparte módulos y límites (plan), suspende/elimina por falta de pago, ve la **auditoría de seguridad global** | Consola **Espacios de trabajo** |
| **Admin de empresa** | Administrador del piso | Tu cliente | Dentro de SU workspace: crea **sedes**, usuarios, perfiles, horarios, feriados y sus **normas** (Ajustes) | Su workspace |
| **Usuario de sede / empleado** | Encargado de área / inquilino | Su gente | Solo su sede (`users.site_id`) o solo su propia información | Su sede |

**Reglas duras que el sistema HACE CUMPLIR (no son convención):**

1. **El super nunca opera "desde ninguna parte".** Fuera de un workspace solo ve la
   consola de Espacios de trabajo y la auditoría global. Si intenta abrir
   Empleados/Asistencias/etc. se le redirige con el mensaje "Primero entra a un
   espacio de trabajo" (`CheckModule` + `User::hasModule`). Así es IMPOSIBLE la
   ambigüedad de "¿a qué empresa le creé esto?": todo lo que el super crea, lo crea
   **dentro** del workspace que entró (banner oscuro visible + botón Salir).
2. **El acceso de cada empresa nace con la empresa.** Al crear un workspace, el
   super define su **primer administrador** (nombre/correo/clave, se le envía por
   correo). Ese admin es quien reparte el acceso hacia abajo: usuarios, perfiles
   (módulos dentro del plan), sedes y usuarios atados a sede.
3. **La separación de accesos es por datos, no por pantallas**: `CompanyScope`
   (empresa) + `SiteScope` (sede) filtran TODA consulta automáticamente. Un admin
   de empresa jamás ve otra empresa; un usuario de sede jamás ve otra sede.
4. **El super no es un empleado**: no tiene "Mis asistencias / Vacaciones /
   Justificaciones" en el menú; su cuenta es de plataforma.
5. **Listas con contexto**: Empleados muestra la **columna Sede**; el workspace en
   el que estás siempre se ve en el banner superior (super) o es el tuyo (admin).
