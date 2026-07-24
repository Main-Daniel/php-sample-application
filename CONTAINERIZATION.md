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

Adicionalmente, durante las pruebas se detectó que `bootstrap.php`
(el archivo que Apache antepone a cada request vía
`auto_prepend_file`) nunca cargaba el autoloader de Composer, solo el
autoloader propio del proyecto (para clases en `src/`). Esto provocaba
un error 500 (`Class 'Negotiation\Negotiator' not found`) porque la
librería externa `willdurand/negotiation` no llegaba a cargarse.

Original:
```php
<?php

require "autoloader.php";
require "error_handler.php";
```

Modificado:
```php
<?php

if (is_file(__DIR__ . "/vendor/autoload.php")) {
    require __DIR__ . "/vendor/autoload.php";
}

require "autoloader.php";
require "error_handler.php";
```

En total, dos cambios de código fueron necesarios para containerizar
la aplicación: `config-dev/db-connection.php` y `bootstrap.php`.

## 2. Imagen `web` (aplicación)

- Base: `php:7.4-apache` (el README pide PHP >= 7.1; se usa 7.4 porque la
  línea 7.1 ya no recibe builds nuevos en Docker Hub por fin de soporte).
- Multi-stage build: una etapa con la imagen oficial `composer:2` para
  instalar dependencias (`willdurand/negotiation`, `twbs/bootstrap`), y una
  etapa final que copia el código + `vendor/` sobre `php:7.4-apache`.
- Extensiones habilitadas: `pdo`, `pdo_mysql`, `mysqli`.
- Módulos Apache habilitados: `rewrite`, `deflate`, `headers`.
- El VirtualHost (`docker/app/apache-config/000-default.conf`) reproduce
  exactamente la configuración indicada en el README original:
  `DocumentRoot` en `web/`, `AllowOverride All`, `include_path` apuntando
  a la raíz del proyecto, e `Include` del `config/vhost.conf` del propio
  repo.
- Se recrea el symlink `config -> config-dev` que en el proyecto original
  genera el `Makefile`.

## 3. Imagen `db` (base de datos)

- Base: `mariadb:10.6`.
- Se copia `sql/db.sql` (sin modificaciones) a
  `/docker-entrypoint-initdb.d/`, mecanismo estándar de la imagen oficial
  de MariaDB para inicializar el esquema y los datos de ejemplo en el
  primer arranque.
- Usuario y base de datos se crean vía variables de entorno estándar de
  la imagen (`MARIADB_USER`, `MARIADB_PASSWORD`, `MARIADB_DATABASE`),
  igual que el README pedía crear manualmente.

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

## 5. Manejo de credenciales (seguridad)

Ni `docker-compose.yml` ni los Dockerfiles contienen contraseñas en
texto plano. Las credenciales se inyectan mediante variables de
entorno leídas desde un archivo `.env`:

- `.env.example` (este sí está en el repo) documenta qué variables se
  necesitan, con placeholders, **no** con secretos reales.
- `.env` (el archivo real, con contraseñas reales) está excluido vía
  `.gitignore` y **nunca se sube a GitHub**. Se crea localmente antes
  de ejecutar `docker compose up` (las credenciales usadas para la
  evaluación de esta prueba técnica se entregan aparte, en el archivo
  `GUIA_EJECUCION.txt`, que no forma parte del repositorio).
- El contenedor `db` no publica ningún puerto al host (no tiene
  `ports:` en `docker-compose.yml`), por lo que la base de datos solo
  es alcanzable desde el contenedor `web` a través de la red interna
  de Docker Compose, nunca desde fuera de la máquina.
- El usuario de la aplicación (`sampleuser`) tiene permisos únicamente
  sobre la base `sample`; la aplicación nunca se conecta con el
  usuario `root` de MariaDB (principio de menor privilegio).
- Las contraseñas usadas son generadas aleatoriamente (20 caracteres),
  distintas para `root` y para `sampleuser`, en vez de los valores de
  ejemplo débiles del README original (`samplepass`).

Nota: por ser una aplicación de ejemplo (datos ficticios, sin
información sensible real), el riesgo residual es bajo, pero se
aplican estas prácticas para reflejar cómo se manejaría en un entorno
productivo real.

## 6. Build, publicación y ejecución

```bash
# Build y push (una sola vez, o cuando cambie el código)
docker login
docker build -f docker/app/Dockerfile -t maindannyob/php-sample-app-web:latest .
docker build -f docker/db/Dockerfile  -t maindannyob/php-sample-app-db:latest .
docker push maindannyob/php-sample-app-web:latest
docker push maindannyob/php-sample-app-db:latest

# Ejecución
cp .env.example .env
# editar .env con credenciales reales
docker compose up -d
```

La aplicación queda disponible en `http://localhost:8080/`.

## 7. Repositorio

- Fork público: `<PEGAR_AQUÍ_LINK_DE_TU_FORK>`
- Este documento: `<PEGAR_AQUÍ_LINK_A_ESTE_ARCHIVO_EN_TU_FORK>`
