# Estado del proyecto — IPSearch Interya

> Documento vivo. Refleja el estado **real del código** al 2026-11-27.
> Cualquier afirmación aquí debe poder comprobarse ejecutando las pruebas descritas.

---

## 1. Identidad y propósito

Plataforma **privada de uso interno** de Interya (ISP). Permite que usuarios autorizados
ingresen una dirección MAC y el sistema resuelva automáticamente su dirección IP
consultando varios routers **MikroTik RouterOS 7** vía API REST, indicando router,
ubicación, mecanismo de asignación, estado, hostname y fecha de consulta, y permitiendo
abrir la interfaz web del dispositivo.

**Requisito innegociable:** ninguna consulta MAC → IP puede ejecutarse sin sesión
iniciada y cuenta activa. Ver §6.

---

## 2. Stack y versiones detectadas

| Componente | Versión / detalle |
|---|---|
| PHP | 8.3.28 (Laragon) — extensiones `curl`, `openssl`, `pdo_mysql`, `sqlite3`, `mbstring` |
| Framework | Laravel **11.57.2** (skeleton `laravel/laravel`, estructura Laravel 11: `bootstrap/app.php`) |
| Base de datos | **MySQL 8.4.3** (Laragon), base `ipsearch_interya` |
| Pruebas | PHPUnit 11.5.58 + Laravel TestCase. SQLite `:memory:` (nunca toca la BD real) |
| Frontend | Blade + **Bootstrap 5.3.3** vía CDN + Bootstrap Icons 1.11.3 + Chart.js vía CDN. CSS propio embebido en `layouts/app.blade.php` |
| Build frontend | `package.json`/Vite/Tailwind existen pero **no se usan**: las vistas no incluyen `@vite`. No se requiere `npm run build` para desplegar |
| Colas / sesiones / caché | `database` (tablas `jobs`, `sessions`, `cache` migradas) |
| Correo | SMTP administrado desde el panel (tabla `mail_settings`, contraseña cifrada). `MailConfigurator` lo aplica al mailer en cada arranque; si no hay configuración activa se usa `MAIL_MAILER` del `.env` (local: `log`) |
| Git | Repositorio inicializado (rama `master`) |

> Nota: la salida de PHP incluye avisos `MIB search path: c:/usr/share/snmp/mibs`
> (extensión `snmp` mal configurada en el php.ini local). Es ruido del entorno, no un
> fallo de la aplicación.

---

## 3. Arquitectura

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── SearchController.php              Buscador, historial propio, detalle, abrir dispositivo
│   │   ├── UserController.php                Perfil del usuario autenticado
│   │   ├── Admin/
│   │   │   ├── AdminDashboardController.php  KPIs, serie diaria, salud, estadísticas (JSON)
│   │   │   ├── AdminSettingsController.php   Auditoría, configuración general, SMTP
│   │   │   ├── RouterAdminController.php     CRUD routers + prueba de conexión
│   │   │   └── UserAdminController.php       CRUD usuarios, suspender/reactivar, reenviar invitación
│   │   ├── Auth/
│   │   │   ├── AuthenticatedSessionController.php  Login/logout + throttle + auditoría
│   │   │   ├── PasswordController.php              Recuperación (broker) y cambio de contraseña
│   │   │   ├── RegisteredUserController.php        Invitaciones y activación de cuenta
│   │   │   └── VerificationController.php          Verificación de correo
│   │   └── Api/MacResolverController.php     API interna JSON (auth obligatoria)
│   └── Middleware/
│       ├── EnsureUserIsActive.php            Expulsa sesiones de cuentas suspendidas
│       └── EnsureUserHasRole.php             Autorización por rol (superadmin)
├── Models/                                    User, MikrotikRouter, MacSearch, MacSearchResult,
│                                              AuditLog, Invitation, SystemSetting, MailSetting
├── Services/
│   ├── MacAddressResolverService.php          Orquestación de la consulta + persistencia + auditoría
│   ├── InvitationService.php                  Emisión de invitaciones (token hasheado)
│   └── Mikrotik/
│       ├── MikrotikRestClient.php             Cliente REST RouterOS 7 (curl_multi, timeouts, SSL)
│       └── MikrotikException.php              Excepción tipada por clase de error
└── Exceptions/SearchUnauthorizedException.php
```

**Responsabilidades:** el frontend nunca habla con un router. Todo el acceso a MikroTik
ocurre en `MikrotikRestClient`, invocado solo por `MacAddressResolverService`, que a su
vez solo se invoca desde controladores protegidos por `auth` + `active`.

**Middleware:** `bootstrap/app.php` registra únicamente los alias `active` y `role`.
El middleware de cuenta activa **no** se aplica globalmente al grupo `web` (ver §9, BUG-01).

---

## 4. Base de datos (12 migraciones)

| Tabla | Contenido |
|---|---|
| `users` | name, lastname, username, email, password, role (`superadmin`\|`user`), is_active, last_login_at, suspended_at, suspended_reason, email_verified_at |
| `invitations` | user_id, email, token (SHA-256), expires_at, accepted_at, invited_by |
| `mikrotik_routers` | name, host, api_port, api_user, api_password (**cifrado**), use_ssl, verify_ssl, location, municipality, is_active, priority, timeouts, last_success_at, last_checked_at, connectivity_status |
| `mac_searches` | user_id, mac_normalized, mac_input, total_routers, reachable_routers, result_count, status, duration_ms, ip_address, request_ip, user_agent, meta |
| `mac_search_results` | mac_search_id, router_id, router_name, **municipality**, ip_address, mac_address, mechanism, **mechanisms** (JSON con todos los mecanismos que confirman la IP), status_label, hostname, server, interface, comment, is_active_match, response_ms, raw |
| `audit_logs` | user_id, user_label, action, description, ip_address, result, operation_id, context, created_at (**sin updated_at**: registro inmutable) |
| `system_settings` | key, value, type (`string`\|`bool`\|`int`\|`json`) |
| `mail_settings` | host, port, username, password (cifrado), encryption, from_address, from_name, is_active |

Estados de búsqueda: `completed`, `partial`, `no_results`, `error`, `no_routers`.
Mecanismos: `pppoe_active`, `dhcp_active`, `dhcp_static`, `arp_complete`, `hotspot_binding`.

---

## 5. Integración MikroTik (estado real)

- **Protocolo:** API REST de RouterOS 7 (`/rest/...`) sobre HTTP(S), autenticación básica.
- **Endpoints consultados por búsqueda**: todos los routers × endpoints en un único `curl_multi`
  (`MikrotikRestClient::collectSourcesMany`), así la búsqueda tarda lo que el router más lento.
  Cada petición lleva el filtro REST `?mac-address=` (`?caller-id=` en PPP) para que el router
  devuelva solo las filas de esa MAC y no la tabla completa:
  - `/rest/ip/dhcp-server/lease` → leases dinámicos y estáticos
  - `/rest/ppp/active` → PPPoE activo (MAC en `caller-id`)
  - `/rest/ip/arp` → tabla ARP (completa/incompleta)
  - `/rest/ip/hotspot/ip-binding` → asociaciones estáticas MAC → IP
- **Credenciales:** contraseña cifrada con `Crypt` (APP_KEY). Se descifra en memoria; nunca
  se registra en logs ni se devuelve en JSON (`$hidden = ['api_password']`).
- **Timeouts:** `connect_timeout_ms` y `read_timeout_ms` por router (validados 500–30000 / 1000–60000).
- **SSL:** `use_ssl` y `verify_ssl` configurables por router.
- **SSRF:** `CURLOPT_FOLLOWLOCATION=false` y `CURLOPT_PROTOCOLS` restringido a HTTP/HTTPS.
- **Tolerancia a fallos:** un endpoint ausente (p. ej. router sin PPP) **no** invalida el
  router; solo se marca error si fallan **todos** los endpoints (ver §9, BUG-06).
- **Estado del router:** `connectivity_status` (`ok`/`fail`/`unknown`) se actualiza en cada
  búsqueda, en la prueba manual de conexión y por el comando programado `routers:health`
  (cada 10 minutos, ver §11).
- **No verificado contra hardware real:** no hay routers dados de alta. La ruta de error se
  validó con un router de prueba apuntando a un puerto cerrado (ver §7).

---

## 6. Autenticación, roles y seguridad

### Roles
| Capacidad | superadmin | user |
|---|---|---|
| Buscar MAC | ✅ | ✅ |
| Historial propio / detalle | ✅ | ✅ |
| Abrir dispositivo | ✅ | ✅ |
| Editar perfil / cambiar contraseña | ✅ | ✅ |
| CRUD routers, usuarios, invitaciones | ✅ | ❌ (403) |
| Auditoría, configuración, SMTP, salud | ✅ | ❌ (403) |

### Controles implementados
- **Todas** las rutas de búsqueda, historial, detalle, dispositivo y API interna están bajo
  `['auth','active']`. Las administrativas, además, bajo `role:superadmin`.
- `EnsureUserIsActive`: si la cuenta se suspende con la sesión abierta, la siguiente petición
  cierra sesión, invalida el token CSRF y redirige al login.
- CSRF activo en todo el grupo `web`; los POST sin token se rechazan con **419** antes de
  llegar al servicio de resolución.
- Throttle: login 20/min por IP+identidad con bloqueo configurable (`login_max_attempts`,
  `login_decay_seconds`) y registro en auditoría; búsqueda 30/min; recuperación de contraseña
  5/min; verificación de correo 6/min.
- Contraseñas: `bcrypt` (12 rondas), mínimo 10 caracteres con mayúscula, minúscula, número y símbolo.
- Tokens de invitación y recuperación almacenados **hasheados** (SHA-256 / broker de Laravel).
- Escapado: Blade `{{ }}` + `escapeHtml()` en el render JS del buscador.
- SQL injection: Eloquent/Query Builder con bindings; no hay SQL concatenado.
- Registro libre deshabilitado: las cuentas solo se crean por invitación.

### Evidencia (ejecutable)
`php artisan test` → **100 pruebas / 361 aserciones, OK**, incluyendo:
- invitado no accede a 8 rutas protegidas (redirección a login) y no ejecuta búsquedas;
- la API interna responde 401 sin sesión;
- usuario normal recibe 403 en todo el panel y no crea routers ni usuarios;
- usuario suspendido con sesión abierta es expulsado y su búsqueda no se ejecuta;
- el historial solo muestra las búsquedas propias y el detalle ajeno responde 404;
- la configuración SMTP se aplica al mailer (incluido `smtps` para SSL);
- invitación y recuperación de contraseña envían su notificación, y un token inválido o
  reutilizado se rechaza;
- `device/open` solo permite IPs del historial del usuario (404 si no);
- `routers:health` actualiza estado y `last_success_at` de cada router.

Verificación HTTP manual (servidor real): `GET /login` 200 · `GET /` 302→login ·
`POST /search` invitado 419 · `POST /api/v1/mac-search` invitado 419 · panel admin 200 con
superadmin · búsqueda autenticada 200 con respuesta JSON correcta.

---

## 7. Verificación ejecutada en esta intervención

1. `php artisan migrate` sobre base nueva → 11 migraciones OK.
2. `php artisan db:seed` → settings iniciales + superadministrador (`admin@interya.com`,
   contraseña aleatoria mostrada por consola una sola vez).
3. `php artisan route:list` → 53 rutas, sin errores de reflexión.
4. `php artisan view:cache` → todas las plantillas compilan.
5. `php artisan test` → 97/97 OK.
6. Prueba HTTP real con `curl` + cookie jar: login, búsqueda sin routers (`no_routers`),
   y búsqueda con router inalcanzable (`error`, `failures[0].error=timeout`,
   `connectivity_status=fail`). El router de prueba se eliminó después.
7. Escaneo de BOM y `php -l` sobre todos los archivos modificados → sin errores.
8. Con un SMTP activo apuntando a un puerto cerrado: la app sigue respondiendo (login 200) y
   la solicitud de recuperación devuelve 302 con mensaje genérico, registrando solo un warning
   sin datos sensibles.
9. `php artisan routers:health` ejecutado sobre la base real y `php artisan schedule:list`
   confirma la programación cada 10 minutos.

---

## 8. Funcionalidades: clasificación real

### ✅ TERMINADA Y VERIFICADA (con pruebas)
- Login/logout con throttle, auditoría y bloqueo de cuentas suspendidas.
- Autorización de rutas por rol y estado de cuenta (backend, no UI).
- Buscador MAC → IP: normalización, consulta paralela multi-router, clasificación de estado,
  persistencia de búsqueda + resultados + auditoría.
- Persistencia estable de resultados (incluye municipio aunque el router se renombre/elimine).
- Historial propio paginado y detalle, con aislamiento entre usuarios.
- Apertura de la interfaz web del dispositivo (esquema y puerto configurables), restringida a
  IPs que aparezcan en el historial del propio usuario.
- CRUD de routers con cifrado de credenciales y prueba de conexión.
- CRUD de usuarios, suspensión/reactivación, protección del último superadmin y de la propia cuenta.
- Invitaciones: alta de cuenta inactiva, enlace de activación de un solo uso con caducidad,
  activación con contraseña robusta y login automático.
- Envío por correo de la invitación (`InvitationNotification`) y de la recuperación de
  contraseña (`ResetPasswordNotification`), ambas en español.
- Configuración SMTP del panel aplicada al mailer en cada arranque (`MailConfigurator`);
  un fallo de correo no bloquea la aplicación (se registra sin datos sensibles).
- Auditoría con filtros (acción, resultado, rango de fechas) y detalle.
- Configuración general y SMTP persistidas (cifrado de contraseña SMTP).
- Perfil y cambio de contraseña.
- Panel con KPIs reales y serie diaria real de consultas.
- Sondeo programado de salud de routers (`routers:health`, cada 10 minutos).

### 🟡 IMPLEMENTADA, NO VERIFICADA CONTRA ENTORNO REAL
- Consulta REST a MikroTik: validada por pruebas con cliente simulado y por ruta de error
  real (timeout). **Nunca** probada contra un RouterOS 7 físico.
- Entrega efectiva de correo (bandeja real): la configuración se aplica y el envío se ejecuta,
  pero no hay un servidor SMTP real configurado todavía; verificado con `Notification::fake()`
  y con un SMTP inalcanzable (fallo controlado, sin 500).
- Verificación de correo: rutas, controlador y notificación en español listos; sin correo real
  no se puede completar el ciclo.

### 🟠 PARCIAL
- **Estadísticas:** `/admin/estadisticas` devuelve JSON con serie diaria, mecanismos y top de
  usuarios, pero no existe vista dedicada (el dashboard solo grafica la serie diaria).
- **Salud de infraestructura:** el sondeo programado ya actualiza el estado; la vista muestra el
  resultado y permite probar manualmente, pero no hay histórico de disponibilidad ni alertas.
- **Historial global:** el superadministrador aún no tiene vista de historial de todos los usuarios.

### ⛔ PENDIENTE
Ver `docs/PENDIENTES.md`.

---

## 9. Defectos encontrados y corregidos (registro)

| ID | Defecto | Impacto | Archivo(s) |
|---|---|---|---|
| BUG-01 | `EnsureUserIsActive` añadido al grupo `web` global | **Bucle infinito de redirección: `/login` → `/login`. La aplicación era inaccesible** | `bootstrap/app.php` |
| BUG-02 | `AdminSettingsController` inexistente pero referenciado | `route:list` y `/admin/auditoria`, `/admin/config`, `/admin/smtp` fallaban | `app/Http/Controllers/Admin/AdminSettingsController.php` (creado) |
| BUG-03 | `MacResolverController` sin `use` en `routes/web.php` | Endpoint `/api/v1/mac-search` → "Class not found" | `routes/web.php` |
| BUG-04 | Recursos admin registrados sin prefijo `admin.` en el nombre | **Todos** los enlaces del menú (`admin.users.index`, `admin.routers.index`) → RouteNotFoundException | `routes/web.php` |
| BUG-05 | 12 rutas referenciadas por vistas y no definidas (`search.history`, `search.detail`, `admin.routers.test`, `admin.users.suspend/reactivate/destroy`, `invite.accept.submit`, …) | Errores 500 en el layout principal y botones muertos | `routes/web.php` |
| BUG-06 | `collectSources`: un endpoint ausente marcaba el router entero como caído | Routers sin PPP/hotspot se reportaban "error" y se perdían coincidencias DHCP/ARP | `MikrotikRestClient.php` |
| BUG-07 | `collectSources`: `curl_errno()` no fiable en modo multi en este runtime | Router inalcanzable se reportaba como accesible sin resultados | `MikrotikRestClient.php` (usa `curl_multi_info_read`) |
| BUG-08 | Resolver devolvía filas vacías cuando la coincidencia no tenía IP válida | `Undefined array key` → 500 al guardar resultados | `MacAddressResolverService.php` |
| BUG-09 | `audit_logs` sin `updated_at` + Eloquent con timestamps | **Toda** escritura de auditoría fallaba (incluido el login) | `app/Models/AuditLog.php` |
| BUG-10 | `UserAdminController` sin `use App\Models\AuditLog` | Error fatal en alta/edición/suspensión de usuarios | `UserAdminController.php` |
| BUG-11 | `destroy()` con `where('role','superadmin','is_active',true)` | SQL inválido al eliminar usuarios | `UserAdminController.php` |
| BUG-12 | Ruta `password.update` duplicada; el cambio de contraseña del perfil llamaba al método de reseteo | Cambio de contraseña inoperante (410) y formulario de reseteo con 405 | `routes/web.php`, `PasswordController.php`, vistas |
| BUG-13 | Recuperación de contraseña guardaba el token en `remember_token`, sin caducidad y sin enviar correo | Enlaces válidos para siempre y flujo inservible | `PasswordController.php` (broker nativo) |
| BUG-14 | Invitaciones: el token en claro se perdía (`forceFill` no persistido) | **Imposible** activar una cuenta invitada | `InvitationService.php` (creado), `RegisteredUserController.php` |
| BUG-15 | `@if` sin `@endif` en listado de usuarios | La vista no compilaba | `admin/users/index.blade.php` |
| BUG-16 | `last_checked_at->diffForHumans()` sin null-safe | 500 en listado de routers sin comprobar | `admin/routers/index.blade.php` |
| BUG-17 | `$action` indefinida en formulario de router | 500 al crear router | `admin/routers/create.blade.php` |
| BUG-18 | Formulario de usuario sin botón de envío, rol deshabilitado y `$user->is_superadmin` inexistente | No se podía guardar; un superadmin se degradaba a `user` | `admin/users/edit.blade.php` |
| BUG-19 | `@section('content')` sin cerrar en el dashboard | Página truncada y fuga de buffer de salida | `admin/dashboard.blade.php` |
| BUG-20 | Hoja de estilos **RTL** cargada siempre | Toda la interfaz se renderizaba de derecha a izquierda | `layouts/app.blade.php` |
| BUG-21 | `@can('admin')` sin `Gate` definido | El menú de administración nunca aparecía | `AppServiceProvider.php` |
| BUG-22 | Checkboxes `use_ssl`/`verify_ssl`/`is_active` siempre se guardaban en `true` | Imposible desactivar SSL o un router | `RouterAdminController.php` |
| BUG-23 | `MacResolverController::history` devolvía un Builder sin `get()` | JSON inválido | `Api/MacResolverController.php` |
| BUG-24 | API devolvía el modelo completo en `search_id` | Contrato incorrecto y fuga de campos internos | `SearchController.php`, `MacResolverController.php` |
| BUG-25 | Gráfico del dashboard con datos falsos hardcodeados | Métricas engañosas | `AdminDashboardController.php`, `dashboard.blade.php` |
| BUG-26 | `revalidateInvitation` bloqueaba reenviar invitación a cuentas inactivas (estado normal del invitado) | Reenvío siempre fallaba | `UserAdminController.php` |
| BUG-27 | `DatabaseSeeder` de fábrica incompatible con la tabla `users` (falta `username`) y sin superadmin | `db:seed` fallaba; no había forma de entrar al sistema | `DatabaseSeeder.php`, `UserFactory.php` |
| BUG-28 | `phpunit.xml` apuntaba a MySQL de desarrollo | Las pruebas podían tocar la BD real | `phpunit.xml` |
| BUG-29 | `MacSearchResult::search()` infería la FK `search_id` (columna inexistente) | La relación con la búsqueda fallaba al usarse | `app/Models/MacSearchResult.php` |
| BUG-30 | `GET /device/open/{ip}` devolvía URL para cualquier IP | Podía usarse como generador de enlaces a hosts arbitrarios | `SearchController::openDevice` |
| BUG-31 | Configuración SMTP del panel guardada pero nunca aplicada | El administrador creía tener correo configurado y no salía nada | `app/Services/Mail/MailConfigurator.php` (nuevo) |
| BUG-32 | Notificaciones del framework en inglés | Correos incoherentes con una interfaz 100 % en español | `app/Notifications/*` (nuevas) |
| BUG-33 | Superficies de color fijo (`bg-white`, `table-light`) con texto temático: en modo oscuro quedaban **blanco sobre blanco** | Encabezados y columnas ilegibles | 22 vistas + `layouts/app.blade.php` |
| BUG-34 | El tema seguía la preferencia del sistema y no había selector en la aplicación | Usuarios con Windows en oscuro veían una interfaz oscura sin poder cambiarla | `layouts/app.blade.php`, `partials/theme.blade.php` |
| BUG-35 | El formulario de router proponía el puerto 8729 (API binaria) para la API REST | Pruebas fallidas por puerto equivocado | `admin/routers/create.blade.php`, `edit.blade.php` |
| BUG-36 | "Probar" enviaba un formulario y el navegador salía del panel hacia un JSON crudo | El error de conexión no era legible ni accionable | `admin/routers/index.blade.php`, `admin/health/index.blade.php`, `partials/router-test.blade.php` |
| BUG-37 | Los errores de conexión no incluían el detalle de curl ni el endpoint probado | Imposible distinguir red, puerto o credenciales | `MikrotikRestClient`, `MikrotikException::hint()`, `RouterAdminController::test` |
| BUG-38 | Reglas `letters`, `mixed_case`, `numbers`, `symbols` usadas como texto en la validación de usuarios | **Internal Server Error** al crear o editar usuarios (`validateLetters` no existe) | `UserAdminController.php` |
| BUG-39 | El formulario de alta de usuario no tenía confirmación de contraseña y el checkbox "Activar" se ignoraba | Alta imposible de completar; el usuario quedaba siempre activo | `admin/users/create.blade.php`, `UserAdminController::store` |
| BUG-40 | Al editar un usuario, la contraseña se guardaba **en texto plano** y, si se dejaba vacía, se escribía `NULL` | Credencial expuesta en la base de datos y cuentas rotas | `UserAdminController::update` |
| BUG-41 | `public/favicon.ico` existía pero con **0 bytes**, y ninguna vista lo referenciaba | Pestaña sin icono y petición fallida en cada carga | `public/favicon.*`, `partials/favicon.blade.php` |

---

## 10. Deuda técnica conocida

- `welcome.blade.php` y `resources/js|css` son restos del esqueleto Laravel: no se usan.
- `resources/views/auth/login.blade.php` replica estilos en lugar de extender un layout de auth
  compartido (duplicación de ~20 líneas de CSS en 4 vistas de auth).
- `EnsureUserIsActive` y `EnsureUserHasRole` redirigen a login cuando no hay usuario; con el
  middleware `auth` delante ese caso ya no ocurre, pero se mantiene como defensa.
- El token CSRF se comparte entre el formulario y el `fetch` del buscador mediante un input
  oculto; funciona, pero un `<meta name="csrf-token">` sería más limpio.
- `MailConfigurator` ejecuta una consulta pequeña en cada arranque para leer `mail_settings`.
  Si el tráfico crece, cachear la configuración invalidando al guardar el panel.
- En desarrollo el enlace de activación de la invitación se conserva en el mensaje flash como
  respaldo. Retirarlo cuando el SMTP real esté operativo.

---

## 11. Automatización y correo
### Comando de salud
```
php artisan routers:health              # todos los routers activos
php artisan routers:health --router=3   # uno solo
```
Actualiza `last_checked_at`, `last_success_at` y `connectivity_status`, muestra una tabla y
registra un `warning` por cada router caído (sin credenciales en el log).

Programado en `routes/console.php` cada 10 minutos con `withoutOverlapping()`. Requiere el
planificador activo:
```powershell
php artisan schedule:work                                  # desarrollo
# producción (cron):
* * * * * cd /ruta && php artisan schedule:run >> /dev/null 2>&1
```

### Correo
- El panel (`admin/config`) guarda la configuración SMTP en `mail_settings` con la contraseña
  cifrada; `MailConfigurator` la aplica al mailer en cada arranque (`smtp`/`smtps` según TLS/SSL).
- Si no hay fila activa, se respeta `MAIL_MAILER` del `.env`.
- Notificaciones propias en español: `InvitationNotification`, `ResetPasswordNotification`,
  `VerifyEmailNotification` (registradas en `User::send*Notification`).
- Un fallo del servidor de correo nunca devuelve 500 ni revela datos: se registra un `warning`
  y, en las invitaciones, el enlace se muestra al administrador como respaldo.
- El panel incluye **Enviar correo de prueba** (`POST admin/smtp/test`, solo superadministrador y
  con throttle): envía un mensaje a la cuenta del propio administrador y muestra el error exacto
  del transporte. Con el transporte en `log` avisa de que no hay SMTP activo.

---

## 12. Interfaz y tema

- **Tema claro por defecto**; el oscuro es opcional y se elige con el botón de la barra superior
  (icono luna/sol). La elección se guarda en `localStorage` y se aplica antes del primer pintado.
- El tema se comunica con `data-bs-theme` en `<html>`, por lo que **Bootstrap 5.3 adapta**
  inputs, dropdowns, tablas y paginación.
- **Prohibido** usar clases de superficie fija (`bg-white`, `table-light`) o colores literales en
  las vistas: rompen el contraste al cambiar de tema. La prueba `tests/Feature/ViewThemeTest.php`
  falla si vuelven a aparecer.
- Paleta: azul `#0071BC`, azul oscuro `#083A56`, amarillo `#FCEE21`, fondos claros; grises
  `--muted` con contraste ≥ 6:1 sobre la superficie en ambos temas.

### Favicon e identidad
Icono propio: baldosa con degradado azul (`#0071BC` → `#083A56`), lupa amarilla (`#FCEE21`) y
punto blanco dentro de la lente (dispositivo localizado).

| Archivo | Uso |
|---|---|
| `public/favicon.svg` | Navegadores modernos (vectorial, nítido a cualquier tamaño) |
| `public/favicon.ico` | Fallback clásico, multi-tamaño 16/32/48/64 |
| `public/favicon-16x16.png`, `favicon-32x32.png` | Pestañas y accesos directos |
| `public/apple-touch-icon.png` (180) | iOS / guardar en pantalla de inicio |
| `public/favicon-192x192.png`, `favicon-512x512.png` | Android, PWA y previsualizaciones |

Se referencia con `resources/views/partials/favicon.blade.php` (incluye también
`theme-color`). Cualquier `<head>` nuevo debe incluir ese parcial; `tests/Feature/FaviconTest.php`
lo comprueba.

### Pie de página
`resources/views/partials/footer.blade.php`, incluido al final de `<main>` en el layout. Es
**fijo** (`position: fixed; bottom: 0`), con un **máximo de 75 px** de alto
(`max-height: 75px` + `overflow: hidden`), fondo de tarjeta, línea superior fina, texto pequeño
y alineado a la derecha («Software desarrollado por Duban Restrepo, todos los derechos
reservados.»).

- Se desplaza con el menú: `left: 240px` con la barra abierta y `left: 0` cuando se pliega o en móvil.
- En pantallas menores de 576 px reduce tipografía y relleno para seguir por debajo de 75 px.
- `.main` reserva `6rem` de relleno inferior (96 px) para que el pie nunca tape el contenido.
- Aparece en todas las pantallas autenticadas (incluido el panel de administración).

## 13. Presentación de resultados y navegación

### Un dispositivo por IP
Un mismo equipo aparece a la vez en DHCP y en ARP (o en PPPoE y ARP): son la misma IP vista
por dos mecanismos. `MacAddressResolverService::consolidate()` agrupa por **router + IP** y
deja **un solo resultado**:

- mecanismo principal por especificidad: `dhcp_active` > `dhcp_static` > `pppoe_active` >
  `hotspot_binding` > `arp_complete`;
- los demás mecanismos se guardan en `mac_search_results.mechanisms` como confirmación;
- `is_active_match` se hereda si cualquiera de las filas estaba activa;
- se ordenan primero las coincidencias activas, así el resultado principal es siempre el
  primero (`results[0]`).

La búsqueda de `60:D2:DD:95:2A:55` en el router *zona 100* devolvía antes **2 coincidencias
(DHCP + ARP)** y ahora devuelve **1 dispositivo** con ambos mecanismos.

En la interfaz, el resultado principal se muestra como tarjeta con la IP en grande y el botón
**Abrir dispositivo**; si existen IPs distintas (misma MAC en otro router), aparecen en un
desplegable "resultados adicionales" para no competir visualmente.

### Menú lateral
El botón de la barra superior pliega y despliega el menú en escritorio (`html.sidebar-collapsed`),
con transición y estado recordado en `localStorage`. En móvil sigue funcionando como panel
deslizante con overlay.

---

## 14. Diagnóstico de conexión con routers

```powershell
php artisan routers:probe 187.73.177.186 --port=8081 --plain
php artisan routers:probe router.zona100.local --port=443
```

Comprueba en tres pasos, **sin credenciales ni escrituras**: resolución DNS, apertura TCP y
respuesta HTTP de `/rest/`. Interpreta el resultado:

| Respuesta de `/rest/` | Significado |
|---|---|
| `401` / `403` | Red y puerto correctos; el REST existe y pide credenciales → el problema es el usuario o sus permisos |
| `404` | El servidor web responde pero no hay REST (RouterOS < 7.1 o servicio distinto) |
| `200` | Responde sin autenticación (revisar que sea el equipo esperado) |
| sin respuesta | El puerto no habla HTTP, o la red lo bloquea |

Además advierte si se prueba con 8728/8729, que son los puertos de la **API binaria** y no
sirven para REST.

La prueba del panel muestra ahora el `endpoint`, el detalle de curl y una explicación
accionable (`MikrotikException::hint()`), sin exponer la contraseña.

### Puertos y credenciales de RouterOS
- REST usa el **servicio web**: 443 (`www-ssl`) o 80 (`www`); el puerto se configura en
  `/ip/service`. Requiere RouterOS 7.1 o superior.
- La contraseña del router se guarda **cifrada** en la base de datos y se descifra en memoria;
  se envía al equipo con HTTP Basic (cabecera `Authorization: Basic`). Sobre HTTP eso equivale
  a texto plano en la red: **usa `www-ssl` y marca "Usar SSL"** en producción.
