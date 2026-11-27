# Pendientes — IPSearch Interya

> Ordenados por prioridad técnica. Cada entrada indica el porqué y el punto de partida
> concreto en el código. Actualizar al cerrar cada tarea.

Leyenda: **P0** bloqueante · **P1** importante · **P2** mejora · **P3** deseable

---

## P0 — Bloqueantes (ninguno abierto)

Cerrados en la intervención del 2026-11-27 (ver `docs/ESTADO_PROYECTO.md` §9):
bucle de redirección de login, controlador administrativo inexistente, rutas y nombres de
ruta faltantes, escritura de auditoría rota, invitaciones inutilizables, errores fatales de
plantillas.

---

## P1 — Importantes

> **Cerradas el 2026-11-27:** P1-1 (SMTP aplicado al mailer), P1-2 (entrega por correo de
> invitaciones y recuperación), P1-3 (endurecimiento de `device/open`) y P1-4 (sondeo
> programado de salud). Se conservan abajo como registro con su criterio de aceptación
> cumplido. **La siguiente tarea abierta es P1-5.**

### P1-1 · Aplicar la configuración SMTP guardada al mailer — ✅ hecho
- **Por qué:** `/admin/config` guarda `mail_settings` (host, puerto, usuario, contraseña
  cifrada) pero el mailer sigue usando `MAIL_MAILER=log`. Ningún correo sale del sistema.
- **Dónde:** `app/Providers/AppServiceProvider::boot()`. Leer `MailSetting` (activo), descifrar
  la contraseña y aplicar `config([...])` para `mail.mailers.smtp` y `mail.from`.
  Proteger con `Schema::hasTable('mail_settings')` + `try/catch` para no romper
  `migrate`/`config:cache`.
- **Aceptación:** `Mail::fake()` en pruebas; envío real verificable con el panel SMTP.
- **Hecho:** `app/Services/Mail/MailConfigurator.php` aplicado desde `AppServiceProvider::boot()`.
  Cubierto por `tests/Feature/MailAndPasswordTest.php` (aplicación de host/puerto/contraseña,
  `smtps` para SSL y respeto del `.env` cuando no hay fila activa).

### P1-2 · Entregar por correo los enlaces de invitación y recuperación — ✅ hecho
- **Por qué:** hoy el enlace de invitación se muestra en un mensaje flash (solo lo ve el
  administrador) y el reseteo depende del broker con mailer `log`.
- **Depende de:** P1-1.
- **Dónde:** `InvitationService::issue()` (devolver también el modelo para notificar),
  `UserAdminController::resendInvitation`, `RegisteredUserController::storeInvite`.
  Usar una notificación (`Notification`) con el enlace.
- **Aceptación:** invitación y reseteo llegan a la bandeja real; el flash puede dejar de
  exponer el enlace.
- **Hecho:** `InvitationNotification`, `ResetPasswordNotification` y `VerifyEmailNotification`
  en español, registradas en `User`. `InvitationService::issue()` envía y devuelve `mailed`;
  si el envío falla, el enlace queda como respaldo en el flash. La recuperación usa el broker
  nativo con el token caducable.

### P1-3 · Endurecer `GET /device/open/{ip}` — ✅ hecho
- **Por qué:** devuelve la URL de cualquier IP válida, sin comprobar que provenga de una
  búsqueda del usuario ni el rol. Permite construir enlaces a hosts arbitrarios desde el
  dominio corporativo.
- **Dónde:** `SearchController::openDevice`.
- **Cambio propuesto:** exigir que la IP exista en `mac_search_results` de una búsqueda del
  usuario autenticado (o de cualquier búsqueda si es superadmin) y aplicar `throttle`.
- **Aceptación:** IP no asociada → 404; IP válida del historial → 200.
- **Hecho:** valida que la IP exista en `mac_search_results` de una búsqueda del usuario
  (el superadmin puede abrir cualquiera ya registrada), añade `throttle:60,1` y corrige de paso
  la relación `MacSearchResult::search()` (BUG-29). Cubierto por `tests/Feature/DeviceOpenTest.php`.

### P1-4 · Sondeo programado de salud de routers — ✅ hecho
- **Por qué:** `connectivity_status` solo se actualiza al buscar o al pulsar "Probar".
  Un router caído sin tráfico aparece como `unknown`.
- **Dónde:** crear comando `php artisan routers:health` que use
  `MikrotikRestClient::testConnection` por router activo, y programarlo en
  `routes/console.php` (cada 5–15 min).
- **Aceptación:** prueba de comando con cliente simulado; estado y `last_checked_at` se actualizan.
- **Hecho:** `app/Console/Commands/CheckRoutersHealth.php` (`routers:health`, opción `--router=`),
  programado cada 10 minutos con `withoutOverlapping()` en `routes/console.php`.
  Cubierto por `tests/Feature/RouterHealthCommandTest.php`.

### P1-5 · Historial global para superadministrador — SIGUIENTE TAREA
- **Por qué:** el rol superadmin tiene "historial global" en el alcance funcional, pero solo
  existe `/search/history` (propio).
- **Dónde:** nueva ruta `admin/historial` + controlador/vista con filtros por usuario, MAC,
  fecha y estado. Reutilizar el patrón de `AdminSettingsController::audit`.
- **Aceptación:** usuario normal recibe 403; superadmin ve todas las búsquedas con paginación.

---

## P2 — Mejoras

### P2-1 · Vista dedicada de estadísticas
`/admin/estadisticas` devuelve JSON (`AdminDashboardController::stats`). Añadir vista con
gráficos: serie diaria, distribución por mecanismo y top de usuarios, consumiendo ese endpoint.

### P2-2 · Probar conexión sin abandonar la página — ✅ hecho
`admin/routers/index` y `admin/salud` envían ahora la prueba con `fetch` (`partials/router-test.blade.php`)
y muestran el resultado en un alert: versión, board, latencia y endpoint; en caso de fallo, el
detalle de curl y una explicación accionable. Ya no se sale del panel.

### P2-3 · Estado "parcial" visible en la interfaz
`collectSources` informa `partial` (algún endpoint no disponible) pero el resultado no lo
expone al usuario. Mostrar un aviso "cobertura parcial" cuando aplique.

### P2-4 · Registrar en auditoría los intentos bloqueados por rol
Hoy `token`/rol se resuelven a 403 sin dejar rastro. Registrar `access_denied` con ruta,
usuario e IP en el manejador de excepciones.

### P2-5 · Layout de autenticación compartido
Las 4 vistas de auth comparten ya el selector de tema (`partials/theme.blade.php`), pero siguen
repitiendo ~30 líneas de CSS. Extraer a `layouts/auth.blade.php`. Sin cambios visuales.

### P2-6 · `APP_DEBUG=false` y checklist de despliegue
El `.env` local tiene `APP_DEBUG=true` (correcto en local). Antes de publicar:
`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` real, `php artisan config:cache route:cache view:cache`,
HTTPS forzado y `SESSION_SECURE_COOKIE=true`.

### P2-7 · Inicializar Git
El proyecto no tiene repositorio. Antes de seguir: `git init`, `.gitignore` ya existe, primer
commit del estado actual y commits atómicos por cambio.

### P2-8 · Limpiar restos del esqueleto
`resources/views/welcome.blade.php`, `resources/js/*`, `resources/css/app.css`,
`tailwind.config.js`, `postcss.config.js`, `vite.config.js` no se usan. Eliminar si se confirma
que no habrá build con Vite.

---

## P3 — Deseables

- Exportar historial (CSV/XLSX) con los filtros activos.
- Búsqueda por IP o hostname además de MAC.
- Soporte de RouterOS con API binaria (puerto 8728) si algún equipo no expone REST.
- Métricas por router (latencia media, tasa de fallo) en la vista de salud.
- Confirmación de contraseña (`password.confirm`) para acciones sensibles del panel.
- Paginación configurable y retención de historial (política de purga de `mac_searches`).

---

## Verificación rápida antes de cada entrega

```powershell
php artisan test                    # 50+ pruebas, deben pasar todas
php artisan route:list              # no debe lanzar excepciones
php artisan view:cache              # compila todas las plantillas
php -l <archivo modificado>         # sintaxis
```

Y como mínimo una comprobación HTTP real de: login, búsqueda autenticada, acceso invitado
(bloqueado) y panel con rol `user` (403).
