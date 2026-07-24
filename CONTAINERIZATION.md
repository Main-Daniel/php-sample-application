# Guía de containerización — php-sample-application

Este documento explica los cambios realizados sobre el repositorio original
(`patrickallaert/php-sample-application`) para poder ejecutarlo en Docker,
y cómo se construyeron y publicaron las imágenes.

## 1. Por qué fue necesario un fork

El código original resuelve la conexión a la base de datos en
`config-dev/db-connection.php` con valores fijos:

```php
return new PDO("mysql:host=localhost;dbname=sample", "sampleuser", "samplepass", ...);
```

Al correr la aplicación en un contenedor separado del de la base de datos,
`localhost` ya no apunta a MariaDB (apunta al propio contenedor web). Fue
necesario modificar ese archivo para leer host/usuario/password/BD desde
variables de entorno, manteniendo los mismos valores por defecto del README
para no romper el uso local tradicional:

```php
return new PDO(
    sprintf("mysql:host=%s;dbname=%s", getenv("DB_HOST") ?: "localhost", getenv("DB_NAME") ?: "sample"),
    getenv("DB_USER") ?: "sampleuser",
    getenv("DB_PASS") ?: "samplepass",
    [PDO::ATTR_PERSISTENT => true]
);
```

Es el único cambio funcional en el código de la aplicación.

## 2. Imagen `web` (aplicación)

- Base: `php:7.4-apache` (el README pide PHP >= 7.1; se usa 7.4 porque la
  línea 7.1 ya no recibe builds nuevos en Docker Hub por fin de soporte).
- Multi-stage build: una etapa con la imagen oficial `composer:2` para
  instalar dependencias (`willdurand/negotiation`, `twbs/bootstrap`), y una
  etapa final que copia el código + `vendor/` sobre `php:7.4-apache`.
- Extensiones habilitadas: `pdo`, `pdo_mysql`, `mysqli`.
- Módulos Apache habilitados: `rewrite`, `deflate`, `headers`.
- El VirtualHost (`apache-config/000-default.conf`) reproduce exactamente
  la configuración indicada en el README original: `DocumentRoot` en
  `web/`, `AllowOverride All`, `include_path` apuntando a la raíz del
  proyecto, e `Include` del `config/vhost.conf` del propio repo.
- Se recrea el symlink `config -> config-dev` que en el proyecto original
  genera el `Makefile`.

## 3. Imagen `db` (base de datos)

- Base: `mariadb:10.6`.
- Se copia `sql/db.sql` (sin modificaciones) a
  `/docker-entrypoint-initdb.d/`, mecanismo estándar de la imagen oficial
  de MariaDB para inicializar el esquema y los datos de ejemplo en el
  primer arranque.
- Usuario/base de datos (`sampleuser` / `sample` / `samplepass`) se crean
  vía variables de entorno estándar de la imagen (`MARIADB_USER`,
  `MARIADB_PASSWORD`, `MARIADB_DATABASE`), igual que el README pedía
  crear manualmente.

## 4. Estructura en el repositorio

Ambos Dockerfiles se ubican en carpetas separadas y claramente
nombradas para no confundirlos, dentro de una carpeta `docker/`
nueva en la raíz del repo:

```
docker/
├── app/
│   ├── Dockerfile              -> construye la imagen de la APLICACION
│   └── apache-config/000-default.conf
└── db/
    └── Dockerfile              -> construye la imagen de la BASE DE DATOS
```

El contexto de build de ambos es la **raíz del repositorio** (no la
carpeta `docker/app` ni `docker/db`), para poder copiar `composer.json`,
`web/`, `src/` (en el caso de la app) y `sql/db.sql` (en el caso de la
BD) sin duplicar archivos.

## 5. Build y publicación en Docker Hub

```bash
# Desde la raíz del fork clonado
docker login

docker build -f docker/app/Dockerfile -t maindannyob/php-sample-app-web:latest .
docker build -f docker/db/Dockerfile  -t maindannyob/php-sample-app-db:latest .

docker push maindannyob/php-sample-app-web:latest
docker push maindannyob/php-sample-app-db:latest
```

## 5. Manejo de credenciales (seguridad)

Ni `docker-compose.yml` ni los Dockerfiles contienen contraseñas en
texto plano. Las credenciales se inyectan mediante variables de
entorno leídas desde un archivo `.env`:

- `.env.example` (este sí está en el repo) documenta qué variables se
  necesitan, con valores de ejemplo, **no** con secretos reales.
- `.env` (el archivo real, con contraseñas reales) está excluido vía
  `.gitignore` y **nunca se sube a GitHub**. Cada quien crea el suyo
  localmente antes de ejecutar `docker compose up`.
- El puerto de MariaDB no se publica al host (no hay `ports:` en el
  servicio `db`), por lo que la base de datos solo es alcanzable
  desde el contenedor `web` a través de la red interna de Compose.
- El usuario de la aplicación (`sampleuser`) tiene permisos únicamente
  sobre la base `sample`, no es el usuario `root` de MariaDB
  (principio de menor privilegio).

## 6. Ejecución

```bash
docker compose up -d
```

La aplicación queda disponible en `http://localhost:8080/`.

## 6. Gestión de credenciales (buenas prácticas)

La primera versión de este entregable tenía las credenciales de la
base de datos escritas directamente en `docker-compose.yml`. Se
corrigió por lo siguiente:

- **Problema**: credenciales en texto plano dentro de un archivo que
  se sube a un repositorio público (fork) quedan expuestas a
  cualquiera que vea el repo.
- **Solución aplicada**: `docker-compose.yml` ahora referencia
  variables de entorno (`${DB_USER}`, `${DB_PASSWORD}`,
  `${DB_ROOT_PASSWORD}`, etc.) que Docker Compose toma automáticamente
  de un archivo `.env` local.
- `.env.example` sí se versiona en el repo, como plantilla, pero sin
  valores reales.
- `.env` (con las contraseñas reales) está excluido vía `.gitignore`
  y nunca se sube a GitHub; cada quien lo genera localmente a partir
  de la plantilla.
- Adicionalmente, el contenedor `db` no expone su puerto al host
  (no tiene `ports:` en `docker-compose.yml`), por lo que solo es
  alcanzable desde el contenedor `web` dentro de la red interna de
  Docker Compose, no desde fuera de la máquina.

Nota: por ser una aplicación de ejemplo (datos ficticios, sin
información sensible real), el riesgo residual es bajo, pero se
aplican estas prácticas para reflejar cómo se manejaría en un entorno
productivo real.

## 7. Repositorio

- Fork público: `<PEGAR_AQUÍ_LINK_DE_TU_FORK>`
- Este documento: `<PEGAR_AQUÍ_LINK_A_ESTE_ARCHIVO_EN_TU_FORK>`
