# ================================================================
# IMAGEN: maindannyob/php-sample-app-db
# Contiene: MariaDB con el esquema y datos de ejemplo precargados
#           (NO contiene la aplicacion PHP)
#
# Construir desde la RAIZ del repositorio con:
#   docker build -f docker/db/Dockerfile -t maindannyob/php-sample-app-db:latest .
# ================================================================

FROM mariadb:10.6

# sql/db.sql (contexto = raiz del repo) se ejecuta automaticamente
# en el primer arranque del contenedor (mecanismo estandar de la
# imagen oficial de MariaDB) y crea el esquema + datos de ejemplo.
COPY sql/db.sql /docker-entrypoint-initdb.d/db.sql
