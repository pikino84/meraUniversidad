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
7. **El frontend NUNCA se compila en el servidor.** No incluir `npm ci`, `npm install` ni `npm run build` en ninguna guía de despliegue: en CloudLinux fallan (glibc vieja) y además consumen el disco, que está al 98 %. El build se hace **en local**, se empaqueta en ZIP, se sube, se descomprime en `public/` y **se borra el ZIP** (procedimiento en §5, "Frontend (`public/build`)"). Antes de escribir cualquier guía de despliegue, **leer §5 completo**.

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
npm run build                     # producción → public/build (SOLO en local; nunca en el servidor, regla 7)
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
  URLs SIEMPRE en español (2026-10-20): /iniciar-sesion, /panel, /perfil, /usuarios, /modulos, /carpetas,
                           /historial, /permisos, /seguimiento… (los NOMBRES de ruta siguen en inglés). Las anteriores
                           en inglés → 301 (App\Support\LegacyPaths). Excepción: /api/courses (contrato con WordPress)
  /                      → redirect /iniciar-sesion
  /cursos                → CatalogController (público)
  /<slug-por-idioma>     → ProgramController (público). Programas multi-idioma de config('mera.programs'):
                           /proceso-de-manipulacion-de-alimentos (es) · /food-handling-process (en, sin módulos aún)
  panel                  → course-groups (carpetas de curso, publish), courses.bulk-move / move / ungroup
  /<carpeta>/<año>/<versión> → CoursePageController (p. ej. /higiene/2026/mexico): el link que se comparte, igual
                           que la ruta del panel; muestra SOLO esa versión (sin selector de versión ni de año).
                           Público si la versión está publicada; vista previa para admins. AL FINAL del archivo.
                           /<carpeta> y /<carpeta>/<año> → 301 a la versión por defecto más reciente;
                           /en/<carpeta> y /<carpeta>/en (formatos anteriores) → 301 a la de EUA más reciente
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
app/Support/ProgramCatalog  Programas multi-idioma: URL → (programa, idioma), módulos por idioma, formularios
app/Services/Courses/CourseGroupOrganizer  Acomodo de módulos en carpetas (mover en lote, ordenar, quitar)
app/Models/CourseGroup   Carpeta de curso (nombre y link). Sus versiones: CourseGroupVersion (carpeta + año + país:
                         módulos en orden, código del catálogo, página pública y reportes). Market = versión por país
                         (México, Latam, EUA…), editable en /versiones (super admin). docs/carpetas/INSTRUCTIVO_VERSIONES.md
lang/<idioma>/program.php  Textos traducibles de las páginas de programa
config/mera.php          límites, extensiones permitidas, rutas, URL base de cursos, caché API y **programas**
                         (módulos resueltos por slug del curso, ruta de respaldo, evaluación/encuesta)
app/Http/Middleware/SecurityHeaders  Cabeceras de seguridad globales (CSP en Report-Only; HSTS solo en producción)
app/Support/TestEnvironment  Fuera de producción: cintillo "Entorno de pruebas" (partials/test-environment, en todos los
                         layouts y en el HTML descargado) y "Apagar todo lo compartido" (páginas de versión + links de reportes)
app/Http/Controllers/TrashController Papelera de módulos (solo super admin): borrado suave + restaurar archivos
app/Services/Users/AccountInvitations Link por correo para crear/cambiar contraseña (broker "invitations", 72 h)
app/Console/Commands      mera:check-courses (archivos faltantes, solo lectura) · mera:program-folder (carpeta de un programa) · mera:detach-categories (quita categorías, con respaldo) · mera:report-verify (compara un reporte con el esperado, solo lectura) · mera:purge-report-uploads (Excel abandonados, cada hora) · mera:versions-from-languages (carpeta + idioma → versiones; --dry-run, respaldo JSON) · mera:catalog-import (catálogo de cursos desde el Excel de Capacitación; --aliases, --dry-run) · mera:progress-verify (cifras de un corte del seguimiento en memoria, solo lectura)
resources/js/modules/    flash.js (mensajes por JSON), confirm.js (data-confirm), upload.js (progreso),
                         sortable-modules.js (arrastrar módulos), unsaved.js (aviso de cambios sin guardar)
resources/views/partials page-header, flash, form-errors, delete-button, user-menu, cover (respaldo de portada), brand-fonts
app/Services/Reports/    Reportes de curso por carpeta (docs/reportes/INSTRUCTIVO_REPORTES.md):
  ReportWorkbookReader   Lee la hoja 1 de un .xlsx en streaming (openspout 4.25) y valida encabezados
  PersonnelDirectory     No. de empleado → localidad/área/puesto (reglas en config/mera.php → reports)
  SurveyAnswerClassifier Texto libre → temas (palabras clave de config/report_surveys.php)
  CourseReportBuilder    Arma cubo / inicio_mes / encuesta / catalogo (solo agregados) + avisos y totales
  CourseReportManager    Crear / recalcular / compartir / clasificar. Excel en storage/app/tmp/report-uploads
                         (privado) y borrados SIEMPRE (finally + mera:purge-report-uploads cada hora)
app/Models/CatalogCourse + Services/Reports/CourseCatalogImporter  Catálogo de cursos (/catalogo-cursos, super admin):
                         (paginado de 10)
app/Services/Reports/ProgressReport{Builder,Manager} + ProgressDashboard  Seguimiento anual (/seguimiento, admin y super
                         admin): Excel de progreso + de personal → progress_reports / _people / _items; tablero con
                         filtros, consulta de colaboradores (CSV) y ficha. FastSheetReader: lector rápido del Excel de
                         progreso (~75 mil filas en ~13 s; openspout tardaba ~140 s). OrgMapper: reglas de localidad
                         y área compartidas con el reporte por curso. mera:progress-verify (solo lectura)
                         código, mínima para aprobar, evaluación, si cuenta y códigos de la plataforma
app/Http/Controllers/CourseReportController  course-groups/{carpeta}/reportes/… (admin y super admin),
                         /descargar (HTML autocontenido) y reportes/compartido/{token} (público, noindex)
resources/js/report-dashboard.js + resources/css/report.css  Tablero (d3 7.9.0). Entradas PROPIAS de Vite,
                         sin código compartido con app.js: "Descargar HTML" las incrusta tal cual
config/report_surveys.php  Encuesta por curso (preguntas favorables, catálogo de temas, clasificación previa)
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

**Flujo del proyecto (desde el 2026-10-14):**
- **`qa`** (creada desde `dev` en `be873ba`): rama de **trabajo**. Todo lo nuevo se hace y se sube aquí primero.
- **`dev`**: **producción**. El servidor hace `git pull origin dev` en `~/cursos.meracorporation.com`. A `dev` solo llega lo que el usuario aprueba pasar desde `qa` (merge o fast-forward, **solo con autorización**, regla 1).
- **`main`**: desactualizada; no se usa. No se pudo renombrar `dev` porque el usuario no es dueño del repo (`pikino84`).
- Al terminar un cambio en `qa`: commit y push a `qa` (con autorización); para producción, pasar `qa` → `dev` (con autorización) y desplegar como indica esta sección.
- **Entorno QA (desde el 2026-10-15):** `https://university.meracorporation.com` sirve la rama **`qa`** desde `~/qa.cursos.meracorporation.com` (`git worktree` del repo de producción; BD propia `merabl_cursos_qa`, copia de producción; `APP_ENV=staging`; `MAIL_MAILER=log`; sin archivos de cursos). Configuración, actualización rutinaria y reversa: **`docs/despliegue/QA_UNIVERSITY.md`**. **QA en `3b5fed8`** (2026-10-15; `noindex` verificado). ⚠ Nunca `git pull origin qa` en la carpeta de producción ni `git pull origin dev` en la de QA.

**Estado: ✅ producción en `da2b4d0`** (desplegado el 2026-10-15: reportes de curso; antes `be873ba`, `19dd31a`, `d33a001`, `856697e`, `858c681` y `97ce623`). Respaldos en el servidor: `~/backups_cursos/` (BD `bd_20261012_1743.sql`, `.env` previos, diff de cambios del servidor, commit anterior, inventario de cursos, crontab previo, build anterior, `.htaccess` previos y los 48 PHP de `public/pages/` en `pages_php_<fecha>/`).

### Lecciones del despliegue (LEER antes del próximo)
1. **No compilar en el servidor (regla 7).** `npm run build` falla: CloudLinux tiene una glibc vieja (`GLIBC_2.29 not found`, binario nativo de rollup). `npm ci` sí corre, pero solo deja un `node_modules` inútil que ocupa disco. Se repitió el error el 2026-10-12 con `97ce623`: la guía volvió a incluir `npm ci && npm run build`. Procedimiento correcto en "Frontend (`public/build`)", más abajo.
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
7. **El ZIP de Windows trae permisos `666`** (escribibles por todos): después de instalar el build, `find public/build -type f -exec chmod 644 {} +` y `find public/build -type d -exec chmod 755 {} +`. El `chmod` de la lección 2 solo corrige lo *no legible*. Verificar con `find public storage/app/public -perm -o=w ! -type l` (no debe mostrar nada).
8. **SMTP:** el servidor de salida es **`meracorporation.com`** (puerto 465, SSL), no `mail.meracorporation.com` (dato de cPanel → Connect Devices). `MAIL_PASSWORD` entre **comillas simples**. Un `535 Incorrect authentication data` es la contraseña: se resolvió restableciéndola en cPanel. Probar con `php artisan tinker --execute="Mail::raw('Prueba', function(\$m){ \$m->to('<correo>')->subject('Prueba'); });"` tras `config:cache`.
9. La API solo pagina (y devuelve `meta`) si se manda `?page=`.

### Datos del servidor
- PHP CLI 8.1.34 (`/usr/local/bin/php`). Laravel 10.48.29. Node 18.20.8 (nvm, **no sirve para compilar**). Composer en `~/bin/composer`. `/usr/bin/mysqldump`. Servidor web **LiteSpeed**.
- BD `merabl_cursos`, usuario `merabl_usr_cursos` (credenciales en el `.env`).
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://cursos.meracorporation.com`, `APP_NAME="Mera Universidad"` y correo SMTP con `notificaciones@meracorporation.com` (host `meracorporation.com`, 465, ssl; la contraseña solo está en el `.env` del servidor). Cachés `config`/`route`/`view` activas, así que tras cambiar el `.env` hay que ejecutar `php artisan config:cache`.
- Cambios propios del servidor (no están en git): `public/.htaccess` (solo líneas comentadas) y `.user.ini` (upload/post 512M, memory 512M, max_execution_time 300). Los PHP de demo de `public/pages/` ya no existen ni en el repo ni en el servidor (copia en `~/backups_cursos/pages_php_*`). El cambio local de `tests/Feature/Courses/CourseManagementTest.php` se descartó el 2026-10-14 (inofensivo: `.png` → `.php` en un fixture; respaldo en `~/backups_cursos/tests_servidor_20261014_1106.diff`).
- Crontab: `* * * * * cd /home/merabl/cursos.meracorporation.com && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1` (purga de papelera 03:00 y `activitylog:clean` los domingos). La cuenta comparte crontab con ~15 sitios: **no tocar las otras líneas**. Desde el 2026-10-15 hay una segunda línea igual para QA (`cd /home/merabl/qa.cursos.meracorporation.com && …`).
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
# + chmod de la lección 2, y subir public/build si cambió el frontend (ver abajo)
```

### Frontend (`public/build`): compilar en local, subir el ZIP, descomprimir y borrar el ZIP
Hace falta cada vez que un commit cambia `resources/css`, `resources/js`, `tailwind.config.js`, `package*.json` o vistas con clases nuevas de Tailwind.

**En local** (Windows). El ZIP se arma con PHP `ZipArchive` porque `Compress-Archive` de PowerShell guarda rutas con `\`, que en Linux quedan mal:
```bash
npm run build
C=$(git rev-parse --short HEAD); mkdir -p storage/deploy
php -r '$s="public/build";$z=new ZipArchive;$z->open($argv[1],ZipArchive::CREATE|ZipArchive::OVERWRITE);
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($s,FilesystemIterator::SKIP_DOTS)) as $f)
$z->addFile($f->getPathname(),"build/".str_replace("\\","/",substr($f->getPathname(),strlen($s)+1)));$z->close();' "storage/deploy/build_$C.zip"
```
Resultado: `storage/deploy/build_<commit>.zip`. Contiene `build/manifest.json` y `build/assets/*`. Está en `.gitignore`, así que no se sube a git.

**Subir:** cPanel → Administrador de archivos → `cursos.meracorporation.com/public/` → Cargar. Si se sube desde la **raíz** del Administrador de archivos, el ZIP queda en `~` (`/home/merabl/`), no en `public/`: en ese caso usar `~/build_<commit>.zip` en los comandos de abajo.

**En el servidor**, después del `git pull`. ⚠ **Nunca mover `build` antes de tener el nuevo extraído y verificado.** Sin `public/build`, todas las páginas con `@vite` dan **500** (pasó el 2026-10-12: el ZIP no estaba en `public/`, el `unzip` falló y el build ya se había movido).
```bash
cd ~/cursos.meracorporation.com/public && umask 022
test -f build_<commit>.zip || echo "FALTA EL ZIP: NO CONTINUAR"          # 1) ¿está el ZIP aquí?
rm -rf build_nuevo && mkdir build_nuevo && unzip -q build_<commit>.zip -d build_nuevo
cat build_nuevo/build/manifest.json                                      # 2) debe coincidir con el local
# 3) solo si el paso 2 está bien: intercambio (el viejo queda de respaldo, regla 2)
mv build ~/backups_cursos/build_$(date +%Y%m%d_%H%M) && mv build_nuevo/build build
rmdir build_nuevo && rm build_<commit>.zip                               # borrar el ZIP
find build -type f -exec chmod 644 {} + && find build -type d -exec chmod 755 {} +
cd .. && php artisan view:cache
```
**Si algo falla:** `mv "$(ls -td ~/backups_cursos/build_* | head -1)" ~/cursos.meracorporation.com/public/build` devuelve el build anterior.
Si en el servidor quedó un `node_modules` de un `npm ci` por error, se puede borrar (`rm -rf node_modules`): no lo usa nada y ocupa disco.

### Despliegue de reportes de curso (rama `qa` → `dev`) — ✅ HECHO el 2026-10-15 (`be873ba` → `da2b4d0`)
Resultado: respaldos `TS=20261015_1911` en `~/backups_cursos/` (`commit_anterior_`, `env_` en `600`, `bd_` de 82 KB, `build_`); `pull --ff-only` (62 archivos); Composer con `openspout`; migración `course_reports`; `higiene.csv` en `storage/app/reportes/codigos/` (`600`); build `684ba13` (sirve para `da2b4d0`: sin cambios de frontend entre ambos) con intercambio seguro; cachés y `chmod`. Verificado: `/login`, `/cursos`, `/api/courses` y `build/manifest.json` → 200; sin `X-Robots-Tag` en producción; `mera:purge-report-uploads` en `schedule:list`; en navegador, catálogo, programa, módulos, carpetas y reportes correctos. Lo de abajo queda como referencia para el próximo despliegue con migraciones y frontend.
Lleva **dependencia nueva de Composer, 1 migración aditiva, un archivo privado que se sube a mano y frontend**. Antes: leer las lecciones de arriba (sobre todo 1, 2, 4, 5 y 7).

**Antes de desplegar: verificar en QA** (`university.meracorporation.com`)
- ✅ (QA, 2026-10-15: cifras, tablero, Descargar HTML, compartir sin sesión, `/en/<carpeta>` y "+ Nueva carpeta…" correctos; temporales vacíos) **Primer corte de Higiene** con los Excel reales del 2026-10-14 (carpeta de Higiene → Reportes → Nuevo corte). Debe dar exactamente lo de `docs/reportes/INSTRUCTIVO_REPORTES.md` §9: asignados **3,301**; completaron **2,623 (79 %)**; no empiezan **563**; promedio **77.5** (61 % con 80 o más); satisfacción **76 %** de **1,634** respuestas; aviso de **16** inscritos excluidos. Después, `ls ~/qa.cursos.meracorporation.com/storage/app/tmp/report-uploads` debe quedar vacío.
- ✅ **Límite de subida** (verificado en QA el 2026-10-15): "Módulos → Nuevo módulo" dice **"Máximo 1,000 MB"**. Es el límite de la app: el PHP del hosting ya permite 1,000 MB o más. El `.user.ini` de la raíz del proyecto (512M) **no se aplica** (PHP solo lo busca desde `public/` hacia arriba hasta la raíz del dominio, que es `public/`), y **no hay que copiarlo a `public/`**: bajaría el límite de subida de cursos a 512 MB. Producción está igual (su `.user.ini` también está en la raíz). `php -i` por terminal no sirve para comprobarlo (la CLI no lee `.user.ini`).

**En local**
1. Con autorización (regla 1): pasar `qa` → `dev` (merge o fast-forward) y `git push origin dev`. ✅ Hecho el 2026-10-15 (`dev` = `da2b4d0`).
2. `npm run build` y generar `storage/deploy/build_<commit>.zip` (procedimiento "Frontend", arriba). El manifest debe tener **4 entradas**: `app.css`, `app.js`, `report.css` y `report-dashboard.js`.
3. Tener a la mano `storage/app/reportes/codigos/higiene.csv` (clasificación previa de comentarios de Higiene). **Trae números de empleado: nunca a git ni por correo**; se sube con el Administrador de archivos de cPanel.

**En el servidor**
```bash
cd ~/cursos.meracorporation.com && umask 022 && TS=$(date +%Y%m%d_%H%M)
git rev-parse HEAD > ~/backups_cursos/commit_anterior_$TS.txt
cp .env ~/backups_cursos/env_$TS
# mysqldump con --defaults-extra-file (lección 5) → ~/backups_cursos/bd_$TS.sql; verificar que no esté vacío
php -m | grep -iE '^(zip|xmlreader)$'                     # openspout necesita las dos: deben salir ambas
git pull origin dev
~/bin/composer install --no-dev --optimize-autoloader     # instala openspout/openspout 4.25 (compatible con PHP 8.1)
php artisan optimize:clear                                # lección 4: hay config nueva (mera.reports)
php artisan migrate --force                               # 1 migración aditiva: course_reports
php artisan mera:harden-storage
```
- **Clasificación previa:** `mkdir -p storage/app/reportes/codigos && chmod 700 storage/app/reportes storage/app/reportes/codigos`, subir el CSV a `storage/app/reportes/codigos/higiene.csv` con cPanel y `chmod 600` a ese archivo. Está fuera de `public/` (no se sirve por web). Sin él los reportes funcionan, pero todos los comentarios quedan "por clasificar".
- **Build:** instalar `build_<commit>.zip` con el procedimiento seguro (extraer en `build_nuevo`, revisar que el manifest tenga las 4 entradas, intercambiar, borrar el ZIP, `chmod`).
- `php artisan config:cache && php artisan route:cache && php artisan view:cache`, y el `chmod` de la lección 2.
- **Tarea programada nueva:** `php artisan schedule:list` debe mostrar `mera:purge-report-uploads` (cada hora). Usa el cron que ya existe: **no tocar el crontab**.
- Límites: cada Excel hasta 30 MB (`MERA_REPORTS_MAX_UPLOAD_KB`); `.user.ini` ya permite 512M y 300 s.

**Verificar**
- `/course-groups/<carpeta>`: sección "Reportes". Crear un corte de prueba con los Excel reales: muestra el loader "Procesando los Excel…", los totales y el tablero, sin errores en la consola. `ls storage/app/tmp/report-uploads` debe quedar **vacío**.
- "Descargar HTML" baja un solo archivo que abre sin servidor (gráficas y filtros). "Compartir sin sesión": el link abre sin sesión con `X-Robots-Tag: noindex`; al desactivarlo da 404.
- `/en/<carpeta>` responde y `/<carpeta>/en` redirige con 301. Módulos → "Mover a…" → "+ Nueva carpeta…" crea la carpeta y mueve.
- Borrar el corte de prueba si no se va a usar.

**Reversa:** `git checkout $(cat ~/backups_cursos/commit_anterior_<TS>.txt)`, `~/bin/composer install --no-dev`, devolver el build anterior y `php artisan optimize:clear`. La tabla `course_reports` puede quedarse (es aditiva) o quitarse con `php artisan migrate:rollback --step=1` (con dump antes).

### Despliegue de carpetas por año y versión (rama `qa`; PENDIENTE, requiere autorización)
Lleva **1 migración aditiva, un comando de datos y frontend**. Sin Composer. ⚠ Incluye la corrección de **borrar** (ver bitácora 2026-10-19): en producción hoy no abre ninguna confirmación de eliminar desde `e501ebd`; si se necesita antes que las versiones, se puede llevar sola (solo `resources/js/modules/confirm.js` + build). Primero en **QA** (`docs/despliegue/QA_UNIVERSITY.md`, "Actualizar QA") y, ya aprobado, en producción con el mismo orden:
1. Respaldo: commit anterior, `mysqldump` (lección 5) y, si aparece, el diff de `CourseManagementTest.php` (escáner del hosting) → `git checkout --` de ese archivo.
2. `git pull`, `php artisan optimize:clear` (config nueva `mera.versions`), `php artisan migrate --force` (crea `markets` con México, Latam y EUA, y `course_group_versions`).
3. **Acomodo de lo existente:** `php artisan mera:versions-from-languages --dry-run --map=manipulacion-de-alimentos:LATAM` → revisar la tabla con el usuario (año y versión de cada carpeta) → sin `--dry-run`. Guarda un JSON en `storage/backups/`. Es idempotente.
4. Build con el procedimiento seguro (§5, "Frontend") y cachés (`config`, `route`, `view`) + `chmod` (lección 2).
5. **Verificar:** `/carpetas/<carpeta>` (años → tarjetas de versión) y `/carpetas/<carpeta>/versiones/<id>` (pestañas Módulos, Link para compartir y Reportes; borrar una versión vacía abre la confirmación), cintillo "Entorno de pruebas" **solo en QA** (en producción no debe aparecer), `/versiones` (solo super admin), `/proceso-de-manipulacion-de-alimentos` (mismos 5 módulos y avance), una carpeta publicada en `/<carpeta>/<año>/<versión>` (sin selector de versión ni de año), `/<carpeta>`, `/en/<carpeta>` y `/<carpeta>/en` → 301, y `/api/courses` con `year`, `market`, `market_name`, `catalog_code`.

### Despliegue del seguimiento anual y URLs en español (rama `qa`; PENDIENTE, va junto con el de versiones)
Lleva **3 migraciones aditivas** (`course_catalog` y las 3 tablas `progress_*`, más la de versiones si no se ha aplicado) y frontend. Sin Composer.
1. Respaldo (commit, `mysqldump`) → `git pull` → `php artisan optimize:clear` (config nueva `mera.progress` y `mera.test_environment`) → `php artisan migrate --force`.
2. **Catálogo:** subir `Catalogo_MERA_v12.xlsx` en `/catalogo-cursos` (super admin) o `php artisan mera:catalog-import <xlsx> --aliases=docs/reportes/equivalencias_codigos.csv`. Los cursos de 2025 quedan con "No cuenta".
3. Build seguro (§5, "Frontend") + cachés + `chmod`.
4. **Medir en QA** un corte real (`/seguimiento/nuevo` con el Excel de progreso y el de personal): en local tarda ~70 s con `artisan serve`; si LiteSpeed corta antes (504), pasar el armado a la cola (`jobs` + `queue:work --stop-when-empty` desde el scheduler) y avisar al usuario. `storage/app/tmp/report-uploads` debe quedar vacío.
5. **URLs:** probar `/login` → 301 `/iniciar-sesion`, un link viejo de "olvidé mi contraseña" (`/reset-password/<token>?email=…` → `/restablecer-contrasena/…`), `/dashboard` → `/panel`; `/api/courses` sigue igual (WordPress). Avisar a Capacitación que las direcciones del panel cambiaron (los marcadores viejos siguen funcionando).

### Verificación (lo que se comprobó el 2026-10-12)
- `/login`, `/cursos`, `/api/courses` y los assets de `build/` → 200. La API devuelve paginación (`meta.total` = 120), rutas sin `/` inicial y URLs absolutas.
- PHP de prueba en `pages/` y en `storage/cursos/` → **403**. Los PHP de demo → 404 (movidos). `info.php` y `/register` → 404.
- Errores sin detalles (sin `Whoops`/stack). Inventario de cursos idéntico al previo (13 161 archivos).

**Reversa:** `git checkout $(cat ~/backups_cursos/commit_anterior_<fecha>.txt)`, restaurar `~/backups_cursos/env_*` como `.env`, ejecutar `~/bin/composer install` y `php artisan optimize:clear`. Los PHP de demo se pueden devolver desde `~/backups_cursos/pages_php_*/`.

## 6. Pendientes

**Nuevos alcances (pedidos el 2026-10-15; por implementar en `qa`, cada uno con su instructivo):**
- **Carpetas por año y versión por país** (México · Latam · EUA, extensible; reemplaza la división Español/Inglés): `docs/carpetas/INSTRUCTIVO_VERSIONES.md`.
- **Seguimiento de capacitación por año + reporte individual por persona** (referencia de Capacitación: `storage/app/reportes/referencia/seguimiento_2026_referencia.html`, no versionada porque trae nombres) desde el Excel "Reporte de progreso de capacitaciones" (todos los cursos), con catálogo de cursos y equivalencias de códigos: `docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md` (Fase B opcional: reporte por curso con 1 Excel en vez de 3). Catálogo de referencia en `storage/app/reportes/referencia/Catalogo_MERA_v12.xlsx` (no versionado).
- Ambos tienen una sección **"Decisiones abiertas"**: responderla con el usuario antes de programar.
- **Acordado con Capacitación (2026-10-19, tarde):** cursos **General / Específico** (los específicos, BL o EL, son para ciertas áreas o puestos e inflan cursos y horas por persona en las comparaciones). Columna **`Alcance`** en el Excel del catálogo (tabla de nomenclaturas y tabla de información de los cursos) → `course_catalog.scope`; el importador la actualiza siempre y el panel solo la muestra; las comparaciones entre localidades y áreas usan solo los generales por defecto (`docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md` §7.3). **Pendiente de Capacitación:** enviar el catálogo con `Alcance` lleno.
- **Encuestas de 5 cursos más recibidas** (Huracán 2026, Tus áreas tu seguridad, Orientación a Resultados, Despega con MERA 2026, Salas VIP Lounge) en `storage/app/reportes/referencia/encuestas/` (no versionadas: traen nombres). Curso propuesto solo por el título; indicadores comunes sin configurar: % recomienda y NPS (§6.1 del mismo instructivo).

**Estado (2026-10-15):** producción corre **`da2b4d0`** con los reportes de curso (§5). Antes, **estado (2026-10-14, 14:10):** producción corría **`be873ba`** (incluye `54f6919` y `e501ebd`: auditoría UI/UX, menú lateral, barra superior, categorías ocultas, catálogo a 12 por página sin huecos ni desborde, loader de página y botones centrados). Despliegue de `be873ba`: `pull`, `optimize:clear`, build con intercambio seguro, permisos y cachés; verificado `/login`, `/cursos`, `/cursos?page=2`, API, programa y CSS → 200, catálogo sin scroll horizontal. Antes, con `e501ebd`: dump `bd_20261014_1308.sql` y `mera:detach-categories` (5 módulos; respaldo JSON en el servidor). Cuenta `jfcruz@outlook.com` borrada. **Siguiente:** enviar el correo a Capacitación (redactado el 2026-10-14) y que Thania y Pamela acomoden los módulos en carpetas.

**Dato para Capacitación:** el módulo 117 "Módulo 3 - Cuando algo no está bien" (contenido del Código de Ética) tenía la categoría "Protocolo Sismos"; ya quedó sin categoría, pero conviene acomodarlo en la carpeta correcta.

### Hecho en `qa` (2026-10-14): loader y bloqueo de botones al enviar formularios
- `resources/js/modules/submit-guard.js`: en todo formulario `POST` (incluye PUT/PATCH/DELETE por `_method`) reaparece el loader con "Procesando…" (a los 150 ms, para no parpadear en respuestas rápidas) y se **deshabilitan los botones de envío** (un instante después del envío, para que el navegador no pierda el `name`/`value` del botón pulsado). `confirm.js` lo reutiliza después de confirmar. Al volver con "Atrás" se restaura todo.
- No aplica a formularios `GET` (filtros), a subidas con barra propia (`data-upload-form`) ni a formularios con `data-no-loader` o `target` externo.
- Verificado en navegador con servidor lento simulado: triple clic → **1 sola solicitud**; "Enviar link" muestra el loader; las búsquedas no.

### Para el correo que se va a redactar (pendiente)
- **Reportes de curso (en producción desde el 2026-10-15; `docs/reportes/INSTRUCTIVO_REPORTES.md`):** preguntar también qué significa **"Del lugar 13 en adelante: aún sin cumplimiento"** en el ranking (viene fijo de la plantilla; hoy es `config('mera.reports.dashboard.ranking_low_from')`): ¿es una meta fija o debería depender de un % de avance? Además: al terminar, explicar el flujo (subir los 3 Excel por corte, actualizar un corte, link para compartir) y **preguntar por la conexión por API** con la plataforma de capacitación (MERA World/SuperCompany): si existe, con qué credenciales y quién la autoriza. Con API ya no habría que exportar ni subir los Excel en cada corte; mientras tanto se trabaja con los archivos.
- **Categorías:** avisar que **se ocultaron** del panel y del catálogo `/cursos` (2026-10-14) y que los 5 módulos que tenían una quedaron **sin categoría**, libres para acomodarse solo en carpetas. Preguntar: **¿quieren esta funcionalidad?** Y si sí, **¿cómo funcionaría?** (p. ej. temas como Seguridad, Higiene o Liderazgo para agrupar **carpetas de curso**, tomados del código de MERA World: `NRM-HIG-…` → tipo Normativo, área Higiene). Se reactivan sin tocar código con `MERA_CATEGORIES_ENABLED=true` + `config:cache` (las 3 categorías siguen guardadas y el respaldo JSON permite devolver las asignaciones).

- **Seguimiento anual por persona (`docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md` §10):** respondido por Capacitación el 2026-10-19. Realizado = 100 % + evaluación presentada (regla actual, no cambia); aprobado = realizado con ≥ 80 (≥ 90 en Corresponsal Cambiario); la plataforma ya exporta la calificación más alta; solo cuenta 2026 (lo de 2025 "es del año pasado"): corregirán en la plataforma solo los 5 códigos de 2026. Pendiente de ellas: exportaciones de encuestas de otros cursos (las preguntas cambian en algunos).

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

> Las filas nuevas van **al final de esta tabla** (después de la última fila con fecha), no al final del archivo: fuera de la tabla no se ven como bitácora.

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
| 2026-10-12 | Despliegue de `97ce623`: se volvió a incluir `npm ci && npm run build` en la guía (ya prohibido por la lección 1) y el usuario lo canceló en el servidor. Se agregó la **regla 7** (nunca compilar en el servidor; leer §5 completo antes de cualquier guía) y el procedimiento "Frontend (`public/build`)": build en local → `storage/deploy/build_<commit>.zip` → subir → descomprimir en `public/` → borrar el ZIP. Generado `build_97ce623.zip`. |
| 2026-10-12 | **Caída parcial** (`/login`, `/cursos` y el programa en 500; la API siguió en 200): la guía movía `public/build` a respaldo **antes** de comprobar que el ZIP estuviera en `public/`; el `unzip` falló y el sitio quedó sin build. Restauración: devolver el build respaldado. Se corrigió el procedimiento (§5, "Frontend"): extraer en `build_nuevo`, verificar el manifest y solo entonces intercambiar. |
| 2026-10-12 | Sitio restablecido e instalado el build de `97ce623` (el ZIP estaba en `~`; se extrajo en `build_nuevo`, se verificó el manifest y se intercambió). **Programa publicado y verificado en producción:** `/proceso-de-manipulacion-de-alimentos` 200, los 5 módulos 200 (resueltos por slug), evaluación y encuesta presentes, avance funcionando en móvil, sin errores de JS. `/login`, `/cursos`, API y WordPress (120 cursos) en 200; `/register` e `info.php` en 404. Se borró el `node_modules` del servidor. |
| 2026-10-13 | Nuevo requerimiento en análisis: **carpetas de cursos** en lugar de categorías, con subcarpetas por versión (ES/EN). Se analizaron los 120 módulos de producción (≈25 cursos; hay versiones por región "LATAM") y se redactaron 10 preguntas sin tecnicismos para la cliente (§10). Sin código todavía. |
| 2026-10-13 | Recorrido del flujo real del colaborador en **SuperCompany / MERA World** (`mera.supercompany.com`), con la sesión de Jesus C. (vista de colaborador, sin acciones que modificaran datos). Hallazgo central: **SuperCompany es el LMS** (inscripción, avance, evaluación, encuesta, firma, DC3) y Mera Universidad solo **aloja el contenido** que se abre con "Ir a Curso". El avance es **manual** ("Marcar como completado"); los cursos son exportaciones web de **isEazy Author**, sin SCORM/xAPI. Los enlaces a los módulos están **pegados a mano** en SuperCompany, con carpeta por slug y doble slash. §10 se reescribe con el proceso actual y 12 preguntas ajustadas. Se agrega §11 (insumos para la futura propuesta de intranet; la propuesta todavía no se redacta). |
| 2026-10-13 | Tania confirmó que se incorporará **Estados Unidos**. Cuestionario final de 13 preguntas (§10): agrega crecimiento por país e idioma y administración por país; quita orden obligatorio y seguimiento (son de SuperCompany). Migración por selección múltiple (decisión del usuario). Se adoptó en local la versión de CLAUDE.md trabajada en Claude web (respaldo en `storage/backups/`). |
| 2026-10-13 | **Alcance aprobado:** solo carpetas de curso → Español/Inglés → módulos en orden, con "Mover a…" en lote (§10). Ediciones, países, programas, código de SuperCompany y lo demás pasan a stand by. |
| 2026-10-13 | **Implementado en local (alcance aprobado §10):** carpetas de curso (`course_groups`, migración aditiva con respaldo previo), idioma y orden por módulo, "Mover a…" en lote, pestañas Español/Inglés, subir y bajar, quitar de carpeta, borrar carpeta sin borrar módulos; campos nuevos en la API (sin quitar ninguno). **Programas multi-idioma:** una URL por idioma (`/proceso-de-manipulacion-de-alimentos` y `/food-handling-process`), textos en `lang/<idioma>/program.php`, módulos por idioma (lista o carpeta de curso), formularios por idioma con respaldo al español y selector de idioma con `hreflang`; el inglés no se publica mientras no tenga módulos. **Favicon** `images/favicon_mera.png` (M blanca sobre verde MERA) en todos los layouts. 77 pruebas en verde; revisado en escritorio y móvil. Pendiente: commit/push (autorización) y despliegue. |
| 2026-10-13 | **Página pública por carpeta** (sacada de stand by por el usuario): `/<slug>` y `/<slug>/en`; nace **apagada** (404 al público, vista previa para admins) hasta pulsar "Publicar"; link estable al renombrar y editable; links reservados protegidos; descripción y formularios ES/EN con respaldo al español; recuadro "Página pública" con copiar link. Migración aditiva `2026_10_13_000001`. En local la carpeta de prueba del usuario ("Example") se conservó: se descartó rehacer la primera migración para no borrarla. 87 pruebas en verde. |
| 2026-10-13 | **Auditoría en navegador del entorno local** (QA, UX/UI, accesibilidad, móvil 375 px y seguridad), con sesión de Super Admin y sin modificar datos. Hallazgos nuevos en §12: F1-F4, S23-S27, P9-P12, U11-U27, con prioridad y orden sugerido. Sin código todavía. |
| 2026-10-13 | **Correcciones de la auditoría §12 (local, sin commit):** 22 hallazgos resueltos y F2 parcial (detalle en §12, "Cómo quedó"). Nuevos: middleware `SecurityHeaders`, comando `mera:check-courses`, ruta `course-groups.reorder`, módulos JS `sortable-modules`, `unsaved` y filtros plegables. CLAUDE.md unificado con la versión de Claude web. 102 pruebas en verde, build local y QA visual en 1366 y 375 px con un usuario QA temporal (respaldo de la BD antes; usuario borrado al terminar). |
| 2026-10-13 | **Decisiones del usuario sobre §12 (local, sin commit):** U15 (Cursos → Módulos en el panel), F2 (pantalla Papelera con Restaurar; migración aditiva `2026_10_13_000002`, respaldo de BD antes), U24 (link por correo para crear contraseña; broker `invitations`), cuenta jfcruz@outlook.com borrada en local (dump antes; falta en producción), `delete-user-form` borrado con respaldo, y el programa de alimentos ahora toma módulos y formularios de la carpeta `manipulacion-de-alimentos` sin cambiar su URL (comando `mera:program-folder`). Incidente menor: un `preg_replace` dejó vacío `HasUniqueSlug.php`; se recuperó del historial de la sesión. 112 pruebas en verde, build local y QA visual. |
| 2026-10-13 | **P11, P10 y U27 (local, sin commit):** P11 medido en local (6-76 ms por página) y en producción (~200 ms en `/login`, la mayor parte red/TLS): sin problema de rendimiento; la lentitud local es del entorno (sin OPcache, `artisan serve`). P10: fuera jQuery UI, Waves, `widget.css` y `script.js` de la plantilla (~297 KB), reemplazado por `panel-template.js`; capturas antes/después de 13 pantallas sin cambios visibles. U27: tarjetas del catálogo con un solo enlace y portada con espacio reservado. Indicación del usuario: los datos locales no reflejan producción; el acomodo de contenido lo hacen Thania y Pamela. 114 pruebas en verde. |
| 2026-10-14 | Plantilla de correo con la línea de marca MERA (invitación y "olvidé mi contraseña", en español). Cuenta SMTP definida: `notificaciones@meracorporation.com` (contraseña solo en el `.env` del servidor). 115 pruebas en verde. Commit y push a `dev` autorizados por el usuario. |
| 2026-10-14 | **Despliegue de `19dd31a`** en producción: respaldos (commit, `.env`, BD `bd_20261014_1106.sql`, diff de `tests/`), `pull`, `optimize:clear`, 3 migraciones aditivas, build `19dd31a` con intercambio seguro (assets venían en `666` → `644`), `.env` con `APP_NAME` y SMTP (host correcto `meracorporation.com`; `535` resuelto restableciendo la contraseña en cPanel), `mera:program-folder` (5 módulos LATAM en Español). Verificado desde fuera y en navegador. |
| 2026-10-14 | Cuenta `jfcruz@outlook.com` borrada en producción (S26). Quedan 3 usuarios; el usuario les envía el correo de restablecimiento para validar el flujo de correo en producción. |
| 2026-10-14 | **Auditoría UI/UX + QA en producción (18 hallazgos) y menú lateral** corregidos en local (§13): modales sin desborde y con "Cancelar" legible, tono info/peligro, jerarquía de botones, contraste AA, tablet con tarjetas en 2 columnas, encabezados unificados, menú plano por secciones sin la sombra desfasada del logo. Capturas antes/después en 1523/1280/768/375 px. 116 pruebas en verde. |
| 2026-10-14 | Barra superior: nombre y rol del usuario centrados. **Categorías retiradas** (opción 2, interruptor apagado por defecto; API intacta), catálogo `/cursos` a 12 por página y `mera:detach-categories` (5 módulos sin categoría en local, con dump y respaldo JSON). Pendiente: preguntar en el correo si quieren categorías y cómo funcionarían (§6). 119 pruebas en verde. **Commit y push a `dev` autorizados por el usuario**; falta desplegar. |
| 2026-10-14 | **Despliegue de `e501ebd`** en producción (sin migraciones): dump, `pull`, build con intercambio seguro, permisos, cachés y `mera:detach-categories` (5 módulos; respaldo JSON en el servidor). Verificado en navegador. Detectado: paginación de `/cursos` desborda a ~740 px. |
| 2026-10-14 | Reporte en producción de `/cursos` (tras `e501ebd`): botón de acceso gigante y scroll horizontal por la paginación a ~640 px de ancho efectivo. Corregido en local: regla de ancho completo acotada al encabezado de los listados, botón compacto en el catálogo y vista de paginación publicada (corte `md`, "Página X de Y", `onEachSide(1)`). Revisión a fondo sin desbordes en 6 anchos y 13 pantallas del panel + `/cursos` en 8 anchos. 120 pruebas en verde. Sin commit. |
| 2026-10-14 | `54f6919` subido a `dev` (botón compacto y paginación de `/cursos`). Después, en local: cuadrícula del catálogo sin huecos (1/2/3/4 columnas), loader de página en los 4 layouts y contenido de botones centrado; revisión de 75 botones y 13 pantallas sin desbordes. 120 pruebas en verde. Commit y push a `dev` autorizados por el usuario. |
| 2026-10-14 | **Despliegue de `be873ba`** (sin migraciones; solo vistas, CSS y build). Verificado. Redactado el correo para Capacitación con el nuevo funcionamiento y la pregunta sobre categorías. |
| 2026-10-14 | Anotado como pendiente (§6, pedido del usuario): el loader no aparece al enviar formularios (p. ej. al mandar un correo) y falta bloquear de forma general los botones de envío para evitar solicitudes dobles. |
| 2026-10-14 | Nueva rama de trabajo **`qa`**, creada desde `dev` (`be873ba`). Renombrar `dev` no fue posible (el usuario no es dueño del repo). Flujo: trabajo en `qa`, producción en `dev` (§5). |
| 2026-10-14 | En `qa`: loader y bloqueo de botones al enviar formularios (`submit-guard.js`). **Reportes de curso, pasos 1-3 de `docs/reportes/INSTRUCTIVO_REPORTES.md` §11:** decisiones de §10 resueltas (acceso admin y super admin, palabras clave + corrección, todos los cortes, link para compartir, actualizar cortes, seeder por curso, API para el correo), `openspout` 4.25.0 con `platform.php` 8.1.34, lector y armado del reporte. **Coincide exactamente con el script de referencia** con los Excel reales (`mera:report-verify`). 132 pruebas en verde. Sin commit. |
| 2026-10-14 | En `qa`: **reportes de curso, paso 4** (`docs/reportes/INSTRUCTIVO_REPORTES.md` §11): tabla `course_reports` (migración aditiva, aplicada en local con respaldo), cortes por carpeta (crear, actualizar o recalcular, eliminar), link para compartir sin sesión y clasificación manual de comentarios. Corregido: los Excel quedaban en la carpeta temporal si el archivo traía mal una columna (handle abierto de openspout en Windows). Barra superior en móvil: se ocultó el bloque de usuario de escritorio, que flotaba debajo de la barra en ≤ 992 px (lo provocaba una regla de `e501ebd`, ya en producción), y el menú de usuario móvil ya no se corta a 375 px. 142 pruebas en verde; QA visual en 1366 y 375 px. |
| 2026-10-15 | `94b58d8` subido a `qa` (paso 4, autorizado). En `qa`, sin commit: **reportes paso 5** (tablero con d3 7.9.0, filtros, "Descargar HTML" autocontenido; ver `docs/reportes/INSTRUCTIVO_REPORTES.md` §11). **Módulos:** "Mover a…" ahora ofrece **"+ Nueva carpeta…"** (crea la carpeta y mueve en un solo paso, sin perder el filtro; nombre repetido avisado en el navegador) y el botón "Cancelar" de esa barra ya se lee (texto blanco; la regla general lo dejaba verde sobre verde). 146 pruebas en verde; QA en 1366 y 375 px con usuario temporal (respaldos de la BD antes; datos de prueba borrados). |
| 2026-10-15 | Ajustes pedidos por el usuario (en `qa`): página en inglés de la carpeta en **`/en/<slug>`** (antes `/<slug>/en`, que ahora redirige con 301); hover de la tabla de Módulos sin `scale` (provocaba una barra horizontal intermitente); fecha de corte opcional (vacía = hoy en México); botón "Seleccionar archivo" legible en hover (Bootstrap lo pintaba gris); logo blanco en "Descargar HTML" (`images/logo-mera-university-blanco.png`). Revisión del código de la plantilla: umbrales en `config('mera.reports.dashboard')` (mismos valores), redibujo solo si cambia el ancho, filtros en la URL, tablas que caben en 375 px y `mera:purge-report-uploads` cada hora para Excel abandonados. **Reportes paso 6:** §3 y guía de despliegue en §5. 149 pruebas en verde; QA en navegador con usuario temporal (respaldo antes; datos de prueba borrados). Commit y push a `qa` autorizados por el usuario. |
| 2026-10-15 | **Entorno QA en el servidor:** `university.meracorporation.com` → `~/qa.cursos.meracorporation.com` (worktree de la rama `qa`, `684ba13`). BD `merabl_cursos_qa` (copia de producción: 14 tablas, 120 cursos, 4 usuarios; dump `bd_para_qa_20261015_1435.sql`) con usuario propio sin acceso a `merabl_cursos`; migración `course_reports` aplicada solo en QA; `.env` propio (staging, correo a log, llave propia; `DB_PASSWORD` entre comillas simples o da `1045`); `.user.ini` y `higiene.csv` copiados; build `684ba13` instalado; cron de QA agregado. Producción sin cambios (`be873ba`, 200). Corregido en §5 el commit de producción (`19dd31a` → `be873ba`). Guía: `docs/despliegue/QA_UNIVERSITY.md`. |
| 2026-10-15 | **QA sin indexar:** `SecurityHeaders` agrega `X-Robots-Tag: noindex, nofollow, noarchive` cuando `APP_ENV` no es `production` (QA = `staging`); respeta el `noindex` propio de los reportes compartidos. Producción sin cambios (prueba incluida). `robots.txt` igual (con `Disallow`, Google no leería el `noindex`). Falta aplicarlo en el servidor de QA: `git pull origin qa` + `config:cache` (`docs/despliegue/QA_UNIVERSITY.md`, paso 11). 154 pruebas en verde. |
| 2026-10-15 | **QA actualizado a `3b5fed8`** en el servidor (`~/qa.cursos.meracorporation.com`): respaldo del commit anterior (`~/backups_cursos/commit_anterior_qa_<TS>.txt`, era `684ba13`), `git pull origin qa` (fast-forward, 4 archivos), `optimize:clear` y cachés `config`/`route`/`view`. Sin build, migraciones ni Composer. **Verificado:** `curl -sI https://university.meracorporation.com/login` → `x-robots-tag: noindex, nofollow, noarchive`. `git status` en QA mostró: `.user.ini` sin versionar (esperado: copiado de producción, paso 7 de la guía) y **`tests/Feature/Courses/CourseManagementTest.php` modificado**, el mismo archivo que apareció modificado en producción el 2026-10-12 y se descartó el 2026-10-14. **Causa confirmada con `git diff`:** el escáner del hosting (probablemente Imunify360) cambia en el fixture `'AddType application/x-httpd-php .png'` → `'… .php'` (neutraliza lo que parece un `.htaccess` malicioso). Es el mismo cambio visto en producción. Inofensivo (`tests/` no está en `public/` y no se ejecuta en el servidor). **Antes de cada `pull`** en producción o QA: si aparece, `git diff tests/Feature/Courses/CourseManagementTest.php > ~/backups_cursos/tests_servidor_$TS.diff` y `git checkout -- tests/Feature/Courses/CourseManagementTest.php`. Mejora posible (en `qa`, con permiso): armar esa cadena por partes para que el escáner no la detecte, o pedir al hosting que excluya `tests/`. |
| 2026-10-15 | **Despliegue de reportes de curso en producción** (`be873ba` → `da2b4d0`), tras verificarlos en QA (corte de Higiene con las cifras de `INSTRUCTIVO_REPORTES.md` §9, descarga HTML, compartir sin sesión, `/en/<carpeta>`, "+ Nueva carpeta…"). Respaldos `TS=20261015_1911`; Composer (`openspout`), migración `course_reports`, `higiene.csv`, build `684ba13`, cachés y permisos. Verificado por `curl` y en navegador. **Límite de subida:** la app muestra 1,000 MB; el `.user.ini` de la raíz no aplica y no debe copiarse a `public/` (bajaría a 512 MB). |
| 2026-10-19 | En `qa`, sin commit: **carpetas por año y versión por país** (`docs/carpetas/INSTRUCTIVO_VERSIONES.md`, §9 respondido con el usuario: Español → México salvo excepciones, año del código, México por defecto, sufijos pendientes con Capacitación, "Nuevo año" copia solo la estructura, un módulo en una sola versión, sin fusiones, URLs con la versión primero: `/eua/<carpeta>`). Migración aditiva (`markets`, `course_group_versions`), organizador por versión, pantalla **Versiones**, pestañas año/versión en la carpeta, publicación y reportes por versión, comando `mera:versions-from-languages` (aplicado en local con dump y JSON previos), 301 desde `/en/<carpeta>` y `/<carpeta>/en`, programa de alimentos en la versión Latam, API con 4 campos nuevos. 172 pruebas en verde; QA visual en local (1366 y 375 px) con usuario temporal (borrado). |
| 2026-10-19 | En `qa`, sin commit (pedido del usuario): **(1) Borrar no funcionaba en producción** (carpetas, reportes y toda confirmación de eliminar): `confirm.js` pasaba dos clases en `className` y SweetAlert 2.1.2 solo acepta una (`InvalidCharacterError`); venía de `e501ebd`. Corregido. **(2) Carpeta reorganizada por niveles:** la carpeta muestra años y las versiones del año como tarjetas; cada versión tiene su página `/course-groups/<carpeta>/versiones/<id>` con ruta de navegación y pestañas Módulos / Link para compartir / Reportes (una a la vez); botones secundarios en contorno, acciones de módulo neutras y un solo botón verde por sección. `?version=` redirige a la página nueva. **(3) Cintillo "Entorno de pruebas"** fijo abajo en todas las páginas fuera de producción (también el HTML descargado), con **"Apagar todo lo compartido"** (admin y super admin; apaga las páginas de versión y revoca los links de reportes; en producción la ruta da 404). Las páginas de programa (`config/mera.php`) no se apagan con ese botón. 176 pruebas en verde; QA visual en 1366 y 375 px con usuario temporal (dump antes; usuario borrado; lo publicado para probar quedó apagado como estaba). |
| 2026-10-19 | En `qa`, sin commit (pedido del usuario): **link de la página pública = ruta del panel**: `/<carpeta>/<año>/<versión>` (p. ej. `/example/2026/mexico`; antes `/<versión>/<carpeta>[/<año>]`, nunca desplegado). La página muestra **solo** esa versión: sin selector de versión ni de año ni `hreflang`, y la línea superior dice cuál es ("Curso en línea · 2026 · México"). `/<carpeta>` y `/<carpeta>/<año>` → 301 a la versión por defecto (México) más reciente o la primera visible; `/en/<carpeta>` y `/<carpeta>/en` → 301 a EUA más reciente (links ya compartidos en producción). El botón **"Apagar todo lo compartido"** del cintillo ahora se ve siempre (antes solo con algo compartido; con 0 decía "Nada compartido" y el usuario no lo encontró). **Reporte general de todos los cursos** (`docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md`): sigue sin implementar; falta responder su §10. 175 pruebas en verde; revisado en navegador (redirecciones y página pública), dump antes y usuario temporal borrado. |
| 2026-10-19 | `97b1763` subido a `qa` (autorizado): versiones por país, corrección de borrar, carpeta por niveles, links `/<carpeta>/<año>/<versión>` y cintillo de pruebas. **Seguimiento anual por persona:** §10 de `docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md` respondido (37 cursos en lista editable, equivalencias propuestas + confirmar, admin y super admin, cruce con el reporte de personal, encuesta solo de Higiene, 6 cortes). Capacitación respondió: realizado = 100 % + evaluación presentada (el 79 % de Higiene es correcto); aprobado = realizado con ≥ 80 (≥ 90 en Corresponsal Cambiario); la plataforma exporta la calificación más alta; corregirán los 15 códigos en la plataforma; las encuestas cambian en algunos cursos (enviarán ejemplos). |
| 2026-10-19 | `4d54e5f` subido a `qa` (documentación, autorizado). **Reporte general, paso 2 (en `qa`): catálogo de cursos.** Migración aditiva `course_catalog` (aplicada en local con dump previo), importador del Excel `Catalogo_MERA_v12.xlsx` (72 cursos, 14 códigos alternos; idempotente, no pisa lo del panel ni borra), comando `mera:catalog-import` y pantalla **Catálogo de cursos** (super admin). Reglas de Capacitación: realizado = 100 % + evaluación; aprobado = realizado con ≥ mínima (80; Corresponsal Cambiario 90; Inducción sin evaluación). **Incidente evitado:** se sobrescribió por error `resources/views/catalog/index.blade.php` (catálogo público `/cursos`); se restauró con `git checkout` antes de cualquier commit y las vistas nuevas van en `course-catalog/`. Solo cuentan los cursos de 2026 en adelante (`mera.progress.tracking_from_year`; los 22 de 2025 quedan con "No cuenta", aplicado en local con dump previo). También se evitó un choque de nombres de ruta: la pantalla nueva usa `course-catalog.*` (`catalog.index` es `/cursos`); las pruebas lo detectaron. 180 pruebas en verde; QA visual en 1366 y 375 px con usuario temporal (borrado). Se corrigieron los textos de link en Versiones (`/<carpeta>/<año>/<versión>`). |
| 2026-10-19 | En `qa`, sin commit (pedido del usuario): **(1)** Catálogo de cursos paginado de 10. **(2) URLs en español** en todo el sitio (`/iniciar-sesion`, `/panel`, `/modulos`, `/carpetas`, `/usuarios/nuevo`, `/modulos/<id>/editar`…; `Route::resourceVerbs` nuevo/editar); las anteriores en inglés redirigen con 301 conservando la consulta (`LegacyPaths`); `/api/courses` no cambia (WordPress). **(3) Reporte general (seguimiento anual), pasos 3-6 de `INSTRUCTIVO_REPORTE_PERSONAS.md`:** `OrgMapper` (reporte por curso idéntico con los Excel reales: `mera:report-verify` coincide fila por fila), migración `progress_reports/_people/_items` (aplicada en local con dump previo), `FastSheetReader` (lectura 143 s → 13 s), armado con el reporte de personal (bajas fuera), `mera:progress-verify` con los Excel reales del 12-oct: 2026 = 37 cursos, 58,405 asignaciones, 3,300 personas, realizado 69.9 %, aprobado 56.5 %, promedio 86.7 (cuadra con §9 descontando las 11 bajas); Liderazgo Positivo 2026: 509 realizados (prototipo: 511). Pantallas `/seguimiento` (cortes, nuevo corte, tablero con filtros, consulta de colaboradores con CSV y ficha individual imprimible), menú "Seguimiento anual" (admin y super admin). Tablero: 1 consulta + cálculo en PHP (8.6 s → 1.5 s en local). Corte real creado en local en ~68 s (se conserva en la BD local para revisarlo). Pendiente: encuestas por curso (Capacitación mandó 5 ejemplos el 19-oct), links por localidad y "Descargar HTML". 188 pruebas en verde; QA visual en 1366 y 375 px con usuario temporal (borrado). |

## 10. Carpetas de cursos: ALCANCE APROBADO (2026-10-13)

**Lo único que se implementa ahora** (decisión del usuario, 2026-10-13). Todo lo demás de esta sección queda en **stand by** (ver "Análisis previo", más abajo): no implementarlo ni proponerlo como siguiente paso.

### Qué se construye
**Curso (carpeta) → Idioma (Español / Inglés) → Módulos en orden.**
- **Módulo = un registro actual de `courses`.** No se crea una tabla de módulos nueva ni se mueve ningún archivo.
- **Curso (carpeta):** tabla nueva (p. ej. `course_groups`): nombre, slug, orden. Sin portada ni descripción obligatorias.
- **Idioma:** solo dos valores fijos, `es` y `en`. Se guardan en el módulo (columna `language`), no en una tabla. Dentro de cada carpeta se muestran como dos subcarpetas: Español e Inglés.
- **En el módulo:** columnas nuevas, todas *nullable*: `course_group_id`, `language`, `position`. Un módulo sin carpeta aparece en "Sin carpeta".

### Panel (solo administradores)
- Lista de carpetas de curso: crear, renombrar, ordenar y borrar. Al borrar una carpeta, sus módulos regresan a "Sin carpeta"; no se borra ningún módulo.
- Dentro de una carpeta: dos pestañas o subcarpetas, **Español** e **Inglés**, con los módulos en orden y forma de cambiarlo (número o subir/bajar).
- **Acomodo de lo existente:** selección múltiple de módulos → "Mover a…" (carpeta + idioma). Capacitación hace el acomodo de los ~120 módulos.
- Al subir o editar un módulo: elegir carpeta, idioma y posición (opcionales).

### Restricciones (no negociables)
- **Ninguna URL de módulo cambia.** Los enlaces pegados en MERA World (SuperCompany) apuntan a `storage/cursos/<carpeta>/index.html`. La carpeta de curso es solo una agrupación en la BD.
- `/api/courses`, el catálogo `/cursos`, la página de WordPress y `/proceso-de-manipulacion-de-alimentos` siguen funcionando igual. Se pueden **agregar** campos a la API, pero no quitar ni renombrar.
- Las **categorías** actuales no se borran en esta fase (la API usa `category_id`). Su reemplazo queda en stand by.
- Migración solo aditiva, con respaldo previo (regla 2), pruebas en sqlite (regla 3) y build en local (regla 7).
- Las versiones LATAM o por país **no** son un tercer idioma: por ahora Capacitación decide si las pone en el mismo curso (en Español o Inglés) o en una carpeta aparte (p. ej., "Manejo de alimentos LATAM").

### Implementación (2026-10-13, en local; pendiente commit y despliegue)
**Carpetas de curso** (alcance aprobado):
- **Migración** `2026_10_13_000000_create_course_groups_table`, solo aditiva:
  - Tabla `course_groups` (`name`, `slug`, `position`).
  - En `courses`: `course_group_id` (nullable, FK `nullOnDelete`), `language` (`es`/`en`) y `position` (nullable).
  - Ninguna URL ni archivo cambia.
- **Código:**
  - `App\Models\CourseGroup` (idiomas fijos en `CourseGroup::LANGUAGES`).
  - `Course::group()` y el scope `ordered()`.
  - `App\Services\Courses\CourseGroupOrganizer`: mover en lote, subir/bajar, colocar en posición, quitar, borrar carpeta y renumerar sin huecos. Toda la lógica de acomodo pasa por aquí.
  - `CourseGroupController`, rutas `course-groups.*`, `courses.bulk-move`, `courses.move` y `courses.ungroup`.
- **Panel:**
  - Menú "Carpetas de curso".
  - Lista de carpetas con conteo ES/EN.
  - Detalle con pestañas Español/Inglés: subir/bajar, ver, editar y quitar.
  - En Cursos: filtro "Carpeta", columna "Carpeta · Idioma" y selección múltiple con la barra "Mover a…". "Agregar módulos" llega con la carpeta e idioma preseleccionados.
  - En el formulario del módulo: carpeta, idioma y n.º de módulo (opcionales).
- **API:** se **agregaron** `course_group_id`, `course_group`, `language` y `position`. No se quitó nada.
- **Pruebas:** `tests/Feature/CourseGroupTest.php`.

**Links multi-idioma de programas** (pedido del usuario, 2026-10-13):
- **Una URL por idioma**, definida en `config/mera.php → programs.<clave>.languages.<idioma>.slug`:
  - `/proceso-de-manipulacion-de-alimentos` (es).
  - `/food-handling-process` (en).
  - Selector de idioma y `<link rel="alternate" hreflang>`.
  - **Un idioma sin módulos no se publica** (404, y no aparece en el selector). Hoy el inglés de este programa no tiene módulos: no existe en producción.
- **Textos traducibles:**
  - Los fijos de la página (pasos, botones, consejo, avance, evaluación/encuesta, valores) están en `lang/es/program.php` y `lang/en/program.php`.
  - Los propios del programa (título, descripción, títulos de módulos) van por idioma en la config.
  - El JS de avance recibe sus textos ya traducidos por `data-i18n-*`.
- **Módulos por idioma:** `modules` (lista explícita por slug de curso, como hoy en español) **o** `course_group => '<slug-de-carpeta>'`, que toma los módulos de ese idioma de la carpeta y en su orden. **Para publicar el inglés basta con una de las dos.**
- **Formularios:** `actions[].url` acepta `['es' => …, 'en' => …]`. Si el idioma no tiene URL, usa la del idioma por defecto. **Hoy ambos idiomas usan los formularios en español** (decisión del usuario: preparado, sin separar todavía).
- **Avance:** la clave de `localStorage` es el slug de cada idioma. La de español no cambió, así que se conserva el avance de quien ya empezó.
- **Código:** `App\Support\ProgramCatalog`, `ProgramController`, `programs/show.blade.php` y `layouts/program.blade.php`.

**Página pública por carpeta** (sacada de stand by por el usuario, 2026-10-13):
- **Links:** `cursos.meracorporation.com/<slug>` (español) y **`/<slug>/en`** (inglés), con selector de idioma y `hreflang`. Usa la misma vista y el mismo diseño que `/proceso-de-manipulacion-de-alimentos`, incluido el avance por dispositivo (clave `carpeta:<slug>:<idioma>`).
- **Nace apagada** (`is_published = false`). Mientras está apagada:
  - El público recibe **404**.
  - Los administradores ven una **vista previa** con aviso amarillo y `noindex`.
  - No se puede publicar una carpeta sin módulos con idioma.
- **Un idioma sin módulos no se muestra al público** (404) ni aparece en el selector.
- **El link (slug) no cambia al renombrar** la carpeta (`slugFollowsName() = false`). Se edita a mano en "Editar carpeta", con aviso si la página ya está publicada.
- **Links reservados** (`CourseGroup::reservedSlugs()`): rutas de la app (login, cursos, dashboard…), carpetas de `public/` (images, css, storage…), los slugs de programas en config, más `en`, `es`, `api` y `admin`. Un nombre como "Login" genera `login-2` solo.
- **Rutas** `course-pages.show` y `course-pages.show.lang` **al final** de `routes/web.php`, para que cualquier ruta existente se resuelva antes. Efecto aceptado: un `POST` a una ruta de un solo segmento que no existe responde **405** en vez de 404 (p. ej. `POST /register`; sigue siendo imposible registrarse).
- **Panel**, dentro de cada carpeta:
  - Recuadro "Página pública" con estado (Publicada/Apagada), botón **Publicar/Apagar** (con confirmación) y, por idioma, el link con **Copiar link** y **Ver/Vista previa**, más un aviso si ese idioma no tiene módulos.
  - La lista de carpetas muestra "Página publicada/apagada".
  - "Editar carpeta" incluye: nombre en inglés (título de la página en inglés), link, descripción ES/EN y links de **evaluación** y **encuesta** ES/EN. Solo se aceptan `https`. Si el inglés va vacío, usa el de español.
- **Migración** `2026_10_13_000001_add_public_page_to_course_groups_table` (aditiva): `name_en`, `description_es/en`, `evaluation_url_es/en`, `survey_url_es/en`, `is_published` y `published_at`.
- **Código:** `CoursePageController`, `CourseGroup::publicUrl()/titleFor()/descriptionFor()/actionUrl()`, `CourseGroupController::publish()`, `course-groups/_public-page.blade.php` y el botón copiar en `resources/js/modules/course-groups.js`.
- **Pruebas:** `tests/Feature/CoursePageTest.php`.

**Favicon:** `partials/favicon.blade.php` en los 4 layouts (`app`, `guest`, `public` y `program`) → `public/images/favicon_mera.png`. Es la "M" blanca oficial de meracorporation.com sobre un cuadrado redondeado verde `#024D25`; la original era blanca sobre transparente y no se veía en pestañas claras. Se puede reemplazar por otra con el mismo nombre.

### Despliegue de este cambio (lleva migraciones, `.env` y frontend)
Incluye: carpetas de curso, páginas públicas, programas multi-idioma, favicon, correcciones de §12, Papelera (F2), link para crear contraseña (U24) y la carpeta del programa de alimentos.
1. **En local**, después del commit:
   - `npm run build`.
   - Generar `storage/deploy/build_<commit>.zip` (§5, "Frontend").
2. **En el servidor:**
   1. `mysqldump` con respaldo (lección 5 de §5).
   2. `git pull origin dev`.
   3. `php artisan optimize:clear`.
   4. `php artisan migrate --force`. Son **3 migraciones, todas aditivas**: `course_groups` (con los campos de la página pública), 3 columnas nulas en `courses` (carpeta, idioma, posición) y `deleted_at` + `trash_items` en `courses` (Papelera).
   5. **`.env`** (respaldo antes, regla 2):
      - `APP_NAME="Mera Universidad"` (pendiente desde U26: el título y el remitente de los correos salen de aquí).
      - **Correo (obligatorio para U24):** cuenta **`notificaciones@meracorporation.com`** (definida por el usuario el 2026-10-14). **La contraseña NO va en el repo ni en este archivo** (el repo es público): el usuario la tiene y se escribe solo en el `.env` del servidor. Valores: `MAIL_MAILER=smtp`, `MAIL_HOST` y `MAIL_PORT` según cPanel → Cuentas de correo → "Connect Devices" (normalmente `mail.meracorporation.com`, 465 con `MAIL_ENCRYPTION=ssl`), `MAIL_USERNAME=notificaciones@meracorporation.com`, `MAIL_PASSWORD=<la del usuario, entre comillas si lleva # o .>`, `MAIL_FROM_ADDRESS=notificaciones@meracorporation.com`, `MAIL_FROM_NAME="Mera Universidad"`. Probar con un usuario de prueba antes de avisar a Thania y Pamela. Sin esto, al crear un usuario la cuenta se crea pero el link no llega (el panel lo avisa). Respaldo: `php artisan mera:rotate-password <correo>`.
      - Opcional: `MERA_INVITATION_HOURS` (vigencia del link; 72 por defecto).
   6. Instalar el build con el procedimiento seguro: ZIP verificado → `build_nuevo` → intercambio.
   7. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
   8. **Carpeta del programa de alimentos:** `php artisan mera:program-folder proceso-de-manipulacion-de-alimentos --dry-run` (revisar) y luego sin `--dry-run`. Crea la carpeta `manipulacion-de-alimentos` (apagada) y le acomoda en Español los 5 módulos LATAM del programa. Es idempotente y **no cambia la URL** `/proceso-de-manipulacion-de-alimentos` ni el avance guardado.
   9. **Categorías (2026-10-14, siguiente despliegue):** con `mysqldump` antes, `php artisan mera:detach-categories --dry-run` (revisar la lista; en producción eran 5 módulos) y luego sin `--dry-run`. Guarda el respaldo JSON en `storage/backups/` del servidor. No hace falta tocar el `.env` (las categorías ya quedan ocultas por defecto).
   9. ✅ **Cuenta jfcruz@outlook.com:** borrada en producción el 2026-10-14 (con `mysqldump` antes).
   10. `chmod` (lección 2).
3. **Verificar:**
   - `/course-groups` (panel) y `/papelera` (solo super admin).
   - `/proceso-de-manipulacion-de-alimentos`: los mismos 5 módulos, con sus anclas `#/slide/…`, evaluación y encuesta. `/manipulacion-de-alimentos` redirige a esa URL.
   - `/food-handling-process`: **404** hasta que la carpeta tenga módulos en Inglés.
   - Crear una carpeta de prueba: su `/<slug>` da **404** sin sesión y vista previa con sesión de admin; al publicarla se ve sin sesión. Después borrarla (sus módulos no se borran).
   - Crear un usuario de prueba con un correo propio: debe llegar el link "Crea tu contraseña". Después borrarlo.
   - `/api/courses`: campos nuevos en `null`. WordPress: 120 cursos.

### Stand by (no implementar)
Ediciones por año, país o región como versión, código de SuperCompany, botón "Copiar enlace", cambios al catálogo público, acceso de colaboradores (D1), avance propio. Todo está documentado en el análisis previo.

---

### Análisis previo (stand by)

**Estado: respuestas de Tania recibidas (2026-10-13); falta validar el diseño con ella.** Todavía no hay código: no implementar nada hasta tener sus respuestas.

#### Qué se pidió
Reemplazar las **categorías** por **carpetas de cursos**, porque cada curso tiene varios módulos. Dentro de cada carpeta de curso habrá **subcarpetas por versión** (español e inglés, como mínimo) con sus módulos.

#### Proceso actual del colaborador (recorrido del 2026-10-13)
Hoy intervienen **dos plataformas**. El colaborador nunca entra a Mera Universidad por su cuenta: llega por un enlace desde SuperCompany.

| Paso | Dónde | Qué pasa |
|---|---|---|
| 1. Inicio de sesión | `auth.supercompany.com/auth/login` | Correo y contraseña, "Recordarme", recuperar contraseña, autorregistro ("crea una ahora") y versión en inglés. Al entrar, va a `mera.supercompany.com/lobby` ("MERA World"). |
| 2. Llegar a los cursos | Menú lateral, **Desarrollo → Entrenamiento** (o la app "Entrenamiento", o "Training Center" en Team). También desde **Agenda → Hacer → "2 cursos por completar"** | Tres vistas: **Tablero** (`/training`: curso destacado, cursos activos con %, insignias, últimas unidades), **Auto-aprendizaje** (`/training/trails`: tarjetas con filtros Cursando, Terminado y Pendiente, duración, modalidad, "avance general de colaboradores" y "mínimo aprobatorio: 80") y **Expediciones** (`/training/expeditions`: historial de unidades con tipo, duración, fecha de inscripción, estatus y fecha de término). |
| 3. Abrir el curso | `/training/trail/<id>` (p. ej., 11388981 = Protocolo Sismos) | Barra de progreso, "Detalle del curso" (descripción, número de módulos, duración y contacto de soporte: tanias@meracorporation.com), **"Constancia DC3"** (deshabilitada hasta terminar) y menú lateral de **módulos → unidades**. Cada unidad trae tipo, duración, instrucciones y las pestañas Contenido y Comunidad (comentarios). |
| 4. Tomar el módulo | Botón **"Ir a Curso"** (abre en pestaña nueva) → `cursos.meracorporation.com//storage/cursos/<carpeta-slug>/index.html#/slide/<id>` | Contenido de **isEazy Author** (exportación web; en Sismos M1 son 13 diapositivas). Dentro del módulo, la navegación tiene **candado** ("Siguiente" bloqueado hasta terminar la diapositiva). No reporta nada a SuperCompany. |
| 5. Registrar el avance | De vuelta en SuperCompany | El colaborador pulsa **"Marcar como completado"** en cada unidad. **El avance es declarativo:** se puede marcar sin haber abierto el contenido. |
| 6. Cerrar el curso | SuperCompany | **Evaluación final** nativa (una pregunta por pantalla, 10 preguntas en Sismos, obligatoria; mínimo aprobatorio de 80 en algunos cursos), **encuesta de satisfacción**, a veces **firma digital** de un documento (p. ej., "Firma tu Protocolo de Sismos", desde "Mis documentos") y, al final, la **constancia DC3**. |

**Estructura de un curso en SuperCompany:** Curso (*trail*) → Módulos (secciones) → Unidades. Tipos de unidad vistos: **Curso** (enlace a isEazy), **Experiencia** (actividades de recapitulación), **Examen**, **Encuesta**, **Documento** (firma o presentación), **Artículo** (p. ej., "Consulta tu protocolo por estado", "Links de apoyo oficial") y **Webinar**. Ojo: SuperCompany cuenta como "módulos" también la evaluación y la encuesta (Sismos dice "4 módulos", pero de contenido son 2).

**Nomenclatura de cursos que ya usa Capacitación** (21 cursos activos en la cuenta revisada): `TIPO-ÁREA-NNN-MODALIDAD-AÑO[-PAÍS] | Nombre corto`. Ejemplos: `NRM-EMG-003-EL-26 | Protocolo Sismos`, `NRM-HIG-005-EL-26 | Manipulación de Alimentos`, `CMP-LID-006-BL-26 | Liderazgo Positivo 2026`, `NRM-SEG-005-EL-26-MX | Factores Psicosociales`.
- Tipo (deducido): NRM (normativo), NEG (negocio o cultura), CMP (competencias). Área: SEG, EMG, HIG, LID, CUL, OPE, SUP. Modalidad: EL (*e-learning*), BL (*blended*). Año: 25, 26. País opcional: MX.
- **Hay ediciones por año del mismo curso** (p. ej., Fundamentos Seguridad 001-EL-25 y 002-EL-26; Protocolo Huracán 001-25 y 002-26). El idioma **no** forma parte del código.

#### Implicaciones para el diseño
1. **Mera Universidad hoy funciona como hosting de contenido, no como LMS.** El avance, las calificaciones, la evaluación, la encuesta y la DC3 viven en SuperCompany. Rehacerlos aquí duplicaría registros (ya pasa con `/proceso-de-manipulacion-de-alimentos`, que tiene su propio avance, evaluación y encuesta, mientras SuperCompany tiene el curso `NRM-HIG-005-EL-26` con 5 módulos).
2. **Los enlaces de SuperCompany son un contrato.** Están pegados a mano, apuntan a carpetas por **slug** (`modulo-1-fundamentos-y-prevencion-sismica`), traen doble slash y llevan el `#/slide/<id>` de isEazy. Reorganizar en carpetas **no debe cambiar ninguna URL existente**. Revisar también qué pasa con la carpeta de un curso antiguo cuando se reemplaza su ZIP (carpeta inmutable, §3).
3. **La "carpeta de curso" debería corresponder 1:1 con el curso de SuperCompany** y usar su código como identificador. Las "versiones" pueden ser de idioma, de región (`-MX`, LATAM) o de año.
4. **Avance real:** isEazy puede exportar en SCORM. Si SuperCompany aceptara SCORM, el avance sería automático y no declarativo. No es parte de este desarrollo, pero condiciona si vale la pena construir avance propio.
5. **Acceso:** el contenido se abre sin sesión. Es el tema D1, que sigue delegado: no actuar.
6. **Crecimiento a otros países:** Tania confirmó (2026-10-13) que **se incorporará Estados Unidos**, además de México y LATAM. País e idioma deben ser **dos listas configurables** desde el panel (no columnas ni código fijo), para que agregar EE. UU., otro país u otro idioma sea un dato y no un desarrollo. Una versión = idioma + país (+ edición/año).
7. **Lo que NO se construye aquí** mientras SuperCompany sea el LMS: avance, candado de orden entre módulos, evaluación, encuesta, reportes y DC3. Se preguntó solo para confirmarlo (preguntas 1 y 2).

#### Lo que muestran los datos de producción (120 registros al 2026-10-12)
- Cada registro de `courses` es en realidad un **módulo**. Se agrupan en unos **25 cursos**. Ejemplos:
  - **Manejo de alimentos:** ES (ids 20, 21, 25, 23, 24), EN (108, 118–121), LATAM (133–137) y "Proceso" (122–125, 131).
  - **Seguridad y salud en el trabajo:** ES (43–45), EN (98–100).
  - **Estándares de servicio:** ES (29–33), EN (126–130).
  - **Seguir los procedimientos:** MX (62–65) y **LATAM** (67–70).
  - **Despega con MERA** (35–39); **Plaza Premium / socios** (132, 138–142); **Mera Way 2.0 / NetSuite** (2–4, 18, 19, 80).
- Hay versiones que no cambian de idioma sino de **región (LATAM)**.
- Hoy solo hay 3 categorías: Código de Ética (2 módulos), Protocolo Sismos (3) e Inteligencia Emocional (0). 115 módulos no tienen categoría (relacionado con D5).

#### Propuesta técnica preliminar (a validar con las respuestas)
- Estructura: **Curso (carpeta, con código SuperCompany) → Versión (idioma, región y/o año) → Módulos en orden**.
- Mantener `courses` como tabla de módulos, para no romper la API, el WordPress ni **los enlaces de SuperCompany**. Agregar el orden del módulo y su versión. **URLs existentes intactas**; los módulos nuevos usan carpeta inmutable.
- En el panel: botón **"Copiar enlace"** por módulo (URL limpia, sin doble slash) para pegarlo en SuperCompany, y vista "¿En qué curso de SuperCompany está este módulo?".
- Migrar las 3 categorías actuales. La API actual se conserva y se agrega una nueva para cursos (carpetas).
- Avance, evaluación y encuesta propios: **no**, salvo que la cliente lo pida (pregunta 2). El programa público actual queda como caso aparte.
- Idioma y país como **catálogos configurables** (EE. UU. confirmado); permisos de administración por país si la pregunta 7 lo pide.
- Migración de los 120 módulos: **selección múltiple "Mover a curso"** en el panel (decisión del usuario); Capacitación hace el acomodo.

#### Preguntas para la cliente (versión final, 2026-10-13)
Cuestionario para Tania (Capacitación), con opciones para marcar. Reemplaza las versiones anteriores. Se quitaron a propósito el **orden obligatorio entre módulos** y el **seguimiento del colaborador**: los resuelve MERA World (SuperCompany), no Mera Universidad.

Contexto para ella: hoy los colaboradores toman los cursos en **MERA World**: ahí se inscriben, ven su avance, hacen la evaluación y la encuesta y obtienen su DC3. El botón "Ir a Curso" abre el módulo guardado en **Mera Universidad**, donde cada módulo aparece como un curso suelto (p. ej., "Manejo de alimentos" son 15 elementos). La idea es ordenarlo así: **Curso → Versión → Módulos en orden**, ya preparado para Estados Unidos.

**A. Cómo se usa hoy**
1. **¿Quién usa Mera Universidad?** ☐ Solo Capacitación (subir módulos y copiar enlaces para MERA World) ☐ También los colaboradores, para buscar cursos directamente. *Si marcan la segunda:* cuando un curso no tiene todas sus versiones, ☐ mostrar solo las que existen ☐ mostrar "Próximamente en inglés".
2. **Avance, evaluación, encuesta y DC3.** ☐ Siguen solo en MERA World ☐ También los necesitamos en Mera Universidad. ¿La página "Proceso de manipulación de alimentos" (que tiene su propio avance y evaluación) se sigue usando, o ese curso ya se toma en MERA World?
3. **Enlaces en MERA World.** ¿Quién arma los cursos en MERA World y pega los enlaces? ____ ¿Les ayudaría un botón "Copiar enlace" en cada módulo? ☐ Sí ☐ No. *(Los enlaces que ya están puestos seguirán funcionando.)*
4. **Avance marcado a mano.** Hoy el colaborador marca "completado" aunque no haya abierto el módulo. ¿Es un problema para auditorías o DC3? ☐ Sí ☐ No. ¿Pueden preguntar a SuperCompany si su plan acepta cursos **SCORM** (registran el avance solos)?

**B. Crecimiento a otros países**
5. **Países con cursos propios en el próximo año:** ☐ México ☐ LATAM (¿qué países?) ____ ☐ Estados Unidos ☐ Otro: ____
6. **Idiomas:** en Estados Unidos, ☐ inglés ☐ español ☐ ambos. ¿Algún otro idioma a futuro? ☐ Portugués ☐ Otro: ____
7. **Estados Unidos en MERA World.** ¿Sus colaboradores usarán el mismo MERA World? ☐ Sí ☐ No. ¿Quién subirá sus cursos? ☐ Capacitación México ☐ Alguien de allá (que solo vea y administre lo suyo)
8. **Versión por país.** La versión de un país, ¿es el mismo curso traducido o cambia el contenido (normas, procesos, contactos)? ☐ Mismo curso ☐ Cambia ☐ Depende. ¿Llevará el país en el código, como ya pasa con `-MX` (p. ej., `-US`, `-LATAM`)? ☐ Sí ☐ No

**C. Organización de los cursos**
9. **Un curso = un curso de MERA World**, con su mismo código (p. ej., `NRM-EMG-003-EL-26 | Protocolo Sismos`). ☐ De acuerdo ☐ No. ¿Nos confirman qué significa cada parte del código?
10. **Versiones.** Vemos tres: **idioma**, **país** y **año** (p. ej., "Fundamentos de Seguridad" 2025 y 2026). ¿Cuáles son versiones del mismo curso y cuáles cursos distintos? ____ Las ediciones de años anteriores: ☐ se archivan ☐ siguen visibles
11. **Qué más va en la carpeta del curso.** En MERA World un curso también tiene actividades de recapitulación, documentos, artículos y webinars. ☐ Solo los módulos de isEazy ☐ También esos materiales. ¿Las actividades de recapitulación también se hacen en isEazy? ☐ Sí ☐ No
12. **Agrupar.** ¿Les sirve filtrar por **tipo** (Normativo, Competencias, Negocio) y **área** (Seguridad, Higiene, Liderazgo…) tomándolo del código? ☐ Sí ☐ Prefieren otro agrupador: ____ Las categorías actuales (Código de Ética, Protocolo Sismos, Inteligencia Emocional) pasarían a ser cursos.
13. **Nombres y mejoras.** ¿"Curso" y "Módulo", o los de MERA World ("Curso", "Unidad")? ¿Qué les cuesta trabajo hoy al subir a Mera Universidad y armar el curso en MERA World? ____

*Para su información (no requiere respuesta):* para los ~120 módulos que ya existen habrá **selección múltiple** para moverlos a un curso de una vez; Capacitación los agrupa como prefiera.

**Prioridad si no hay tiempo para todo:** 1, 2, 5, 7 y 10.

#### Respuestas de Tania (2026-10-13)
- **Usuarios:** solo Capacitación (sube módulos y copia enlaces). Los colaboradores no navegan Mera Universidad.
- **Avance/evaluación/encuesta/DC3:** en MERA World para **México**. **LATAM y EE. UU. no tienen MERA World** (sin fecha), así que para ellos se usan **páginas de programa** como `/proceso-de-manipulacion-de-alimentos`. Estas páginas pasan a ser parte del alcance.
- **Países:** México, LATAM (Ecuador, Panamá, Colombia) y EE. UU. Cursos los sube Capacitación México.
- **Idiomas:** solo español e inglés. **País:** se busca homologar, pero habrá algunas versiones por país.
- **Años:** cada curso se edita cada año; las ediciones anteriores **siguen visibles**, sin que se vea "encimado".

#### Diseño propuesto con esas respuestas
**Curso → Edición (año) → Versión (idioma + país opcional) → Módulos en orden.**
- La edición vigente se muestra abierta; las anteriores, plegadas en "Ediciones anteriores".
- País opcional: si una versión no tiene país, aplica a todos ("General").
- Cada versión puede publicar su **página de programa** (módulos, avance, evaluación y encuesta por Microsoft Forms) para LATAM/EE. UU.; se crea desde el panel, no en `config/mera.php`.
- URLs existentes intactas; "Copiar enlace" por módulo; migración con selección múltiple.
- **Identificación:** no hace falta login de colaboradores. El registro oficial es la respuesta de Microsoft Forms (el formulario ya pide identificarse). El avance de la página es solo una guía por dispositivo. Opcional: un enlace por versión (o prellenado) para saber de qué edición, idioma y país es cada respuesta.

#### Siguiente paso
1. Recibir las respuestas.
2. Cerrar el diseño con el usuario.
3. Plan de implementación con migración y respaldo (regla 2), conservando las URLs existentes.
4. Pruebas.
5. Build en local (regla 7).
6. Despliegue por fases.

## 11. Insumos para la futura propuesta de intranet propia (NO redactada todavía)

**Estado:** la propuesta de una intranet propia será un **desarrollo independiente**, y se redactará más adelante, cuando el usuario lo pida. Aquí solo queda el inventario de lo que hoy cubre SuperCompany (MERA World), tomado del recorrido del 2026-10-13 con la vista de un colaborador (sin funciones de administración).

| Módulo | Ruta | Qué hace |
|---|---|---|
| Acceso | `auth.supercompany.com` | Login centralizado, recordarme, recuperación, autorregistro, ES/EN |
| Lobby / Company | `/lobby`, `/lobby/welcome` | Banners y avisos (campañas, encuestas externas, álbumes de fotos), **Políticas** (Libro del Colaborador 2026, política de hostigamiento, protocolo de huracán), **videos** institucionales, propósito, 10 valores, "En reflectores" (cumpleaños, aniversarios y bienvenidas) |
| Apps | Botón en el lobby | Portal de Empleados, Sitios, Comunidad, Aplausos, Retro 1:1, Planes de Acción, Entrenamiento |
| Equipo (Team) | `/team`, `/team/g/<id>` | Grupos (Corporativo General, México General), crear grupo; por grupo: **Weekly** (actualización semanal por persona), Muro, Archiveros (archivos) y Planes de Acción |
| Comunidad | `/community` | Feed de publicaciones, felicitaciones automáticas de cumpleaños y aniversarios, botón Publicar |
| Sitios | `/spaces` | Micrositios: MERA Media, Protocolos oficiales de dependencias, Políticas y programas de Talento y Cultura, Políticas administrativas (MX), Implementación NetSuite |
| Aplausos | `/recognitions` | Reconocimientos e insignias por valores, Top 10, nominaciones mensuales |
| Retro 1:1 | `/one-on-one-meetings` | Sesiones 1:1 de retroalimentación (no se abrió para no crear registros) |
| Planes de Acción | `/action-plans` | Planes SMART con progreso y estatus, plantillas (p. ej., planes de clima para PTY) |
| Entrenamiento | `/training…` | LMS: tablero, auto-aprendizaje, expediciones, insignias, DC3 (ver §10) |
| Agenda | Icono de pendientes | **Hacer** (cumpleaños, aniversarios, encuestas y cursos pendientes), **Cumplir** (metas OKR/KPI con peso: vigentes, próximas, históricas), **Leer** |
| Perfil | Avatar | Puesto, área, sede y número de empleado; **Mi expediente**; **Mis docs** (contratos y firma digital de documentos); Asistencia; pestañas Pendientes, Equipo y Perfil |
| Asistente IA | "Super" / "Super Flash" | Chat de IA integrado en todas las páginas |

Puntos a considerar cuando se redacte la propuesta: qué módulos se reemplazan y cuáles se integran, el login único (hoy está en SuperCompany), los datos de RR. HH. (expediente, firma digital, asistencia, DC3), la migración del historial de capacitación y la decisión D1 (acceso a los cursos).

## 12. Auditoría QA / UX / seguridad en navegador (2026-10-13, entorno LOCAL)

Recorrido completo en `http://127.0.0.1:8000` con sesión de Super Admin: login, catálogo, dashboard, cursos (lista y edición), carpetas (lista, detalle y edición), categorías, usuarios, roles, permisos, historial y perfil. Revisado en escritorio (~1200 px) y móvil (375 px). **Sin acciones que modificaran datos** (no se guardó, movió, publicó ni borró nada).

**Cómo trabajar esta sección:**
- Son hallazgos **nuevos**; los que ya estaban en §4 o §6 solo se referencian.
- Respetar las reglas de §0 (respaldo, pruebas en sqlite, build en local, commit solo con autorización) y **no tocar lo delegado (D1-D9)**.
- Prioridad: 🔴 crítico · 🟠 alto · 🟡 medio · 🟢 bajo.
- Al resolver uno, marcar `[x]` y pasarlo a la tabla de §4 con su estado.

### Ya conocidos (no duplicar)
- Cursos y panel en el mismo origen → **S5 / D8** (delegado, no actuar).
- Bootstrap + Tailwind y dos librerías de iconos → **P6 / U9**. Portadas sin miniaturas (450-840 KB cada una) → **P8**.
- `/_ignition/health-check` responde 200 **en local**: es lo esperado con `APP_DEBUG=true` en local. En producción ya está resuelto (S22, `composer --no-dev`). Solo verificar que allá dé 404.

### Funcionalidad
- [x] ✅ **F1. Archivos de cursos faltantes no se detectan.**
  - En local, 23 de 109 módulos dan 404 en "Ver curso" (`storage/cursos/<carpeta>/index.html`), y sus portadas también: la BD viene de producción y esos archivos no se copiaron.
  - El panel no lo avisa: la lista muestra la portada de respaldo, y "Ver curso" abre el 404 genérico de Laravel en una pestaña nueva.
  - Propuesta: comando `mera:check-courses` (solo lectura: revisa que existan el `index.html` y la portada de cada curso) y un aviso "Archivos no encontrados" en la lista y en la edición del curso. Correrlo también en producción (relacionado con D4, portadas rotas).
- [x] ✅ **F2. Papelera sin interfaz.** Al borrar un curso, la confirmación solo dice "¿Eliminar el curso «…»?": no avisa que va a la papelera ni que se purga a los 30 días, y no hay forma de restaurarlo desde el panel (solo por servidor). Propuesta: texto en la confirmación + vista "Papelera" (solo super admin) con "Restaurar" vía `CourseManager`.
- [x] ✅ **F3. Posición vs. número del título.** En "Example", "Service Standards: MODULE 2" quedó como Módulo 1 y "MODULE 1" como Módulo 2. Al usar "Mover a…", sugerir la posición a partir del número del título y avisar si no coincide.
- [x] ✅ **F4. Edición de curso sin aviso de cambios sin guardar.** Si se sale con cambios (o con un ZIP elegido), se pierden sin aviso. Agregar `beforeunload` cuando el formulario esté modificado (y desactivarlo al enviar).

### Seguridad
- [x] ✅ **S23. Sin cabeceras de seguridad.**
  - Ninguna respuesta trae `X-Frame-Options`/`frame-ancestors` (el login se puede embeber en un iframe: clickjacking), `Referrer-Policy`, `Permissions-Policy` ni `Content-Security-Policy`. `nosniff` solo está en `storage/`.
  - Propuesta: middleware global `SecurityHeaders` con `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, una `Permissions-Policy` mínima y `Strict-Transport-Security` solo en producción.
  - CSP: empezar con `Content-Security-Policy-Report-Only`, porque la plantilla usa scripts inline.
  - **No aplicarlo a `storage/cursos`**: los paquetes de isEazy podrían romperse.
  - Cubrirlo con pruebas de Feature.
- [x] ✅ **S24. `X-Powered-By: PHP/x.y.z` expuesto** (local 8.2.12; producción 8.1.34, ver §7). Quitarlo con `Header unset X-Powered-By` en `public/.htaccess` o con `expose_php = Off` en `.user.ini`. Así no se anuncia una versión de PHP fuera de soporte.
- [x] ✅ **S25. "Eliminar cuenta" en Mi perfil.** Cualquier admin o super admin puede borrar su propia cuenta desde `/profile` (`DELETE /profile`, de Breeze). Verificar que `ProfileController::destroy` respete `UserPolicy` (último super admin). Como las cuentas las crea un admin (S7), lo coherente es **quitar el auto-borrado** y que lo haga otro admin desde Usuarios. Agregar una prueba.
- [ ] 🟡 (jfcruz borrada en local y en producción el 2026-10-14; falta revisar el admin con dominio externo) **S26. Revisión de accesos.** Hay una cuenta **admin** con un correo de un dominio distinto de `meracorporation.com`, y "Super Admin" (jfcruz@outlook.com) tiene los roles `admin` y `super admin` a la vez. Sugerencia: confirmar con el usuario si esos accesos siguen vigentes, mostrar en Usuarios la "Última conexión" y permitir **desactivar** una cuenta sin borrarla. No modificar cuentas sin instrucción.
- [x] ✅ **S27. API pública.** `Access-Control-Allow-Origin: *` está bien para lectura pública. Verificar que `per_page` tenga tope (p. ej. 100) y que `/api/courses` tenga `throttle:api` (no se pudo comprobar desde el navegador).
- [x] ✅ Bien: logout por POST con CSRF; las rutas del panel redirigen a login sin sesión; `/register` da 404; una carpeta sin publicar da 404 sin sesión; la búsqueda escapa HTML; `autocomplete` correcto en el login.

### Rendimiento
- [x] ✅ **P9. `images/logo_university.svg` pesa 150 KB** y se usa en el header y como portada de respaldo en cada tarjeta y fila. Probablemente trae un bitmap incrustado: exportarlo vectorial / pasarlo por SVGO (objetivo < 15 KB) o usar un PNG/WebP pequeño.
- [x] ✅ **P10. JS y CSS de la plantilla en todas las páginas del panel:** `jquery-ui.min.js` (248 KB), `jquery.slimscroll`, `waves`, `feather.css` + `font-awesome`, `style.css` (315 KB) y `widget.css`. Inventariar cuáles se usan (jquery-ui parece no usarse) y quitar los demás. Requiere QA visual (va con P6/P7).
- [x] ✅ **P11. TTFB de ~0.9-1.3 s por página en local** (dashboard, cursos, usuarios…). Medir con `DB::listen` o Debugbar (solo local) cuántas consultas hace y cuánto tarda cada página, y revisar que OPcache esté activo en XAMPP. Si en producción también tarda, perfilar `/courses` (127 KB de HTML con 44 formularios).
- [x] ✅ **P12. Preloader de la plantilla** (`.loader-bg` de pcoded): cada navegación deja la página atenuada ~1 s aunque ya cargó. Quitarlo o bajarlo a < 200 ms.

### UX/UI
- [x] ✅ **U11. Usuarios: la columna "Acciones" queda oculta** detrás del scroll horizontal a ~1200 px (la tabla mide 977 px dentro de un contenedor de 851 px). Los botones de editar y eliminar no se ven sin desplazar. Usar `.mera-table-stack` como en Cursos o reducir columnas.
- [x] ✅ **U12. Roles y permisos confusos.**
  - El permiso se llama "Super Admin" y lo tiene el rol `admin`; el rol `super admin` aparece "Sin permisos".
  - El acceso real se decide por **rol** (`role:super admin|admin`), así que editar permisos no cambia nada.
  - El rol `user` se puede asignar al crear un usuario, pero no da acceso al panel.
  - Propuesta: ocultar "Permisos" (o explicar que es informativo), quitar `user` del selector o explicarlo, y mostrar nombres legibles ("Super administrador", "Administrador").
- [x] ✅ **U13. Historial poco legible.**
  - Muestra "Course #127" en lugar del nombre, y campos técnicos en inglés ("position: 2 → 1").
  - El filtro solo tiene Todos/Carpetas/Cursos: faltan Categorías y Usuarios, que también se registran, y no permite filtrar por usuario ni por fecha.
  - En móvil la tabla mide 751 px y obliga a desplazar: pasarla a `.mera-table-stack`.
  - Traducir los atributos con `lang/es/validation.php → attributes` o con un mapa propio.
- [x] ✅ **U14. Dashboard desalineado con las carpetas.**
  - Destaca "104 cursos sin categoría" y el acceso rápido "Nueva categoría", pero la organización ahora es por **carpetas** (105 módulos sin carpeta). Además, "Revisar cursos" lleva a `/courses` sin filtro.
  - Propuesta: tarjeta "Carpetas", aviso "N módulos sin carpeta" → `/courses?carpeta=sin` y acceso rápido "Nueva carpeta".
  - Mantener las categorías mientras siga su stand by (§10).
- [x] ✅ **U15. Terminología mezclada.** El menú y la tabla dicen "Cursos" ("109 cursos"), pero cada registro es un **módulo**, y el formulario de carpeta pide "Nombre del curso". Unificar: carpeta = curso, registro = módulo ("Módulos" en el menú, "Nuevo módulo"). Confirmar con el usuario antes de renombrar, porque cambia lo que Capacitación ya conoce.
- [x] ✅ **U16. Iconos que se ven como cuadros (□) al cargar:** pasa en la lista de cursos y en móvil (botón de menú, "Copiar link", el globo de "Página pública", etc.). Precargar `fa-solid-900.woff2` y `feather.woff` (`<link rel="preload" as="font" crossorigin>`) y confirmar `aria-hidden` en todos los iconos decorativos.
- [x] ✅ **U17. Edición de curso con portada rota:** se ve el texto alternativo dentro de un recuadro ("Portada actual de…") en vez de la portada de respaldo que sí usa la lista. Usar `partials/cover` también en la edición.
- [x] ✅ **U18. Reordenar módulos de uno en uno:** cada ↑/↓ recarga la página completa.
  - Para carpetas de 5 o más módulos, agregar arrastrar y soltar (SortableJS, guardado por `fetch` a `CourseGroupOrganizer`) y conservar ↑/↓ por accesibilidad.
  - El icono de "Quitar de la carpeta" (flecha de salida) se confunde con "Cerrar sesión": usar otro icono o texto.
- [x] ✅ **U19. Móvil (375 px):**
  - En Cursos, los filtros ocupan toda la primera pantalla antes del primer resultado: hacerlos plegables ("Filtros (1)").
  - Hay 64 botones de icono de menos de 36 px en Cursos y unos 11 en Carpetas, Usuarios y Categorías: subirlos a 40-44 px.
  - En el detalle de carpeta, "Copiar link" y "Vista previa" se apilan a lo ancho en cada idioma: ponerlos en una sola fila de dos botones.
- [x] ✅ **U20. Perfil:**
  - Las etiquetas no tienen `for`, así que los lectores de pantalla no las asocian a los campos.
  - El título de la página es `h5`; debe ser `h1`, como en el resto.
  - Al entrar a Mi perfil, el menú lateral pierde el elemento activo y se pliega "Administración".
- [x] ✅ **U21. Usuarios con el mismo nombre.** Dos cuentas se llaman "Super Admin" (jfcruz@outlook.com y jesus.castro@…), y el saludo dice "Bienvenido, Super Admin". Pedir nombres reales y marcar la cuenta propia con "(tú)" en la lista. **No cambiar datos sin instrucción.**
- [x] ✅ **U22. Mayúsculas en botones:** "Guardar Cambios", "Subir Módulo Nuevo Aquí", "Copiar Link", "Vista Previa", "Publicar Página", "Eliminar Cuenta", "Nuevo Usuario". En español solo va mayúscula inicial ("Guardar cambios"). Unificar también "link" vs. "enlace".
- [x] ✅ **U23. Cursos (lista):**
  - No se puede ordenar por nombre ni por fecha, y no hay columna "Actualizado".
  - La columna Categoría se oculta en anchos ≤ 1200 px.
  - "Español · Módulo 1" se parte en dos líneas: usar `white-space: nowrap` o abreviar ("ES · 1").
- [x] ✅ **U24. Crear usuario:** no hay botón para mostrar la contraseña ni para generar una segura. A futuro conviene "enviar enlace para crear contraseña" (correo de restablecimiento) en lugar de que el admin la defina y la entregue por otro canal (relacionado con D3).
- [x] ✅ **U25. Favicon duplicado:** `<link rel="icon">` aparece 3 veces en el `<head>` del panel. Revisar `partials/favicon` y los layouts.
- [x] ✅ **U26. Login:**
  - No hay botón para mostrar u ocultar la contraseña.
  - El enlace "Ver catálogo" mide 14 px de alto y tiene bajo contraste.
  - El `alt` del logo y el `<title>` salen de `config('app.name')`. En local, `.env` tiene `APP_NAME=Laravel`, por eso se ve "· Laravel" y `alt="Laravel"`. Poner `APP_NAME="Mera Universidad"` en el `.env` local y en `.env.example`, y dejar fijo el `alt` del logo ("MERA University").
- [x] ✅ **U27. Catálogo público `/cursos`** (prioridad baja: según Tania solo lo usa Capacitación):
  - Mientras cargan, las tarjetas son bloques grises sin `aspect-ratio`.
  - Las portadas tienen `alt` vacío. Es correcto si son decorativas, pero entonces el título debe ser el enlace.
  - En móvil la paginación muestra todos los números.

### Cómo quedó (2026-10-13, local, sin commit)
- **Resuelto:** F1 (`mera:check-courses` de solo lectura + aviso en lista y edición; el ojo se desactiva), F3 ("Mover a…" ordena por el número del título y la carpeta avisa si no coincide), F4 (`data-warn-unsaved`), S23/S24 (middleware `SecurityHeaders`; CSP en modo *Report-Only*), S25 (sin auto-borrado de cuenta), S27, P9 (logo 150 KB → 15 KB; original en `storage/backups/assets_20261013/`), P12 (sin preloader), U11, U12 (nombres legibles de rol; solo se asignan roles del panel; aviso de que los permisos son informativos), U13, U14, U16 (precarga de fuentes de iconos), U17, U18 (arrastrar y soltar nativo, sin dependencia nueva; ↑/↓ se conservan; icono de quitar = `fa-unlink`), U19 (filtros plegables propios: **no usar `.collapse`**, Tailwind la define como `visibility: collapse`), U20, U22 (`.btn{text-transform:none}`; se unificó "link"), U23 (orden por nombre/actualizado, columna "Actualizado"), U25, U26 (mostrar contraseña, enlace al catálogo, `APP_NAME` local corregido con respaldo del `.env`).
- **Decisiones del usuario aplicadas (2026-10-13):**
  - **U15:** en el panel, cada registro es un **módulo** ("Módulos", "Nuevo módulo", "Subir módulo"); la carpeta es el curso. El catálogo público `/cursos` no cambió.
  - **F2:** pantalla **Papelera** (solo super admin, `/papelera`): borrado suave (`deleted_at`) + `trash_items` con el nombre de cada archivo en la papelera; "Restaurar" devuelve los archivos a su ruta original (el link vuelve a funcionar) y el módulo al final de su carpeta. `mera:purge-course-trash` también borra de la BD lo que lleva más de 30 días. Lo borrado antes de este cambio no aparece (no tiene registro).
  - **U24:** el admin ya no define contraseñas. Al crear un usuario se le envía un correo "Crea tu contraseña" (broker `invitations`, 72 h); en la lista hay "Enviar link" para reenviarlo. **Requiere correo SMTP en producción** (§10, despliegue).
  - **S26/U21:** cuenta `jfcruz@outlook.com` borrada en local y **en producción (2026-10-14)**, con dump previo en ambos. La lista ya marca la cuenta propia con "(tú)". El admin con dominio externo sigue pendiente de revisar.
  - `profile/partials/delete-user-form.blade.php` borrado (respaldo en `storage/backups/code_removed_20261013/`).
  - **Programa de alimentos → carpeta:** `config('mera.programs…course_group')` = `manipulacion-de-alimentos`. Si la carpeta tiene módulos en un idioma, la página los toma de ahí (orden y links de evaluación/encuesta de "Editar carpeta"); si no, usa la lista de la config como respaldo. **La URL y la clave del avance no cambian**; `/manipulacion-de-alimentos` redirige al programa. `/food-handling-process` se publica solo cuando la carpeta tenga módulos en Inglés. Comando idempotente `mera:program-folder` (en local creó la carpeta vacía: la BD local no tiene los 5 módulos LATAM).
- **P11 (medido, 2026-10-13):** en el servidor cada página tarda 6-76 ms con 0-9 consultas (sin N+1). En producción, un estático tarda ~100 ms tras la conexión TLS y `/login` ~200 ms: el PHP aporta ~110 ms y el resto es red/TLS. **No hay problema de rendimiento en producción.** La lentitud local viene del entorno: OPcache desactivado en XAMPP (`;zend_extension=opcache` en `C:
mpp\php\php.ini`), `php artisan serve` atiende una petición a la vez y las portadas que faltan en local pasan por Laravel (404). Las únicas consultas repetidas (3 en Usuarios por la regla del último super admin, ~11 ms en la página del programa) no justifican caché.
- **P10 (2026-10-13):** el panel ya no carga jQuery UI (248 KB), Waves (css+js), `widget.css` ni `public/js/script.js` de la plantilla (~297 KB menos). De `script.js` solo se usaban el botón de pantalla completa y el ícono del menú: ahora están en `resources/js/modules/panel-template.js` (`data-fullscreen`). Se conservan jQuery, Popper (menú de usuario), Bootstrap, slimScroll (lo usa `pcoded` en el menú lateral), `pcoded` y `vertical-layout`. Verificado con capturas antes/después de 13 pantallas en 1366 y 375 px: 25 de 27 idénticas píxel por píxel; Módulos tiene la misma diferencia que dos corridas sin cambios (portadas que cargan) y el menú móvil solo cambia el suavizado del texto. Menú de usuario, menú móvil y pantalla completa probados. **Quitar más (style.css de 315 KB, unificar Bootstrap/Tailwind) es P6/P7.**
- **U27 (2026-10-13):** cada tarjeta del catálogo tiene un solo enlace (el título, que cubre la tarjeta y anuncia "se abre en una pestaña nueva"); la portada es decorativa y reserva su espacio 16:9 con fondo de marca mientras carga; foco visible y hover suave (respeta `prefers-reduced-motion`). La paginación en móvil ya mostraba solo "Anterior / Siguiente" (vista Tailwind de Laravel): no se cambió.
- **Plantilla de correo MERA (2026-10-14):** tema `resources/views/vendor/mail/html/themes/mera.css` (`config('mail.markdown.theme') = 'mera'`): fondo `#F3F8F4`, tarjeta con borde superior `#93C01F` y radio de 18 px, botón pill `#024D25`, Montserrat con respaldo Arial y pie con *Vision · Values · Results*. Logo a color en PNG (`public/images/logo-mera-university-email.png`, generado del original; los correos no muestran SVG) con URL absoluta desde `APP_URL`. Solo se publicaron `html/header`, `html/message` y el tema; lo demás lo toma Laravel. Textos del correo de "olvidé mi contraseña" traducidos en `lang/es.json`. Prueba: `test_emails_use_the_mera_template_in_spanish`.
- **Nota local:** si existe `public/hot`, las páginas usan el servidor de Vite (`npm run dev`) en lugar de `public/build`. Está en `.gitignore`.
- **Dato a revisar en "Example" (no se modificó):** en Inglés, MODULE 4 está como Módulo 1 y MODULE 3 como Módulo 2; en Español, MODULE 2 está como Módulo 1. Ahora la carpeta lo marca con "El título dice Módulo N".

### Lo que está bien (no tocar)
Diseño consistente con la marca, confirmaciones con el nombre del elemento, `aria-label` descriptivos en los botones de icono, skip-link, barra "Mover a…" accesible (`aria-live`), pestañas de idioma con `aria-current`, carpetas sin publicar protegidas y ninguna página se desborda a lo ancho en 375 px (solo el contenido dentro de algunas tablas).

### Orden sugerido de trabajo
1. Seguridad sin impacto visual: S25, S23 y S24 (con pruebas).
2. Lo que hoy estorba el uso: U11, F1, U13 y U12.
3. Coherencia con las carpetas: U14, U15 (confirmar nombres con el usuario) y U18.
4. Rendimiento y pulido: P9, P12, U16, U17, U19, U20 y el resto de 🟢.
5. Al final, lo que requiere QA visual amplio (P10, junto con P6/P7).

## 13. Auditoría UI/UX + QA en producción (2026-10-14)

Revisión de `cursos.meracorporation.com` como Super administrador, en 375, 768 y 1280 px (documento del usuario: `auditoria-ui-ux-qa-mera-universidad.md`, 18 hallazgos), más un reporte del usuario sobre la barra superior y el menú lateral. **Subido a `dev` el 2026-10-14 (commit autorizado por el usuario); pendiente de desplegar.** Verificado con capturas de antes y después en 1523, 1280, 768 y 375 px, con un usuario QA temporal de correo largo (respaldo de la BD antes; usuario borrado al terminar). 116 pruebas en verde.

| # | Hallazgo | Estado |
|---|---|---|
| 1 | El correo desbordaba el modal "Enviar link" | ✅ Título corto y el dato largo en el cuerpo (`data-confirm-strong`, con `overflow-wrap: anywhere`). Medido: 0 px de desborde en los 4 anchos (antes 405-528 px) |
| 2 | "Cancelar" ilegible en los modales (1.35:1) | ✅ Contorno verde sobre blanco en **todos** los `swal()`, foco visible lima y botones de 44 px |
| 3 | Ícono de advertencia y botón de peligro en acciones no destructivas | ✅ `data-confirm-tone`: `info` (enviar link, publicar/apagar, restaurar, quitar de carpeta) o `danger` (eliminar: ícono de advertencia y botón rojo). Los botones se deshabilitan al confirmar (sin envíos dobles); el resultado lo muestra el mensaje flash |
| 4 | Jerarquía de botones invertida | ✅ Primario verde oscuro (`.mera-btn-save`), secundario en contorno (`.mera-btn-cancel`, `.mera-btn-outline`), acento lima solo en CTA y buscar (`.mera-btn-primary`), eliminar en rojo `#C62828` (`.mera-delete-btn`). "Quitar de la carpeta" usa `.mera-neutral-btn` (no es destructivo) |
| 5 | Tabla de Módulos: columna Nombre angosta y selects truncados | ✅ Nombre con `min-width: 260px`, Acciones y fechas sin salto, filtros en grid de una fila (`.mera-filter-grid`), "Todas" en vez de "Todas las categorías". La miniatura de portada solo se muestra desde 1400 px (en 1280 px la columna Acciones se salía) |
| 6 | Texto gris con contraste bajo | ✅ Token `--mera-text-muted: #5F6368` (≈ 6.3:1) en `.text-muted`, rol del encabezado, tarjetas del dashboard, conteos de categorías y etiquetas de tarjetas |
| 7 | Tablet (768 px) con el diseño de móvil | ✅ Los cortes `max-width: 768px` pasaron a `767.98px` (como Bootstrap `md`): botones de ancho automático en tablet. Entre 768 y 991 px las tablas de usuarios, roles, etc. y los módulos se muestran como **tarjetas en 2 columnas** |
| 8 | Tres estilos de encabezado de página | ✅ Visualmente unificados por CSS: los encabezados de formularios y "Mi perfil" usan el mismo degradado, título de 28 px y barra lima que los listados (el ícono circular se reemplazó por la barra). No se convirtieron a un componente Blade único |
| 9 | Encabezados de tabla "rayados" | ✅ El degradado se aplica a la fila (`thead tr`), no a cada celda |
| 10 | Tarjetas del dashboard de alturas distintas | ✅ Misma altura y línea secundaria en las cuatro ("0 publicadas", "N sin carpeta", "con acceso al panel", "en pausa") |
| 11 | Elemento activo del menú en celeste | ✅ Verde suave `#E3F0D0` con barra lima a la izquierda; íconos de 16 px |
| 12 | Subtítulo blanco sobre el final celeste del degradado | ✅ El degradado termina en `#55882B` y el subtítulo es más opaco |
| 13 | Fuente de 10.4 px y objetivos táctiles pequeños | ✅ Notas a 12 px; botones de acción de 40 px (44 px en táctil); "Organizar módulos" y "Editar" de 44 px |
| 14 | Inputs truncados en móvil | ✅ Placeholder "Nombre o correo" y menos relleno lateral en móvil |
| 15 | Logo sin nombre accesible; autocompletar y mostrar contraseña en Mi perfil | ✅ `alt="MERA University – Ir al inicio"`, casilla "Mostrar contraseñas" (`data-show-passwords`) y placeholders útiles. 🟡 SweetAlert v1 no atrapa el foco dentro del modal (Escape sí cierra) → ver #18 |
| 16 | `<code>` rosa en Roles | ✅ Badge neutro `.mera-code-badge` |
| 17 | Copy y microinteracciones | ✅ Un solo saludo (el del encabezado; el dashboard dice "Resumen…"), Papelera con texto no técnico y estado vacío con ícono, "¿Cómo funciona el acceso?" plegable (`<details>`), se quitó el botón de pantalla completa. ⏳ **Dato a revisar:** el rol `admin` tiene asignado un permiso llamado "Super Admin" (es dato de la BD de producción, no de código; los permisos son informativos) |
| 18 | Bootstrap + Tailwind + SweetAlert v1 | ⏳ Pendiente (va con P6): migrar a SweetAlert2 o a `<dialog>` propio y unificar componentes |
| — | **Reporte del usuario: barra superior y menú lateral mal posicionados** | ✅ El bloque del logo tenía sombra propia y medía 78 px bajo una barra de 80 px (línea desfasada solo bajo el logo): se quitó. El menú era un acordeón "Administración" que ocultaba todo al pulsarlo: ahora es una lista plana con secciones (Contenido, Administración, Cuenta), elementos de 44 px y el texto en el flujo normal (la plantilla lo posicionaba en absoluto). El botón ☰ sigue ocultando el menú en escritorio y abriéndolo en móvil |
| — | **Reporte del usuario: nombre y rol del usuario mal posicionados (barra superior, escritorio)** | ✅ La plantilla daba al `<li>` 75 px con `line-height: 75px` y el desplegable quedaba alineado a la base del texto (avatar de y=3 a y=43 en una barra de 80 px). Ahora avatar, nombre, rol y flecha se centran en los 80 px (y=20 a 60), con flecha de 16 px y fondo suave al pasar el mouse |
| — | **Pregunta del usuario: ¿por qué todos los módulos dicen "Archivos no encontrados"?** | ℹ️ No se borró nada (109 activos, Papelera vacía, mismos 250 elementos en `storage/app/public/cursos`). Son los **26 módulos más recientes**, subidos a producción después de copiar la BD local: no tienen archivos en el equipo local y, como la lista ordena por "Más recientes", llenan las primeras páginas. En producción no aplica |
| — | **Decisión del usuario: retirar las categorías (opción 2)** | ✅ Interruptor `config('mera.categories_enabled')` (`MERA_CATEGORIES_ENABLED`, **apagado por defecto**): sin menú, sin tarjeta en el dashboard, sin campo en el formulario, sin columna ni filtro en Módulos, sin filtro en el Historial; `/categories` responde 404. Al editar un módulo **no se toca** su categoría (antes se borraba si el campo no venía). Catálogo `/cursos`: sin filtro ni etiqueta de categoría y **12 módulos por página** (antes 24; `MERA_CATALOG_PER_PAGE`). **API sin cambios** (el WordPress muestra el nombre de la categoría). Además, por decisión del usuario, `php artisan mera:detach-categories` quitó la categoría a los 5 módulos que la tenían (local: dump + respaldo JSON `storage/backups/categorias_de_modulos_*.json`); las 3 categorías no se borraron |
| — | **Reporte del usuario en producción (`/cursos`): botón "Acceso administradores" gigante y scroll horizontal por la paginación** | ✅ (local) Causa común: un ancho efectivo de ~640 px (escalado de Windows). (1) La regla de móvil (≤767 px) que pone `.mera-btn-primary` a ancho completo era **global**: ahora solo aplica a `.mera-page-header .mera-btn-primary` (el "Nuevo …" de los listados). El botón del catálogo es compacto (`.mera-header-cta`, sin saltos de línea; en celulares angostos dice "Acceso" con candado y `aria-label` completo). (2) La vista de paginación de Laravel mostraba todos los números desde 640 px (`sm`) y desbordaba: se publicó solo `resources/views/vendor/pagination/tailwind.blade.php`, con el corte en **768 px (`md`)**, fila que puede partirse, "Página X de Y" en celular y texto más chico bajo 360 px; además `onEachSide(1)` en catálogo, Módulos, Historial, Usuarios, Papelera y Permisos. Verificado: sin desborde en 13 pantallas del panel (1280/960/768/640/375/320 px) ni en `/cursos` (1440 a 320 px); el "Nuevo …" sigue a ancho completo en celular |
| — | **Catálogo: hueco en la última fila** | ✅ (local) `auto-fill` daba 5 columnas en pantallas anchas y 12 módulos quedaban 5+5+2. Ahora columnas fijas: 1 / 2 (≥576) / 3 (≥992) / 4 (≥1400); 12 se divide exacto. Verificado de 1541 a 375 px |
| — | **Loader de página (pedido del usuario: los estilos tardan y se ve feo)** | ✅ (local) `partials/page-loader` como primer hijo de `<body>` en los layouts `app`, `public`, `guest` y `program`: fondo de marca, logo y spinner; estilos y script en línea. Se quita cuando el DOM y las fuentes (`document.fonts.ready`) están listos, con tope de 2.5 s; respaldo sin JS (animación CSS a los 4 s y `<noscript>`). Medido: se quita 66-153 ms después de la respuesta (≈0.9 s la primera vez que se descarga un peso nuevo de Montserrat); después no bloquea clics (`pointer-events: none`). Nota: si Google Fonts no responde, el navegador bloquea la página por el `<link>` en `<head>` (no es del loader) |
| — | **Textos de botones pegados a la izquierda ("Buscar" en Usuarios y en `/cursos`)** | ✅ (local) `.btn.mera-btn-primary/save/cancel/outline` con `justify-content: center`. El "Nuevo …" del encabezado no se parte (en tablet va debajo del título: `col-lg-8/4`) y "Copiar link"/"Vista previa" caben en celular. Verificado: 75 botones centrados en 13 pantallas a 1280/768/375 px, sin desbordes |

**Archivos:** `resources/css/app.css` (bloque "AUDITORÍA UI/UX 2026-10-14" al final; respaldo previo en `storage/backups/app.css.antes_auditoria2_*`), `resources/js/modules/confirm.js`, `layouts/navigation.blade.php` (respaldo en `storage/backups/navigation.blade.php.antes_auditoria2_*`), `partials/header`, `partials/delete-button`, `partials/roles-notice`, dashboard, módulos, usuarios, roles, Papelera, carpetas y Mi perfil.

**Nota local:** `public/hot` (de un `npm run dev` que ya no corría) se movió a `storage/backups/public_hot_*`; con él, las páginas buscaban los estilos en un Vite apagado.
