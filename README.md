# Control de incidencias con Docker Compose

Práctica individual de Infraestructura de Servicios. 
Aplicación web para registrar incidencias (título, descripción, estado y prioridad) con cinco servicios en contenedores: un frontend en Nginx que sirve la interfaz y reenvía `/api/` al backend, una API en PHP con Apache, MariaDB como base de datos, Redis como caché y Mailpit para capturar los correos de notificación.

## Arquitectura

```
Navegador ──▶ frontend (Nginx :8081) ──/api/──▶ backend (PHP/Apache)
                                                  │   │   │
                                         db ◀─────┘   │   └────▶ mailpit (SMTP :1025)
                                    (MariaDB 3306)    └──▶ redis (6379)
Navegador ──▶ mailpit (interfaz web :8025)
```

Todos los servicios comparten la red `app_network` y se encuentran por nombre (`db`, `redis`, `mailpit`, `backend`), resuelto por el DNS interno de Docker. Solo se publican dos puertos hacia el equipo anfitrión: 8081 (frontend) y 8025 (interfaz de Mailpit). MariaDB, Redis y el backend no son accesibles desde fuera.

## Requisitos

Docker Engine y Docker Compose v2 o superior (probado con Docker 29.8 y Compose 5.5 en Fedora).

## Ejecución desde cero

```bash
git clone [URL del repositorio]
cd [carpeta]
cp .env.example .env      # edita .env y cambia las contraseñas
docker compose up -d --build
docker compose ps
```

Si usas SELinux (Fedora), el volumen de `init.sql` lleva la opción `:z`, por lo que no necesitas configurar nada más.

## URL de acceso

- Aplicación: http://localhost:8081
- Salud de la API: http://localhost:8081/api/health
- Correos capturados (Mailpit): http://localhost:8025

## Variables de entorno (`.env`)

`MARIADB_ROOT_PASSWORD`, `MARIADB_DATABASE`, `MARIADB_USER` y `MARIADB_PASSWORD`. El archivo `.env` no se versiona; `.env.example` sirve de plantilla.

## API

`GET /incidencias`, `POST /incidencias`, `PUT /incidencias/{id}`, `DELETE /incidencias/{id}` y `GET /health`. Responde 201 al crear, 404 si no existe el registro, 422 si los datos son inválidos y 503 si MariaDB o Redis no están disponibles.

## Redis

El listado se guarda en la clave `incidencias:lista` con TTL de 30 segundos y se invalida en cada creación, edición o eliminación. La clave `contador_consultas` cuenta las consultas al listado. La respuesta incluye la cabecera `X-Cache: HIT` o `MISS`.

## Mejora individual: prioridad

Cada incidencia tiene una prioridad (`baja`, `media` o `alta`) guardada en MariaDB y mostrada con colores en la tabla, donde puede cambiarse directamente. Se agregó con `ALTER TABLE` sobre la base existente y también está en `database/init.sql` para instalaciones nuevas.

## Comandos de verificación

```bash
docker compose ps
docker compose exec redis redis-cli KEYS "*"
docker compose exec redis redis-cli GET contador_consultas
docker compose exec redis redis-cli TTL incidencias:lista
docker compose exec db sh -c 'mariadb -u "$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" -e "SELECT id, titulo, estado, prioridad FROM incidencias;"'
docker compose down        # detiene; los datos se conservan
docker compose down -v     # detiene y borra los volúmenes
```

## Nota

Mailpit es una herramienta de desarrollo: captura los correos sin entregarlos, por lo que no sustituye a un servidor de correo real en producción.
