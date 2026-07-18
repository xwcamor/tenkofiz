# Arquitectura multi-sede y camino a SaaS — decisiones y dilemas

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
