# IPSearch Interya

Plataforma interna de Interya para resolver **MAC → IP** consultando en paralelo los routers
**MikroTik RouterOS 7** de la infraestructura a través de su API REST.

El acceso es **privado**: toda consulta exige sesión iniciada y cuenta activa. No existe
registro libre; las cuentas se crean por invitación desde el panel de superadministración.

## Requisitos

- PHP 8.3 con extensiones `curl`, `openssl`, `pdo_mysql`, `mbstring`
- MySQL 8.4 (Laragon) y Composer
- Laravel 11 · Bootstrap 5.3 (CDN) — no requiere compilar assets

## Instalación

```powershell
composer install
# Crear la base de datos si no existe
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root -h 127.0.0.1 `
  -e "CREATE DATABASE IF NOT EXISTS ipsearch_interya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate
php artisan db:seed      # crea el superadministrador e imprime su contraseña una sola vez

php artisan serve        # o abrir http://ipsearch-interya.test con Laragon
```

La configuración de base de datos y aplicación vive en `.env`. Para fijar la contraseña inicial
sin aleatoriedad: `$env:IPS_ADMIN_PASSWORD = 'TuClave#2026'; php artisan db:seed`.

## Uso

1. Iniciar sesión con la cuenta de superadministrador.
2. **Routers** → dar de alta cada MikroTik (host, puerto API 8729, usuario de solo lectura y
   contraseña, que se guarda cifrada) y usar **Probar** para validar la conexión.
3. **Buscador MAC** → ingresar la MAC en cualquiera de los formatos `AA:BB:CC:DD:EE:FF`,
   `AA-BB-CC-DD-EE-FF` o `AABBCCDDEEFF`.
4. El resultado indica IP, router, municipio, mecanismo (PPPoE, DHCP dinámico/estático, ARP,
   hotspot binding), estado y hostname, y permite abrir la interfaz web del dispositivo.
5. **Usuarios** → crear cuentas directamente o **Invitar** (el usuario define su contraseña
   con un enlace de un solo uso válido 7 días).

## Operación

```powershell
php artisan routers:health       # comprueba conectividad y actualiza el estado de los routers
php artisan routers:probe 1.2.3.4 --port=8081 --plain   # diagnóstico de red/puerto/REST
php artisan schedule:work        # en desarrollo; en producción: cron con schedule:run
```

El correo se configura desde **Configuración → SMTP** en el panel (se guarda cifrado y se aplica
al mailer). Con eso funcionan el enlace de activación de invitaciones y la recuperación de
contraseña.

### Conexión con los routers

La API REST la sirve el **servicio web** de RouterOS: puerto **443** (`www-ssl`) o **80**
(`www`), configurable en `/ip/service`. Los puertos 8728/8729 son de la API binaria y **no**
sirven para REST. Requiere RouterOS 7.1 o superior y un usuario con permisos de solo lectura.

Se recomienda HTTPS: con HTTP la contraseña viaja en Base64 (texto plano en la red).

## Pruebas

```powershell
php artisan test
```

Las pruebas usan SQLite en memoria y nunca tocan la base de datos de desarrollo.

## Documentación

| Documento | Contenido |
|---|---|
| [docs/ESTADO_PROYECTO.md](docs/ESTADO_PROYECTO.md) | Estado real, arquitectura, seguridad, defectos corregidos |
| [docs/PENDIENTES.md](docs/PENDIENTES.md) | Tareas priorizadas con criterios de aceptación |
| [docs/CONTINUIDAD_IA.md](docs/CONTINUIDAD_IA.md) | Cómo retomar el proyecto, decisiones y trampas del entorno |

## Seguridad

- Roles `superadmin` y `user`; autorización comprobada en el backend.
- CSRF, throttle en login y búsqueda, contraseñas con bcrypt y política mínima de 10 caracteres.
- Credenciales de router y SMTP cifradas con la `APP_KEY`; nunca se muestran ni se registran.
- Las cuentas suspendidas son expulsadas aunque tengan la sesión abierta.

Antes de desplegar en producción: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS y
`php artisan optimize`. Ver `docs/PENDIENTES.md` (P2-6).
