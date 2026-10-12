# CLAUDE.md — Mera Universidad

Plataforma interna de cursos (Mera Corporation). Un panel administrativo permite subir cursos empaquetados en ZIP (HTML/JS autocontenido: Storyline, Rise, iSpring…), clasificarlos en categorías jerárquicas y publicarlos en un catálogo (`/cursos`) y en una API pública (`/api/courses`).

---

## 0. REGLAS OBLIGATORIAS (leer antes de cualquier acción)

0. **Idioma: todas las respuestas al usuario son SIEMPRE en español**, incluidos resúmenes, guías de despliegue, mensajes intermedios y notas de resultados. El código y los comentarios siguen las convenciones de §3.
1. **Nunca hacer `git commit` ni `git push` sin la autorización explícita del usuario. Nunca de forma automática.** Se hacen los cambios, se muestra el resumen o diff y se espera un "sí" explícito. Una autorización vale para un solo commit o push: el siguiente requiere pedirla de nuevo. Esto también aplica a merge, rebase, tags y cualquier operación que modifique el remoto.
2. **Antes de borrar, sobrescribir o modificar datos, generar un backup.** Esto aplica a:
   - **Base de datos:** antes de `migrate`, `migrate:fresh`, `migrate:rollback`, `db:wipe`, `db:seed`, `DROP`, `TRUNCATE`, `DELETE`/`UPDATE` masivos, importaciones o restauraciones:
     ```bash
     /c/xampp/mysql/bin/mysqldump.exe -u <user> [-p<pass>] --routines --triggers merabl_cursos \
       > storage/backups/merabl_cursos_<motivo>_$(date +%Y%m%d_%H%M%S).sql
     ```
   - **Archivos de cursos:** antes de borrar o mover carpetas de `storage/app/public/cursos`, copiarlas a `storage/backups/` (la app ya manda los cursos borrados a una papelera; ver §3).
   - **Código:** antes de borrar archivos versionados, copiarlos a `storage/backups/code_removed_<fecha>/` y **pedir confirmación al usuario**.
   - Verificar que el backup existe y no está vacío **antes** de continuar. `storage/` está en `.gitignore`, así que los backups nunca se suben al repo.
3. **Pruebas solo contra BD aislada.** `phpunit.xml` fuerza `sqlite :memory:`, y `tests/TestCase.php` **aborta** si la conexión no es `:memory:` o una BD `*_testing`. No quitar ese candado. Historia: el 2026-10-08 una corrida de pruebas vació `merabl_cursos` (se restauró desde un dump).
4. **No dejar la configuración cacheada en desarrollo** (`bootstrap/cache/config.php`): hace que Laravel ignore `.env` y `phpunit.xml`. Si existe, `php artisan config:clear`. `config:cache` va solo en producción.
5. El `.env` local apunta al MySQL de XAMPP (BD de prueba). Nunca apuntarlo a producción. Si alguna vez apunta a un host remoto, detenerse y preguntar.
6. Antes de una acción irreversible o que salga del equipo (deploy, servicios externos, borrar ramas), confirmar con el usuario.

---

## 1. Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.2 local (XAMPP) / **PHP 8.1.34 en producción** · **Laravel 10.48** (⚠ fuera de soporte; ver §7), Sanctum 3. El código debe seguir siendo compatible con PHP 8.1 hasta que se suba la versión del servidor |
| Autorización | spatie/laravel-permission 6 + `App\Policies\UserPolicy` |
| Auditoría | spatie/laravel-activitylog 4 (Course, Category, User) → `/activity-logs` |
| Frontend | Plantilla "pcoded" sobre **Bootstrap 5.3.3** + jQuery (en `public/`) · Vite 6, Tailwind 3 (solo login/Breeze y utilidades), Alpine, axios, SweetAlert 2.1.2 (npm, versión fija) |
| BD | MariaDB 10.4 local / MySQL 8 en producción (cPanel/CloudLinux). Base: `merabl_cursos` |
| Idioma | `locale=es` (`lang/es/*`, `lang/es.json`). Fechas guardadas en UTC y mostradas en `America/Mexico_City` (`config('mera.display_timezone')`) |
| Marca | Línea de diseño MERA (§8): Montserrat + paleta oficial del media kit |

## 2. Comandos

```bash
composer install && npm install
npm run dev                       # desarrollo (sin esto o sin build, @vite da error 500)
npm run build                     # producción → public/build
php artisan storage:link          # public/storage -> storage/app/public
php artisan mera:harden-storage   # .htaccess anti-PHP en storage/app/public (idempotente)
php artisan migrate               # ⚠ backup antes (regla 2)
php artisan db:seed               # roles + Super Admins desde SEED_SUPERADMIN_EMAILS (sin contraseñas en código)
php artisan test                  # seguro: sqlite :memory: + candado en TestCase
./vendor/bin/pint                 # formato
php artisan mera:purge-course-trash [--dry-run] [--days=30]
php artisan mera:rotate-password --super-admins   # o con correos; muestra las contraseñas una sola vez
```

Cron en el servidor (necesario para la purga de papelera y la limpieza del activity log):
`* * * * * cd /ruta/app && php artisan schedule:run >> /dev/null 2>&1`

## 3. Arquitectura

```
routes/web.php
  /                      → redirect /login
  /cursos                → CatalogController (público)
  /<programa>            → ProgramController (público). Programas definidos en config('mera.programs');
                           hoy: /proceso-de-manipulacion-de-alimentos
  auth:                  /profile (cualquier usuario autenticado)
  role super admin|admin → dashboard, users, categories, courses
  role super admin       → roles, permissions, activity-logs
routes/auth.php          Breeze SIN registro público (las cuentas las crea un admin). Login con throttle.
routes/api.php           GET /api/courses (pública, cacheada; formato original + campos extra)

app/Services/Courses/
  CourseStorage          Rutas seguras (resolve() impide salir de cursos/ o apuntar a la raíz),
                         .htaccess, papelera, límite real de subida (app vs php.ini)
  CoursePackageExtractor Valida y extrae el ZIP en carpeta PRIVADA: lista blanca de extensiones,
                         anti zip-slip/symlinks/rutas absolutas, límite de archivos y tamaño, index.html
  CourseManager          create/update/delete "todo o nada": prepara lo nuevo → guarda en BD →
                         retira lo viejo a la papelera. Recibe rutas, no el Request (listo para un Job)
app/Http/Requests/       CourseRequest, CategoryRequest, UserRequest (validación + autorización)
app/Policies/UserPolicy  Reglas de super admin / admin / último super admin
app/Models/
  Course                 slug único (HasUniqueSlug), cover_url/content_url, scopes search / inCategoryTree
  Category               árbol desde UNA consulta cacheada (flat/tree/pathMap/descendantIdsOf)
  User                   $hidden password, roles PANEL_ROLES, LogsActivity
app/Support/Like         LIKE escapado y portable (ESCAPE '!')
config/mera.php          límites, extensiones permitidas, rutas, URL base de cursos, caché API y **programas**
                         (módulos resueltos por slug del curso, ruta de respaldo, evaluación/encuesta)
resources/js/modules/    flash.js (mensajes por JSON), confirm.js (data-confirm), upload.js (progreso)
resources/views/partials page-header, flash, form-errors, delete-button, user-menu, cover (respaldo de portada), brand-fonts
docs/wordpress/          Plantilla PHP del catálogo de meracorporation.com/cursos-university (tema Divi-child), README con
                         instrucciones de sustitución, respaldo de la plantilla anterior y alternativa en JS
```

### Almacenamiento de cursos
- `storage/app/public/cursos/<carpeta>/index.html`: contenido. Los cursos nuevos usan una carpeta **inmutable** `curso-XXXXXXXXXX`; renombrar un curso **no mueve archivos**. Los cursos antiguos conservan carpetas con nombre de slug.
- `storage/app/public/cursos/<carpeta>-cover-xxxxxx.(jpg|png|webp)`: portada, con la extensión detectada por contenido. Algunos cursos antiguos guardan `cover.png` dentro de su carpeta; al reemplazar el ZIP, la portada se rescata.
- `storage/app/tmp/course-uploads/`: extracción temporal (privada).
- `storage/app/trash/courses/`: papelera. Se purga a los 30 días.
- `storage/app/public/.htaccess`: bloquea `.php`/`.phtml`/`.phar`… (respuesta 403) y archivos ocultos; envía `nosniff`. Lo regenera `mera:harden-storage` (**versión v2**: usa `RewriteRule … [F]` porque LiteSpeed ignora `RemoveHandler`/`Require`; el comando detecta la versión anterior y la reescribe). Mismo criterio en `public/pages/.htaccess`.

### Convenciones
- Código y comentarios en español; clases y métodos en inglés.
- Estilos propios con prefijo `mera-` en `resources/css/app.css`. No editar `public/css/style.css` (plantilla).
- **Bootstrap 5** (no 4): usar `ms-/me-`, `text-md-end`, `visually-hidden`, `form-select`. Las clases BS4 viejas (`mr-2`, `text-right`) solo funcionan porque Tailwind las define.
- Mensajes al usuario: `->with('success'|'error', …)`; los pinta `partials/flash` + `flash.js`. **Nunca** interpolar datos en `<script>` con `{{ }}`.
- Formularios destructivos: `@include('partials.delete-button', …)` o `<form data-confirm="…">`.
- Búsquedas de texto: `App\Support\Like::contains()`.
- Lógica de archivos de cursos: **solo** vía `CourseStorage` / `CourseManager`.
- Al tocar un controlador: FormRequest + Policy, sin `$request->all()`.

## 4. Estado de la auditoría (actualizado 2026-10-12)

✅ = resuelto y cubierto por pruebas · 🟡 = mitigado o parcial · ⏳ = pendiente (requiere decisión o autorización)

### Seguridad
| # | Hallazgo | Estado |
|---|---|---|
| S1 | RCE por `.php` dentro del ZIP | ✅ Lista blanca de extensiones + `.htaccess` (verificado en Apache: 403, no se ejecuta) |
| S2 | Portada con extensión del cliente y SVG | ✅ `mimes:jpg,jpeg,png,webp` + extensión detectada por contenido |
| S3 | Slug vacío borraba **todos** los cursos | ✅ Slug nunca vacío + `CourseStorage::resolve()` + carpetas inmutables |
| S4 | Contraseñas de super admin en `UserSeeder`. **El repo de GitHub es PÚBLICO**, así que están expuestas | 🟡 Eliminadas del código. ✅ Rotadas en producción el 2026-10-12 (`mera:rotate-password --super-admins`). Cada persona debe cambiar la suya en "Mi perfil". ⏳ Hacer privado el repo (delegado: D2, §6) |
| S5 | JS de los cursos en el mismo origen que el panel | 🟡 Hoy panel y cursos comparten `cursos.meracorporation.com`. `MERA_COURSES_URL` permite moverlos a otro subdominio (p. ej. `contenido.`) sin tocar código. ⏳ Decisión pendiente |
| S6 | Escalada de privilegios (admin → super admin) | ✅ UserPolicy, roles del sistema protegidos, último super admin protegido |
| S7 | Registro público abierto | ✅ Rutas eliminadas (404) |
| S8 | Contenido de cursos sin control de acceso | ⏸ **Delegado (D1, §6):** se consultó por correo si los cursos deben ser públicos o solo para personal de MERA. Hoy son públicos para **cualquiera en internet**, sin login: `/cursos`, `/api/courses`, la página de WordPress y los enlaces directos `/storage/cursos/<carpeta>/index.html`. `/cursos` no tiene `noindex`. Opciones: (a) públicos + `noindex`; (b) solo personal: login de colaboradores (ideal SSO Microsoft/Google), servir `storage/cursos` vía Laravel con sesión y adaptar WordPress; (c) restricción por IP (deja fuera remoto/móvil) |
| S9 | Bomba ZIP | ✅ Límites de archivos y de tamaño descomprimido |
| S10 | Zip-slip incompleto | ✅ Rutas absolutas, `\`, `..` y symlinks rechazados |
| S11 | Fuga de `$e->getMessage()` | ✅ Mensajes genéricos; el detalle va al log |
| S12 | Extracción temporal en zona pública | ✅ `storage/app/tmp` (privado) |
| S13 | SweetAlert por CDN sin versión | ✅ npm 2.1.2 vía Vite |
| S14 | `LIKE` sin escapar | ✅ `App\Support\Like` |
| S15 | Rutas de lounges activas | ✅ Rutas quitadas; tablas vacías eliminadas (migración con guarda) |
| S16 | `User` sin `$hidden`: `/api/user` devolvía el hash de la contraseña | ✅ |
| S17 | Dependencias vulnerables | 🟡 npm: axios actualizado (lo que queda es tooling de build). ⏳ composer: 46 avisos (Laravel 10 EOL, guzzle, symfony…). Plan en §7 |
| S18 | Composer global con `disable-tls=true` y `secure-http=false` | ✅ Reactivado el 2026-10-08 (respaldo `%APPDATA%/Composer/config.json.bak-20261008_170009`). `composer diagnose`: HTTPS a Packagist y GitHub OK. ⏳ El **propio Composer** está desactualizado y tiene avisos: `composer self-update` |
| S19 | XSS en meracorporation.com/cursos-university: el script de WordPress inserta nombre y descripción con `innerHTML` | ✅ Publicado el 2026-10-08: `docs/wordpress/page-catalogo-cursos.php` (render PHP + `esc_html`, paginación y búsqueda). Verificado en vivo. Respaldo de la plantilla anterior en `docs/wordpress/cursos-university_backup.html` |
| S20 | 🔴 **47 scripts PHP de demo de la plantilla ejecutables en producción** (`public/pages/**`): `file-upload/file-upload.php` permite **subir cualquier archivo sin login y ejecutarlo**; también hay `filer/php/ajax_upload_file.php`, `ajax_remove_file.php` y `j-pro/php/action.php` (correo). El servidor comparte cuenta con ~15 sitios | ✅ 2026-10-12: los 48 PHP se **movieron fuera de la raíz web** (`~/backups_cursos/pages_php_*`; responden 404) y `public/pages/.htaccess` usa `RewriteRule … [F]` (403 verificado en producción). La v1 parecía no funcionar en LiteSpeed, pero la causa real eran **permisos 600** (§5, lección 2). Sin indicios de intrusión. ✅ También quitados del repo |
| S21 | `public/info.php` (phpinfo) público en producción; no está en git | ✅ Movido a `~/backups_cursos/` el 2026-10-12 (ahora responde 404) |
| S22 | Producción con `APP_ENV=local` y `APP_DEBUG=true`: los errores muestran código, rutas y variables | ✅ 2026-10-12: `production`/`false` + cachés. Verificado: los errores no muestran detalles |

### Calidad (QA)
| # | Hallazgo | Estado |
|---|---|---|
| Q1 | `/cursos` daba 500 | ✅ Catálogo público nuevo |
| Q2 | Pérdida del curso al fallar un reemplazo de ZIP | ✅ Todo o nada + papelera |
| Q3 | No se podía editar un usuario sin cambiar su contraseña | ✅ |
| Q4 | Categoría duplicada → 500; no se permitían homónimas bajo padres distintos | ✅ Únicas entre hermanos; slug único |
| Q5 | Borrado en cascada silencioso | ✅ La confirmación muestra el impacto; lógica explícita (no depende del motor de BD) |
| Q6 | `CourseController_base.php` (clase duplicada) | 🟡 Respaldado; **se conserva por decisión del usuario** (§6) |
| Q7 | `moveDirectory` silencioso al renombrar | ✅ Ya no se mueven carpetas al renombrar |
| Q8 | Seeders con IDs fijos | ✅ Idempotentes y basados en nombres |
| Q9 | Perfil solo para admins | ✅ Disponible para cualquier usuario autenticado |
| Q10 | Mensajes `session()` dentro de JS | ✅ JSON + `flash.js` |
| Q11 | IDs duplicados en formularios de borrado | ✅ `data-confirm` |
| Q12 | Pruebas borraban la BD; 21 de 25 fallaban | ✅ 61 pruebas verdes (213 aserciones) en sqlite + candado |
| Q13 | Validaciones en inglés | ✅ `lang/es` |
| Q14 | Código heredado (Lounge, Client, agencias…) | 🟡 Tablas eliminadas (migración con guarda); los archivos **se conservan por decisión del usuario** (§6) |
| Q15 | Lógica de slug duplicada | ✅ Trait `HasUniqueSlug` |
| Q16 | No existía `public/storage` | ✅ `storage:link` ejecutado en local |
| Q17 | Doble slash `cursos.meracorporation.com//storage/…`. Causa: la API devolvía `/storage/…` y el WordPress antepone `…com/` | ✅ La API devuelve `storage/…` sin `/` inicial en los campos originales, más `course_url`/`cover_image_url` absolutos. El WordPress actual queda corregido sin cambios |
| Q18 | Catálogo de WordPress sin paginación | ✅ `/api/courses?page=N&per_page=12&search=…&category_id=…`, más el snippet en `docs/wordpress/` (tiene respaldo si la API aún es la versión anterior) |

### Rendimiento y escalabilidad
| # | Hallazgo | Estado |
|---|---|---|
| P1 | N+1 en `full_path` | ✅ `Category::pathMap()` (una consulta cacheada). Prueba: menos de 15 consultas por página. `preventLazyLoading` registra N+1 en el log |
| P2 | Árbol de categorías recalculado en cada request | ✅ Caché + memo por request, invalidada por eventos |
| P3 | ZIP síncrono con copia doble | ✅ `rename` en vez de copiar. **Decisión: sin cola por ahora** (ver §7, "¿Cola?"). `CourseManager` ya está preparado para moverse a un Job si se necesita |
| P4 | API sin caché | ✅ Caché de 10 minutos con invalidación |
| P5 | Listados sin paginar | ✅ Usuarios, permisos e historial paginados; búsqueda de usuarios |
| P6 | Frontend pesado (Bootstrap + Tailwind, varias fuentes) | 🟡 Quitado chartist y el CDN. ⏳ Unificar framework de CSS (requiere QA visual) |
| P7 | ~5000 archivos de plantilla sin usar en `public/` | ⏳ Inventariar y mover a backup (requiere QA visual) |
| P8 | Portadas sin lazy loading | ✅ `loading="lazy"`. ⏳ Miniaturas (Intervention Image) |

### UX/UI
| # | Hallazgo | Estado |
|---|---|---|
| U1 | "Anti-captura" bloqueaba F12/Ctrl+S y oscurecía la pantalla | ✅ Eliminado del panel |
| U2 | Accesibilidad | ✅ Zoom permitido, `aria-label`, skip-link, `label for`, foco visible, `aria-current` |
| U3 | Subida sin progreso | ✅ Barra %, cancelar, validación de tamaño previa, manejo de 413, 419 y 422 |
| U4 | Se perdía el ZIP al fallar la validación | ✅ Envío por XHR: el formulario no se recarga |
| U5 | Accesos rápidos y menú no dependían del rol | ✅ Menú por rol; usuarios sin rol entran a su perfil |
| U6 | Confirmaciones genéricas | ✅ Nombre del elemento e impacto |
| U7 | Árbol de categorías se desbordaba en móvil | ✅ Indentación con tope (`clamp`) |
| U8 | Marca y metadatos de la plantilla | ✅ Título "· Mera Universidad", `noindex`, chat falso eliminado. ⏳ `favicon.ico` de 0 bytes |
| U9 | Dos librerías de iconos | ⏳ |
| U10 | Filtros sin contador ni opción de limpiar | ✅ Contador, chip del filtro activo, limpiar, "ver cursos" por categoría |

## 5. Despliegue a producción

**Flujo del proyecto:** local (rama `dev`) → GitHub `pikino84/meraUniversidad` (rama `dev`) → el servidor hace `git pull` en `~/cursos.meracorporation.com`. **`dev` funciona como producción**; `main` está desactualizada y no se usa.

**Estado: ✅ desplegado el 2026-10-12** (commits `d33a001`, `856697e` y `858c681`; producción en `858c681`). Respaldos en el servidor: `~/backups_cursos/` (BD `bd_20261012_1743.sql`, `.env` previos, diff de cambios del servidor, commit anterior, inventario de cursos, crontab previo, build anterior, `.htaccess` previos y los 48 PHP de `public/pages/` en `pages_php_<fecha>/`).

### Lecciones del despliegue (LEER antes del próximo)
1. **No compilar en el servidor.** `npm run build` falla: CloudLinux tiene una glibc vieja (`GLIBC_2.29 not found`, binario nativo de rollup). Se compila **en local** (`npm run build`), se empaqueta `public/build` en un ZIP, se sube por el Administrador de archivos de cPanel y se descomprime en `public/`.
2. **Permisos.** LiteSpeed sirve los estáticos y lee los `.htaccess` con **otro usuario**; el PHP corre como `merabl`. Un archivo en `600` o una carpeta en `700` da **404 en estáticos** y hace que su `.htaccess` se **ignore en silencio**, mientras el PHP se sigue ejecutando. Causas vistas: `umask 077` que quedó activo en la terminal y ZIP creado en Windows con permisos `rwx------`. Tras cualquier despliegue:
   ```bash
   umask 022
   find public storage/app/public -type f ! -perm -o=r -exec chmod 644 {} +
   find public storage/app/public -type d ! -perm -o=x -exec chmod 755 {} +
   ```
   (`mera:harden-storage` ya fuerza `0644` en su `.htaccess`.)
3. **En LiteSpeed, el bloqueo de PHP se hace con `RewriteRule … [F]`.** Con el archivo legible, la regla sí funciona (verificado: 403 en `pages/` y en `storage/`).
4. **`optimize:clear` antes de `migrate`** cuando el servidor tiene configuración cacheada y el commit trae archivos de `config/` nuevos.
5. **`mysqldump` sin teclear la contraseña:** leerla del `.env` a un `--defaults-extra-file` temporal con `umask 077` y borrarlo al terminar. Teclearla dio `1045 Access denied`.
6. La shell del servidor no soporta `<(...)` (`/dev/fd`): usar archivos temporales y `cmp`.

### Datos del servidor
- PHP CLI 8.1.34 (`/usr/local/bin/php`). Laravel 10.48.29. Node 18.20.8 (nvm, **no sirve para compilar**). Composer en `~/bin/composer`. `/usr/bin/mysqldump`. Servidor web **LiteSpeed**.
- BD `merabl_cursos`, usuario `merabl_usr_cursos` (credenciales en el `.env`).
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://cursos.meracorporation.com`. Cachés `config`/`route`/`view` activas, así que tras cambiar el `.env` hay que ejecutar `php artisan config:cache`.
- Cambios propios del servidor (no están en git): `public/.htaccess` (solo líneas comentadas) y `.user.ini` (upload/post 512M, memory 512M, max_execution_time 300). Los PHP de demo de `public/pages/` ya no existen ni en el repo ni en el servidor (copia en `~/backups_cursos/pages_php_*`). Además, `tests/Feature/Courses/CourseManagementTest.php` aparece modificado en el servidor (§6, por revisar).
- Crontab: `* * * * * cd /home/merabl/cursos.meracorporation.com && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1` (purga de papelera 03:00 y `activitylog:clean` los domingos). La cuenta comparte crontab con ~15 sitios: **no tocar las otras líneas**.
- Disco al 98 %. CloudLinux limita CPU/IO: `tar -czf` de los 9 GB de cursos no avanza.

### Despliegue rutinario (código sin cambios de frontend ni de BD)
```bash
cd ~/cursos.meracorporation.com && umask 022 && TS=$(date +%Y%m%d_%H%M)
git rev-parse HEAD > ~/backups_cursos/commit_anterior_$TS.txt
git pull origin dev
~/bin/composer install --no-dev --optimize-autoloader   # solo si cambió composer.lock
php artisan migrate --force                              # solo si hay migraciones (con mysqldump antes)
php artisan mera:harden-storage
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
# + chmod de la lección 2, y subir public/build si cambió el frontend (lección 1)
```

### Verificación (lo que se comprobó el 2026-10-12)
- `/login`, `/cursos`, `/api/courses` y los assets de `build/` → 200. La API devuelve paginación (`meta.total` = 120), rutas sin `/` inicial y URLs absolutas.
- PHP de prueba en `pages/` y en `storage/cursos/` → **403**. Los PHP de demo → 404 (movidos). `info.php` y `/register` → 404.
- Errores sin detalles (sin `Whoops`/stack). Inventario de cursos idéntico al previo (13 161 archivos).

**Reversa:** `git checkout $(cat ~/backups_cursos/commit_anterior_<fecha>.txt)`, restaurar `~/backups_cursos/env_*` como `.env`, ejecutar `~/bin/composer install` y `php artisan optimize:clear`. Los PHP de demo se pueden devolver desde `~/backups_cursos/pages_php_*/`.

## 6. Pendientes

**Estado (2026-10-12):** producción corre `858c681`. **Abierto a nuestro cargo:** publicar el programa `/proceso-de-manipulacion-de-alimentos` (hecho en local con pruebas en verde; falta autorización de commit/push y desplegar: `git pull` → `npm ci && npm run build` → `php artisan config:cache && php artisan route:cache && php artisan view:cache`). La ruta depende de `config('mera.programs')`, así que **sin volver a cachear rutas y configuración la página da 404**.

### Delegado a otras áreas (comunicado por correo el 2026-10-12): NO actuar sin instrucción explícita del usuario
Estos puntos no le corresponden a este equipo. Se enviaron por correo a los responsables. No se trabajan, no se proponen como "siguiente paso" y no se modifica nada relacionado hasta que el usuario lo indique.

| # | Tema | Responsable | Ref. |
|---|---|---|---|
| D1 | **Acceso a los cursos:** ¿públicos o solo para personal de MERA? Hoy cualquiera en internet los ve sin login, y `/cursos` no tiene `noindex`. **No cambiar el acceso hasta tener respuesta** | Dirección / RR. HH. / Capacitación | S8 |
| D2 | **Repositorio de GitHub público** (`pikino84/meraUniversidad`): hacerlo privado. Expone este CLAUDE.md (estructura del servidor, hallazgos de seguridad) y el historial con las contraseñas antiguas (ya rotadas) | Dueño de la cuenta `pikino84` | S4 |
| D3 | **Contraseñas temporales de los 5 Super Admin:** entregarlas por canal privado; cada persona la cambia en "Mi perfil" y también en otros servicios donde haya reutilizado la anterior | Cada Super Admin | S4 |
| D4 | **3 portadas rotas (404):** módulos 2 (Ajuste de Inventario), 3 (Crear una Orden de Traslado) y 5 (Introducir Solicitudes) de Mera Way 2.0 (NetSuite). Volver a subirlas desde "Editar curso" | Responsables del contenido | — |
| D5 | **115 de 120 cursos sin categoría** (solo hay categoría en Código de Ética, con 2 cursos, y Protocolo Sismos, con 3). Definir categorías y asignarlas. La API ya permite filtrar por `category_id` | Capacitación | — |
| D6 | **Hosting:** PHP 8.1 del sitio fuera de soporte (subir `cursos.` a 8.3/8.4 en MultiPHP Manager), disco al 98 %, sin respaldo completo de los cursos (9 GB; JetBackup), y una sola cuenta cPanel compartida por ~15 sitios | Administrador del hosting | §7 |
| D7 | **Actualizar Laravel 10 → 13** (fuera de soporte, 46 avisos de Composer). Depende de D6 y requiere aprobación y tiempo asignado | Decisión de quien asigne el trabajo | S17, §7 |
| D8 | **¿Servir el contenido de los cursos desde otro subdominio** (p. ej. `contenido.`) para aislar su JavaScript del panel? | TI / Dirección | S5 |
| D9 | **WordPress:** limpiar el CSS viejo del catálogo en `Divi-child/style.css` (líneas ~5342-5426) | Administrador de meracorporation.com | — |

### Para revisar (interno, menor)
- En el servidor, `git status` muestra **`tests/Feature/Courses/CourseManagementTest.php` modificado** en producción, fuera de git. No afecta al sitio (no es público ni lo carga la app). Revisar con `git diff tests/` en el servidor antes del próximo `pull`; si no tiene nada útil, descartarlo con `git checkout -- tests/` (con respaldo previo, regla 2).
- `docs/wordpress/cursos-university_backup.html` contiene PHP: renombrarlo a `.php.bak` en el próximo commit que toque `docs/`.

### Conservado por decisión del usuario (no borrar sin pedirlo)
- **Código heredado** sin uso, respaldado en `storage/backups/code_removed_20261008_154752/`: `CourseController_base.php`, `LoungeController.php`, `Auth/RegisteredUserController.php`, `Models/Lounge.php`, `Models/Client.php`, `views/lounges/*`, `views/auth/register.blade.php`, `views/welcome.blade.php` y los seeders `Lounge`, `Status`, `RolePermission` y `ModelHasRoles`.
- Los backups locales en `storage/backups/` (no se suben a git).

### Mejoras futuras (requieren QA visual)
- Unificar el framework de CSS (Bootstrap + Tailwind) y los iconos (P6, U9); limpiar las ~5000 páginas de plantilla sin uso en `public/` (P7); miniaturas de portadas (P8); `favicon.ico` de 0 bytes (U8).

## 7. Notas para el paso a producción

### Servidor de producción (datos verificados el 2026-10-08)
| Dato | Valor | Fuente |
|---|---|---|
| Servidor web | **LiteSpeed** (cPanel, CloudLinux `-cll-lve`) | Cabecera `Server` |
| PHP del sitio | **8.1.34** (EOL desde dic-2025, sin parches de seguridad) | Cabecera `X-Powered-By` de `cursos.` y del dominio principal |
| PHP instalado en el servidor | Existe **8.4.25** (lo usa phpMyAdmin) → el cPanel ofrece versiones nuevas en **MultiPHP Manager** | Encabezado del dump SQL |
| MySQL | 8.0.39 | Encabezado del dump SQL |
| Dominios | Panel, API y cursos: `cursos.meracorporation.com` · Catálogo: WordPress (Divi) en `meracorporation.com/cursos-university/` | — |

### Hasta qué versión de Laravel se puede subir
| Laravel | PHP mínimo | Soporte (seguridad) | ¿Viable? |
|---|---|---|---|
| 10 (actual) | 8.1 | Terminado | Solo parches 10.50.x con `composer update` |
| 11 | 8.2 | Terminado (mar-2026) | No vale la pena |
| 12 | 8.2 | Hasta ~feb-2027 | Paso intermedio |
| **13** (13.35 al 2026-10-06) | **8.3** | Hasta ~2028 | **Objetivo recomendado** |

Ruta sugerida:
1. En cPanel → MultiPHP Manager, cambiar el subdominio `cursos.` a **PHP 8.3 o 8.4**, primero en un subdominio de prueba si es posible. Revisar extensiones: `zip`, `fileinfo`, `gd`, `pdo_mysql`, `mbstring`.
2. Localmente usar PHP 8.3+ (XAMPP trae 8.2).
3. Actualizar 10 → 11 → 12 → 13 con la guía oficial, corriendo `php artisan test` en cada salto. Paquetes a revisar: spatie/permission, spatie/activitylog, sanctum y breeze.
4. Mientras tanto: el código actual es **compatible con PHP 8.1** (verificado: ninguna dependencia del lock exige 8.2).

### Contraseñas expuestas: rotación OBLIGATORIA
- `database/seeders/UserSeeder.php` tuvo la contraseña real de los Super Admin (`jfcruz@outlook.com`, `jesus.castro@meracorporation.com`). El repo `github.com/pikino84/meraUniversidad` es **público**, así que debe tratarse como **comprometida**.
- ¿Borrarla del historial de git? Es posible (`git filter-repo` o BFG, y luego `push --force`), pero **no basta**: reescribe todos los commits, obliga a re-clonar y no elimina copias que alguien ya haya bajado ni cachés. **Lo que protege es rotar la contraseña.** Recomendación adicional: hacer el repo **privado**.
- Pasos en producción (por SSH o la Terminal de cPanel):
  ```bash
  php artisan mera:rotate-password --super-admins          # o: mera:rotate-password correo@dominio.com
  ```
  Genera contraseñas aleatorias, invalida el "recordarme" y las muestra **una sola vez**. Entregarlas por un canal privado y pedir que cada persona la cambie en "Mi perfil".
- Si esa misma contraseña se usó en otro servicio (correo, cPanel, etc.), cambiarla también ahí.

### Composer con TLS desactivado (máquina de desarrollo)
- `composer config --global` tiene `disable-tls=true` y `secure-http=false`: Composer descarga paquetes **sin verificar el certificado** de Packagist/GitHub. Alguien en la red (Wi-Fi, proxy) podría servir un paquete alterado que luego se ejecutaría en el servidor.
- Probable origen: Windows/curl falla con `CRYPT_E_NO_REVOCATION_CHECK` (la red no deja comprobar la revocación de certificados). **PHP sí valida TLS en esta máquina** (usa `C:\xampp\apache\bin\curl-ca-bundle.crt`), así que no hace falta desactivarlo.
- **Hecho el 2026-10-08:** se quitaron ambas opciones (respaldo `config.json.bak-20261008_170009`) y `composer diagnose` conecta por HTTPS sin problema. No afecta a la app ni al servidor; solo a cómo Composer descarga paquetes en esta máquina. Si en otra red fallara, **no** volver a desactivar TLS: configurar `cafile` con el certificado del proxy.
- Siguiente paso: `composer update` dentro de Laravel 10 (lleva a 10.50.x y cierra parte de los 46 avisos), y `php artisan test`.

### ¿Cola para procesar los ZIP?
**Por ahora no.** Se sube un ZIP a la vez y el tiempo de espera se va casi todo en la **subida** (red), que una cola no acelera: el archivo tiene que llegar completo al servidor de todos modos. La extracción local de un curso promedio (~60 MB) tarda segundos. Una cola en hosting compartido además exige un cron con `queue:work`, más mantenimiento y otro punto de falla. **Reconsiderar** si: (a) la extracción supera ~60 s, (b) LiteSpeed corta las peticiones largas (error 500/504 al subir), o (c) se agregan pasos pesados (miniaturas, antivirus). `CourseManager` ya recibe rutas y no el Request, así que moverlo a un Job es directo.

## 8. Línea de diseño (marca MERA)

Fuente: https://meracorporation.com/media-kit/ y https://meracorporation.com/

| Token | HEX | Uso |
|---|---|---|
| MERA green | `#024D25` | Primario: títulos, botones principales, header de tablas |
| Secondary green | `#55882B` | Degradados, hover |
| Green for accent | `#93C01F` | Acentos, botones de acción, badges |
| Light blue | `#81CFF4` | Alternativo: foco, elemento activo del menú |
| Aquamarine blue | `#8CBCC4` | Alternativo: degradados, iconos |

- **Tipografía:** Montserrat (Thin → Black), respaldo **Arial**. Se carga desde `partials/brand-fonts`, y en `resources/css/app.css` ("LÍNEA DE MARCA") reemplaza las fuentes de la plantilla.
- **Degradados:** `--mera-gradient` (green → secondary → accent) y `--mera-gradient-sky` (green → aqua → light blue), que evoca el "amanecer" del rebranding.
- **Logo:** siempre en minúsculas ("mera"). En texto corrido la marca va en MAYÚSCULAS: "MERA". Tagline: *Vision · Values · Results*. No rotar, deformar ni cambiar el tagline.
- **Tailwind:** colores `mera-green`, `mera-secondary`, `mera-accent`, `mera-sky`, `mera-aqua`; `darkMode: class`, para que el modo oscuro del sistema operativo no cambie la marca.
- **Componentes:** botones redondeados (pill), tarjetas con `border-radius: 16-18px` y sombras verdes suaves. En móvil las tablas pasan a tarjetas (`.mera-table-stack` + `data-label` en cada `<td>`).

## 9. Bitácora de lo realizado

| Fecha | Qué se hizo |
|---|---|
| 2026-10-08 | Auditoría inicial (seguridad, QA, rendimiento, UX/UI) y primera versión de este CLAUDE.md. |
| 2026-10-08 | **Incidente:** `php artisan test` vació la BD local `merabl_cursos` (había configuración cacheada y la conexión era MySQL). Se restauró desde un dump de producción, con respaldo previo, y se aislaron las pruebas (sqlite `:memory:` + candado en `TestCase`). Origen de las reglas 2, 3 y 4. |
| 2026-10-08 | Correcciones S1-S16, Q1-Q16 y P1-P5: servicios de cursos (`CourseStorage`, `CoursePackageExtractor`, `CourseManager`), políticas y roles, FormRequests, caché de categorías y API, papelera, comandos `mera:*`, localización `es`, pruebas. Migración de tablas legacy aplicada en local (con respaldo). |
| 2026-10-08 | UX/UI y marca: Montserrat + paleta del media kit, login rediseñado, subida con progreso, tablas como tarjetas en móvil, respaldo de portadas, catálogo público `/cursos`. Revisado con capturas de escritorio (1366 px) y móvil (390 px). |
| 2026-10-08 | API: corregido el doble slash (rutas sin `/` inicial), campos absolutos `course_url`/`cover_image_url` y paginación opcional `?page=`. |
| 2026-10-08 | WordPress: nueva plantilla `page-catalogo-cursos.php` (render PHP, paginación, búsqueda, sin XSS), **publicada por el usuario** y verificada en vivo. |
| 2026-10-08 | Composer local: TLS reactivado (`disable-tls`/`secure-http`), con respaldo de `config.json`. |
| 2026-10-08 | Hallazgo S20: 47 scripts PHP de la plantilla ejecutables en producción → `public/pages/.htaccess`. |
| 2026-10-08 | Commit **`d33a001`** subido a `origin/dev` (autorizado por el usuario). |
| 2026-10-09 | Reglas: respuestas siempre en español (regla 0) y commit/push nunca automáticos (regla 1). |
| 2026-10-12 | Despliegue, Fase 0: `info.php` retirado; revisión sin indicios de intrusión. Fase 1 parcial: falta `mysqldump` e inventario (§5). |
| 2026-10-12 | Producción ya corre `d33a001` (verificado desde fuera). Se detectó que LiteSpeed ignora `RemoveHandler`/`Require all denied`: `public/pages/.htaccess` y el `.htaccess` de storage (v2, en `CourseStorage`) pasan a usar `RewriteRule … [F]`. Verificado en local (PHP 403, CSS/JS/portadas 200) con 61 pruebas en verde. CLAUDE.md reorganizado (§5 despliegue con avance, §6 pendientes por prioridad, §9 bitácora). Commit y push autorizados por el usuario. |
| 2026-10-12 | **Despliegue completado** (fases 1-6). Dump de la BD (leyendo credenciales del `.env`), inventario de cursos, `git pull` (`d33a001` y luego `856697e`), `composer --no-dev`, `migrate` (tablas heredadas), build compilado en local y subido (el servidor no puede compilar: glibc vieja), `.env` de producción + cachés, contraseñas de Super Admin rotadas, cron del scheduler. Los 48 PHP de `public/pages/` se movieron fuera de la raíz web. Causa de los estilos rotos y de los `.htaccess` ignorados: permisos 600/700 (umask 077 + ZIP de Windows) → `chmod`. `CourseStorage::harden()` ahora fuerza `0644`. Verificado en producción: PHP en `pages/` y `storage/` → 403, assets → 200, inventario igual. |
| 2026-10-12 | Quitados del repo los 48 PHP de demo de `public/pages/` (respaldo local en `storage/backups/code_removed_20261012_pages_php/`). `harden()` fuerza `0644`. S8 (acceso a cursos) queda en espera de consulta por correo. Commit autorizado por el usuario. |
| 2026-10-12 | Producción en `858c681` (servidor actualizado con `pull`). Todos los pendientes restantes pasan a **delegados** (§6, D1-D9) y se comunican por correo; ya no son tareas de este equipo. Detectado `tests/…/CourseManagementTest.php` modificado en el servidor (por revisar). |
| 2026-10-12 | **Programa público "Proceso de manipulación de alimentos"** en `/proceso-de-manipulacion-de-alimentos` (a partir del HTML que entregó el usuario). Mejoras sobre el original: documento HTML completo (`lang`, `viewport`, metadatos para compartir), módulos resueltos **por slug** (re-subir un ZIP ya no rompe enlaces), logo como archivo (`images/logo-mera-blanco.png`) en lugar de 19 KB en base64, sin modo oscuro automático (regla de marca), jerarquía de encabezados y `aria-label` con aviso de pestaña nueva, `rel="noopener noreferrer"`, avance por dispositivo (localStorage: "Visto", "Continúa aquí", barra, aviso al terminar, reiniciar), `prefers-reduced-motion`. Genérico: otro programa = otra entrada en `config/mera.php`. 65 pruebas en verde. **Pendiente: autorización de commit y push; tras el `pull` en el servidor: `npm ci && npm run build`, `php artisan config:cache && php artisan route:cache && php artisan view:cache`.** |
