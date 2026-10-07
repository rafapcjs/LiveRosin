# Live Rosin — Tienda con catálogo público y panel de administración

Tienda web para vender destilados, vapers y otros productos **por WhatsApp** (sin pago en línea ni carrito).
Tiene dos partes:

- **Catálogo público** (`/`): lo ven los clientes desde el celular. Pide confirmar mayoría de edad,
  muestra los productos con su precio y estado, y cada pedido se envía por WhatsApp con el mensaje listo.
- **Panel privado** (`/admin`): para el dueño. Ventas, compras, inventario, productos, categorías,
  ajustes, administradores y registro de seguridad.

---

## Con qué está hecho

| Parte | Tecnología |
|---|---|
| Lenguaje y framework | **PHP 8.3** + **Laravel 12** |
| Base de datos | **PostgreSQL 16** |
| Pantallas | **Blade** (HTML del servidor). Sin Node, sin npm, sin React/Vue, sin Tailwind |
| Diseño | CSS propio escrito a mano en `public/css/app.css` (mobile first, variables CSS, flexbox y grid) |
| Interacción | JavaScript propio y mínimo en `public/js/app.js` (sin librerías) |
| Excel | [OpenSpout](https://github.com/openspout/openspout) (`.xlsx`) |
| Fotos | [Intervention Image](https://image.intervention.io/) con GD (reduce a 1200 px y crea miniatura `.webp`) |
| Pruebas | [Pest](https://pestphp.com/) (más de 120 pruebas automáticas) |
| Tipografías | Anton, Barlow Condensed y Special Elite, guardadas en `public/fonts` (licencia OFL) |
| Entorno local | Docker (PHP 8.3 + PostgreSQL 16) |

Idioma español de Colombia, zona horaria `America/Bogota`, dinero en **pesos enteros** (sin decimales)
mostrado como `$ 1.250.000`.

---

## Qué hace

### Catálogo público (`/`)
- **Control de edad en el servidor**: sin confirmar "Sí, soy mayor de 18" no se envía ningún producto. Se recuerda 30 días.
- Barra fija con **menú ☰** (categorías y secciones) y **buscador** que filtra mientras se escribe.
- Filtro por categoría **instantáneo** (sin recargar) y con historial: el botón "atrás" funciona.
- Tarjetas con foto, precio y estado: **Disponible**, **Últimas unidades** o **Agotado**.
- **Ventana de detalle** del producto con selector de cantidad y total; el pedido llega por WhatsApp con
  producto, descripción, cantidad, precio unitario y total.
- Secciones **Quiénes somos**, **Cómo pedir** y **Contacto**. Responsive para teléfono y PC.
- **Privacidad**: el catálogo nunca envía costo, ganancia, margen, stock exacto ni ventas (lo vigilan pruebas automáticas).

### Panel privado (`/admin`)
- **Ventas**: registrar (descuenta stock), anular (devuelve stock), nota opcional, resumen de hoy / semana / mes,
  filtros por fechas y **Excel**.
- **Compras**: entradas de mercancía que suben el stock; resumen, filtros y Excel.
- **Inventario por lotes (PEPS)**: cada compra es un lote con su propio costo; cada venta toma primero las
  unidades más antiguas y guarda su **costo real**. Ajustes de inventario para daños, pérdidas o correcciones.
- **Productos**: foto, precio, calculadora de margen y ganancia. El stock solo cambia con compras, ventas o ajustes.
- **Categorías**, **alertas de stock** y **Ajustes** (nombre de la tienda, frase, WhatsApp, umbral de alerta).
- **Administradores**: crear, cambiar contraseñas, habilitar/deshabilitar y eliminar.
- **Seguridad**: registro de cada intento de entrar y aviso de intentos fallidos.

---

## Cómo levantarlo en tu computador (con Docker)

Necesitas **Docker Desktop** y **Git**. No hace falta instalar PHP ni PostgreSQL.

```bash
# 1. Descargar el proyecto
git clone git@github.com:rafapcjs/LiveRosin.git
cd LiveRosin

# 2. Crear tu archivo de configuración
cp .env.example .env
#    Edita .env: pon DB_HOST=db y escribe ADMIN_EMAIL y ADMIN_PASSWORD

# 3. Encender los contenedores (la primera vez tarda unos minutos)
docker compose up -d

# 4. Solo la primera vez: instalar librerías, preparar la base de datos y datos de ejemplo
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
```

Listo:ñ

- Catálogo: <http://localhost:8000>
- Panel: <http://localhost:8000/admin> (entra con el `ADMIN_EMAIL` y `ADMIN_PASSWORD` de tu `.env`)

Para apagarlo: `docker compose down` (tus datos se conservan).
Para volver a encenderlo: `docker compose up -d`.

Desde el celular (en el mismo wifi): `http://IP-DE-TU-PC:8000`.

### Sin Docker
Necesitas PHP 8.3 (extensiones `pgsql`, `pdo_pgsql`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `bcmath`, `intl`),
Composer y PostgreSQL 16. Crea la base de datos, configura `DB_*` en `.env` y ejecuta los mismos comandos
del paso 4 sin `docker compose exec app`, y luego `php artisan serve`.

---

## Comandos útiles

En tu computador, antepón `docker compose exec app` a cada comando.

| Qué quieres hacer | Comando |
|---|---|
| Crear el administrador con los datos del `.env` | `php artisan tienda:crear-admin` |
| Crear otro administrador con usuario | `php artisan tienda:crear-usuario dealer3` |
| Cambiar la contraseña de un administrador | `php artisan tienda:cambiar-clave dealer3` |
| Correr las pruebas automáticas | `php artisan test` |
| Aplicar cambios de base de datos | `php artisan migrate` |

Los administradores también se gestionan desde el panel: **Ajustes → Administradores**.

---

## Configuración (`.env`)

| Variable | Para qué sirve |
|---|---|
| `APP_URL` | Dirección de la página (en el servidor, `https://tudominio.com`) |
| `APP_DEBUG` | `true` solo en tu computador; **`false` en el servidor** |
| `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexión a PostgreSQL (`DB_HOST=db` con Docker) |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Primer administrador (`tienda:crear-admin`) |
| `ADMIN_IDLE_MINUTES` | Minutos sin usar el panel antes de cerrar la sesión (30) |
| `RATE_PUBLIC_PER_MINUTE`, `RATE_ADMIN_PER_MINUTE` | Límite de visitas por minuto por persona |
| `STORE_NOINDEX` | `true` para pedir a Google que no muestre el catálogo |
| `SESSION_SECURE_COOKIE` | `true` en el servidor con HTTPS |
| `MAIL_*` | Correo para los avisos de seguridad |

El nombre de la tienda, la frase, el WhatsApp y el umbral de "últimas unidades" se cambian en el panel: **Ajustes**.

---

## Seguridad

- Todo `/admin` exige sesión; una prueba revisa que ninguna ruta del panel quede abierta.
- Sin registro público ni "olvidé mi contraseña". Entrada con usuario o correo.
- **5 intentos fallidos** → bloqueo de 5 minutos y correo de aviso. Cada intento queda en **Ajustes → Seguridad**.
- **Cierre de sesión por inactividad** (30 min) con aviso "Seguir conectado" 2 minutos antes.
- Límite de visitas por minuto (en Laravel y en Nginx: `deploy/nginx/tienda.conf`).
- Protección CSRF, escape de todo lo que se muestra (XSS), consultas seguras, cabeceras de seguridad (CSP, etc.).
- Fotos validadas y re-generadas (se elimina cualquier contenido oculto).
- Ventas y compras con transacciones y bloqueo de fila: dos ventas a la vez no dejan el stock en negativo.

**Al publicar**: `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` con HTTPS, contraseñas propias y fuertes,
copias de seguridad diarias y la tarea programada:

```
* * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

---

## Cómo está organizado

| Carpeta o archivo | Qué contiene |
|---|---|
| `app/Http/Controllers` | Pantallas del catálogo y del panel (`Admin/`) |
| `app/Http/Requests/Admin` | Reglas y mensajes de validación de los formularios |
| `app/Http/Middleware` | Control de edad, cierre por inactividad, cabeceras de seguridad |
| `app/Services` | Lógica de negocio: ventas, compras, inventario por lotes, fotos, Excel, avisos |
| `app/Catalog/CatalogItem.php` | Lo único que el catálogo público sabe de un producto (privacidad) |
| `app/Models` | Tablas de la base de datos |
| `database/migrations` | Estructura de la base de datos |
| `resources/views` | Pantallas en Blade (`catalog/` público, `admin/` panel) |
| `public/css/app.css` | Todo el diseño |
| `public/js/app.js` | Todo el JavaScript |
| `lang/es` | Mensajes en español |
| `tests` | Pruebas automáticas (Pest) |
| `docker/`, `compose.yaml` | Entorno local con Docker |
| `deploy/nginx/tienda.conf` | Configuración de Nginx para el servidor |

---

## Subir cambios a GitHub

```bash
git add .
git commit -m "Describe aquí el cambio"
git push
```

## Actualizar el servidor después de subir cambios

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
```
