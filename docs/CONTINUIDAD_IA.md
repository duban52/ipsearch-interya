# Continuidad IA — cómo retomar este proyecto

> Léelo completo antes de tocar código. Está escrito para que un agente o desarrollador
> nuevo entienda el proyecto sin repetir el trabajo ya hecho.

---

## 1. Contexto de traspaso

El proyecto fue iniciado por **Claude Code**, que escribió la práctica totalidad del código
(controladores, modelos, migraciones, servicios, vistas y Middleware) **sin ejecutarlo**:
la base de datos no existía, las migraciones nunca se habían aplicado y el seeder de fábrica
era incompatible con el esquema. La auditoría del 2026-11-27 encontró **28 defectos**, varios
de ellos bloqueantes (la aplicación era inaccesible por un bucle de redirección en `/login`).

Todo eso está corregido y verificado. **El punto de partida real hoy es un sistema que
arranca, autentica, autoriza y busca** (con las limitaciones de `docs/PENDIENTES.md`).

---

## 2. Puesta en marcha desde cero

```powershell
# Requisitos: Laragon con PHP 8.3 y MySQL 8.4 en marcha
cd C:\laragon\www\ipsearch-interya

# 1. Base de datos (si no existe)
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root -h 127.0.0.1 `
  -e "CREATE DATABASE IF NOT EXISTS ipsearch_interya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Esquema + datos iniciales
php artisan migrate
php artisan db:seed        # crea admin@interya.com; imprime la contraseña UNA vez

# 3. Servir
#    Con Laragon: http://ipsearch-interya.test
#    Sin Laragon: php artisan serve --host=127.0.0.1 --port=8000

# 4. Verificación
php artisan test           # 97 pruebas / 346 aserciones

# 5. Tareas programadas (estado de routers cada 10 min)
php artisan schedule:work  # en desarrollo; en producción usar cron: schedule:run
```

Para fijar la contraseña del superadministrador sin aleatoriedad:

```powershell
$env:IPS_ADMIN_PASSWORD = 'UnaClaveRobusta#2026'
php artisan db:seed
```

Variables opcionales del seeder: `IPS_ADMIN_EMAIL`, `IPS_ADMIN_USERNAME`, `IPS_ADMIN_PASSWORD`.

---

## 3. Reglas de trabajo (heredadas del encargo original)

1. **Auditar antes de modificar.** No reescribir módulos que funcionan.
2. **No cambiar de tecnología** ni sustituir Laravel, Blade, Bootstrap o `curl`.
3. **No debilitar la seguridad.** Toda ruta nueva de datos debe ir bajo `['auth','active']`
   (y `role:superadmin` si es administrativa). Los endpoints JSON deben rechazar invitados.
4. **No ejecutar migraciones destructivas** (`migrate:fresh`, `db:wipe`, borrados de datos)
   sin autorización explícita.
5. **No tocar `.env`** ni secretos sin necesidad justificada.
6. **No probar contra routers reales** sin autorización; usar cliente simulado o un router
   de prueba apuntando a un puerto cerrado.
7. **Cada cambio deja pruebas.** Si corriges un defecto, añade la prueba que lo demuestra.
8. **Documentar.** Actualizar `docs/ESTADO_PROYECTO.md` (si cambia el estado) y
   `docs/PENDIENTES.md` (si cierras o abres tareas).

---

## 4. Mapa mental para orientarse rápido

| Necesito… | Voy a… |
|---|---|
| Cambiar la lógica de búsqueda | `app/Services/MacAddressResolverService.php` |
| Cambiar cómo se habla con MikroTik | `app/Services/Mikrotik/MikrotikRestClient.php` |
| Añadir un mecanismo de asignación | `MikrotikRestClient::SOURCES` + `MacAddressResolverService::searchInSources()` + etiqueta en la vista |
| Tocar permisos/rutas | `routes/web.php` + middleware en `app/Http/Middleware/` |
| Cambiar la respuesta JSON de búsqueda | `SearchController::search` y `Api/MacResolverController::search` (mantener ambos) |
| Añadir una pantalla de administración | Controlador en `app/Http/Controllers/Admin/`, vista en `resources/views/admin/`, ruta en el grupo `admin` |
| Cambiar estilos globales | `resources/views/layouts/app.blade.php` (CSS embebido, variables `--primary` etc.) |
| Tocar el correo (SMTP/notificaciones) | `app/Services/Mail/MailConfigurator.php` + `app/Notifications/` |
| Salud de routers / tareas programadas | `app/Console/Commands/CheckRoutersHealth.php` + `routes/console.php` |
| Añadir una prueba | `tests/Feature/` (flujo HTTP) o `tests/Unit/` (lógica pura) |

### Rutas y nombres (contrato con las vistas)
- Buscador: `search.index`, `search.perform`, `search.history`, `search.detail`, `device.open`
- Perfil: `profile.edit`, `profile.update`, `password.update` (PUT, cambio de contraseña autenticado)
- Recuperación: `password.request`, `password.email`, `password.reset`, `password.store` (POST del token)
- Invitación: `invite.accept` (GET), `invite.accept.submit` (POST), `invite.create`, `invite.store`, `invite.resend`
- Admin: prefijo de nombre `admin.` (¡incluidos los recursos! `admin.users.index`, `admin.routers.index`)

> **Trampa vista antes (BUG-04):** `Route::resource('users', ...)` dentro de un grupo con
> `prefix('admin')` **no** añade el prefijo `admin.` a los nombres. Hay que declarar
> `->names('admin.users')`.

---

## 5. Decisiones técnicas tomadas (y por qué)

| Decisión | Motivo |
|---|---|
| `EnsureUserIsActive` **no** va en el grupo `web` global | Aplicado globalmente redirigía `/login` a `/login` (bucle). Se aplica por alias en los grupos protegidos |
| `AuditLog` con `$timestamps = false` | La tabla solo define `created_at` y es un registro inmutable; así se respetó el esquema original en lugar de migrarlo |
| Recuperación de contraseña con el **broker nativo** (tabla `password_reset_tokens`) | La implementación previa guardaba el token en `remember_token`, sin caducidad y sin correo. El broker ya estaba configurado en `config/auth.php` |
| Token de invitación: SHA-256 en BD + enlace en claro una sola vez | Nunca se almacena el secreto utilizable; el enlace se entrega al administrador (hasta que exista envío por correo) |
| `InvitationService` compartido | Evita duplicar la emisión de invitaciones entre `RegisteredUserController` y `UserAdminController` |
| `curl_multi_info_read` para el resultado de cada transferencia | `curl_errno()` no reflejó los fallos en modo multi con este curl 8.7.0-DEV (verificado empíricamente) |
| Añadir `municipality` a `mac_search_results` | El detalle histórico mostraba `—` porque el dato no se guardaba; mismo criterio de desnormalización que `router_name` |
| Hotspot IP bindings como cuarto mecanismo | Estaba en el alcance funcional y no se consultaba; se integra en el mismo `curl_multi` (sin coste extra de latencia) |
| Pruebas con SQLite `:memory:` | Evita que la suite borre la base de datos de desarrollo |
| SMTP administrado en BD, aplicado en `AppServiceProvider::boot()` | El correo debe gestionarlo el superadministrador desde el panel; si no hay fila activa se respeta el `.env` |
| Notificaciones propias en español (`app/Notifications`) | La interfaz es 100 % español; los correos del framework salían en inglés |
| `device/open` valida contra `mac_search_results` | Evita que el endpoint sirva de generador de enlaces hacia hosts arbitrarios |
| `routers:health` cada 10 min con `withoutOverlapping()` | Mantiene el estado de infraestructura al día sin depender del tráfico |
| Tema con `data-bs-theme` y variables CSS | Bootstrap 5.3 adapta sus componentes; nunca usar colores de superficie fijos en las vistas |
| `routers:probe` sin credenciales | Separa red/puerto de credenciales antes de tocar la base de datos |

---

## 6. Trampas del entorno (Windows / Laragon)

- **`Set-Content -Encoding UTF8` en PowerShell 5.1 añade BOM** y rompe los archivos PHP
  (`Namespace declaration statement has to be the very first statement`). Usar el editor/`write`
  o `[System.IO.File]::WriteAllText(..., New-Object System.Text.UTF8Encoding($false))`.
- La política de ejecución de PowerShell bloquea `.ps1`: usar
  `Set-ExecutionPolicy -Scope Process Bypass` en la misma sesión si hace falta.
- La salida de PHP incluye avisos `MIB search path: c:/usr/share/snmp/mibs` por la extensión
  `snmp` mal configurada. Es ruido; filtrar con `Select-String -NotMatch 'MIB|Cannot find module'`.
- `php artisan test` devuelve 0 aunque PowerShell muestre `[exit code: 1]` por los avisos en
  stderr. Comprobar con `php vendor\bin\phpunit` y leer la línea `OK (...)`.
- `encryptString()` se descifra con `Crypt::decryptString()`, **no** con el helper `decrypt()`
  (que hace `unserialize` y falla).

---

## 7. Estado de las pruebas (contrato de no regresión)

`php artisan test` → **97 pruebas, 346 aserciones, todas OK**. Cubren:

| Archivo | Qué protege |
|---|---|
| `tests/Unit/MacNormalizationTest.php` | Normalización y rechazo de MAC inválidas |
| `tests/Feature/AuthenticationTest.php` | Login accesible, sin bucle de redirección, cuentas suspendidas, auditoría de login, logout |
| `tests/Feature/SecurityAccessTest.php` | Invitados bloqueados en 8 rutas y en búsqueda/API; usuario normal 403 en admin; suspensión con sesión viva; salud pública |
| `tests/Feature/MacSearchTest.php` | Estados de búsqueda, mecanismos (DHCP/PPPoE/ARP/hotspot), coincidencias sin IP, router caído, endpoint ausente, aislamiento del historial |
| `tests/Feature/InvitationTest.php` | Alta por invitación, activación pública, token de un solo uso, bloqueo de invitados y de usuarios normales |
| `tests/Feature/MailAndPasswordTest.php` | SMTP aplicado al mailer (`smtp`/`smtps`), envío de invitación y de recuperación, token inválido, suspendido sin enlace |
| `tests/Feature/DeviceOpenTest.php` | Solo se abren IPs del historial propio; 404 para ajenas o inexistentes; superadmin; invitado 401 |
| `tests/Feature/RouterHealthCommandTest.php` | `routers:health` actualiza estados, soporta `--router=` y no falla sin routers |
| `tests/Feature/RouterDiagnosticsTest.php` | La prueba de conexión devuelve pista y detalle por tipo de fallo; `routers:probe` advierte sobre 8728/8729 |
| `tests/Feature/ViewThemeTest.php` | Ninguna vista usa `bg-white`/`table-light`; tema claro por defecto y selector presente |
| `tests/Feature/PanelSmokeTest.php` | Render de todas las pantallas del panel y del usuario, cifrado de credenciales, suspender/reactivar, protección del último superadmin |
| `tests/Feature/ExampleTest.php` | La raíz exige sesión |

Si una prueba falla, **primero** comprobar si el cambio rompió un requisito; no ajustar la
prueba para que pase sin analizar el motivo.

---

## 8. Siguiente paso recomendado

**Cerrado en esta iteración:** P1-1 (SMTP al mailer), P1-2 (correo de invitaciones y
recuperación), P1-3 (endurecer `device/open`) y P1-4 (`routers:health` programado).

1. **P1-5**: historial global para superadministrador (ruta `admin/historial`, filtros por
   usuario/MAC/fecha/estado, 403 para rol `user`).
2. **P2-1**: vista dedicada de estadísticas (consumir `/admin/estadisticas`).
3. **P2-2**: probar conexión con `fetch` en lugar de POST que devuelve JSON.
4. **P2-6 / P2-7**: checklist de producción (`APP_DEBUG=false`, cachés, scheduler) y `git init`.
5. Configurar un SMTP real desde el panel para validar la entrega efectiva de correo
   (hoy verificado con `Notification::fake()` y con un SMTP inalcanzable).

Cada uno tiene su criterio de aceptación en `docs/PENDIENTES.md`.
