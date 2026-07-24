<?php
 
// MODIFICADO para containerización:
// El original tenía host=localhost y credenciales fijas, lo cual no
// funciona cuando la base de datos vive en otro contenedor. Ahora los
// valores se leen de variables de entorno (con los mismos defaults que
// el README original), para que la app funcione igual en local y en Docker.
 
return new PDO(
    sprintf(
        "mysql:host=%s;dbname=%s",
        getenv("DB_HOST") ?: "localhost",
        getenv("DB_NAME") ?: "sample"
    ),
    getenv("DB_USER") ?: "sampleuser",
    getenv("DB_PASS") ?: "samplepass",
    [PDO::ATTR_PERSISTENT => true]
);
