# CLAUDE.md — Mera Universidad

Plataforma interna de cursos (Mera Corporation). Un panel administrativo permite subir cursos empaquetados en ZIP (HTML/JS autocontenido: Storyline, Rise, iSpring…), clasificarlos en categorías jerárquicas y publicarlos en un catálogo (`/cursos`) y en una API pública (`/api/courses`).

---

## 0. REGLAS OBLIGATORIAS (leer antes de cualquier acción)

1. **Nunca hacer `git commit` ni `git push` sin la autorización explícita del usuario.** Se hacen los cambios, se muestra el diff y se espera un "sí" explícito. Una autorización vale para un solo commit o push.
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
config/mera.php          límites, extensiones permitidas, rutas, URL base de cursos, caché API
resources/js/modules/    flash.js (mensajes por JSON), confirm.js (data-confirm), upload.js (progreso)
resources/views/partials page-header, flash, form-errors, delete-button, user-menu, cover (respaldo de portada), brand-fonts
docs/wordpress/          Snippet del catálogo para meracorporation.com/cursos-university (pegar en Divi)
```

### Almacenamiento de cursos
- `storage/app/public/cursos/<carpeta>/index.html`: contenido. Los cursos nuevos usan una carpeta **inmutable** `curso-XXXXXXXXXX`; renombrar un curso **no mueve archivos**. Los cursos antiguos conservan carpetas con nombre de slug.
- `storage/app/public/cursos/<carpeta>-cover-xxxxxx.(jpg|png|webp)`: portada, con la extensión detectada por contenido. Algunos cursos antiguos guardan `cover.png` dentro de su carpeta; al reemplazar el ZIP, la portada se rescata.
- `storage/app/tmp/course-uploads/`: extracción temporal (privada).
- `storage/app/trash/courses/`: papelera. Se purga a los 30 días.
- `storage/app/public/.htaccess`: bloquea `.php`/`.phtml`/`.phar`… (respuesta 403) y archivos ocultos; envía `nosniff`. Lo regenera `mera:harden-storage`.

### Convenciones
- Código y comentarios en español; clases y métodos en inglés.
- Estilos propios con prefijo `mera-` en `resources/css/app.css`. No editar `public/css/style.css` (plantilla).
- **Bootstrap 5** (no 4): usar `ms-/me-`, `text-md-end`, `visually-hidden`, `form-select`. Las clases BS4 viejas (`mr-2`, `text-right`) solo funcionan porque Tailwind las define.
- Mensajes al usuario: `->with('success'|'error', …)`; los pinta `partials/flash` + `flash.js`. **Nunca** interpolar datos en `<script>` con `{{ }}`.
- Formularios destructivos: `@include('partials.delete-button', …)` o `<form data-confirm="…">`.
- Búsquedas de texto: `App\Support\Like::contains()`.
- Lógica de archivos de cursos: **solo** vía `CourseStorage` / `CourseManager`.
- Al tocar un controlador: FormRequest + Policy, sin `$request->all()`.

## 4. Estado de la auditoría (2026-10-08)

✅ = resuelto y cubierto por pruebas · 🟡 = mitigado o parcial · ⏳ = pendiente (requiere decisión o autorización)

### Seguridad
| # | Hallazgo | Estado |
|---|---|---|
| S1 | RCE por `.php` dentro del ZIP | ✅ Lista blanca de extensiones + `.htaccess` (verificado en Apache: 403, no se ejecuta) |
| S2 | Portada con extensión del cliente y SVG | ✅ `mimes:jpg,jpeg,png,webp` + extensión detectada por contenido |
| S3 | Slug vacío borraba **todos** los cursos | ✅ Slug nunca vacío + `CourseStorage::resolve()` + carpetas inmutables |
| S4 | Contraseñas de super admin en `UserSeeder`. **El repo de GitHub es PÚBLICO**, así que están expuestas | 🟡 Eliminadas del código. ⏳ **URGENTE: rotarlas en producción** con `php artisan mera:rotate-password --super-admins` (ver §7) |
| S5 | JS de los cursos en el mismo origen que el panel | 🟡 Hoy panel y cursos comparten `cursos.meracorporation.com`. `MERA_COURSES_URL` permite moverlos a otro subdominio (p. ej. `contenido.`) sin tocar código. ⏳ Decisión pendiente |
| S6 | Escalada de privilegios (admin → super admin) | ✅ UserPolicy, roles del sistema protegidos, último super admin protegido |
| S7 | Registro público abierto | ✅ Rutas eliminadas (404) |
| S8 | Contenido de cursos sin control de acceso | ⏳ Decisión de negocio: hoy es público por diseño (catálogo + API) |
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
| S20 | 🔴 **47 scripts PHP de demo de la plantilla ejecutables en producción** (`public/pages/**`): `file-upload/file-upload.php` permite **subir cualquier archivo sin login y ejecutarlo**; también hay `filer/php/ajax_upload_file.php`, `ajax_remove_file.php` y `j-pro/php/action.php` (correo). El servidor comparte cuenta con ~15 sitios | ✅ `public/pages/.htaccess` bloquea PHP (verificado en local: 403; CSS/JS siguen en 200). ⏳ En el servidor, `file-upload.php` aparece **modificado localmente**: revisar `git diff` y las carpetas `uploads/` por si hubo intrusión |
| S21 | `public/info.php` (phpinfo) público en producción; no está en git | ⏳ Borrarlo en el servidor (respaldo antes) |
| S22 | Producción con `APP_ENV=local` y `APP_DEBUG=true`: los errores muestran código, rutas y variables | ⏳ Cambiar a `production`/`false` en el despliegue |

### Calidad (QA)
| # | Hallazgo | Estado |
|---|---|---|
| Q1 | `/cursos` daba 500 | ✅ Catálogo público nuevo |
| Q2 | Pérdida del curso al fallar un reemplazo de ZIP | ✅ Todo o nada + papelera |
| Q3 | No se podía editar un usuario sin cambiar su contraseña | ✅ |
| Q4 | Categoría duplicada → 500; no se permitían homónimas bajo padres distintos | ✅ Únicas entre hermanos; slug único |
| Q5 | Borrado en cascada silencioso | ✅ La confirmación muestra el impacto; lógica explícita (no depende del motor de BD) |
| Q6 | `CourseController_base.php` (clase duplicada) | ⏳ Respaldado; falta autorización para borrarlo (ver §6) |
| Q7 | `moveDirectory` silencioso al renombrar | ✅ Ya no se mueven carpetas al renombrar |
| Q8 | Seeders con IDs fijos | ✅ Idempotentes y basados en nombres |
| Q9 | Perfil solo para admins | ✅ Disponible para cualquier usuario autenticado |
| Q10 | Mensajes `session()` dentro de JS | ✅ JSON + `flash.js` |
| Q11 | IDs duplicados en formularios de borrado | ✅ `data-confirm` |
| Q12 | Pruebas borraban la BD; 21 de 25 fallaban | ✅ 58 pruebas verdes en sqlite + candado |
| Q13 | Validaciones en inglés | ✅ `lang/es` |
| Q14 | Código heredado (Lounge, Client, agencias…) | 🟡 Tablas eliminadas; los archivos de código esperan autorización (§6) |
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

## 5. Checklist de despliegue a producción

1. **Backup** de la BD de producción y de `storage/app/public/cursos` (regla 2).
2. `git pull` (cuando el usuario lo autorice) → `composer install --no-dev -o` → `npm ci && npm run build`.
3. `php artisan migrate --force`: solo elimina tablas legacy **vacías**; si alguna tiene datos, la conserva y lo registra en el log.
4. `php artisan storage:link` (si no existe) → `php artisan mera:harden-storage` → verificar que `https://cursos.meracorporation.com/storage/cursos/x.php` responda 403. Probar antes en LiteSpeed; si `storage` responde 500, quitar el bloque `php_flag` del `.htaccess`.
5. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://cursos.meracorporation.com`, `MERA_COURSES_URL` vacío (los cursos siguen en el mismo subdominio), resto de `MERA_*` según `.env.example`.
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
7. Cron de `schedule:run` en cPanel.
8. **Rotar las contraseñas de super admin** (ver §7). Es obligatorio.
9. **WordPress:** ✅ ya publicado (2026-10-08). Tras el despliegue, comprobar que siga mostrando 109 cursos (la API nueva agrega campos y es compatible).
10. Verificar: login, crear un curso de prueba, `/api/courses`, `/api/courses?page=1`, y que un enlace del WordPress abra **sin** `//storage`.

## 6. Pendiente de autorización del usuario

- **Código heredado:** el usuario decidió **conservarlo por ahora**. Está respaldado en `storage/backups/code_removed_20261008_154752/`: `CourseController_base.php`, `LoungeController.php`, `Auth/RegisteredUserController.php`, `Models/Lounge.php`, `Models/Client.php`, `views/lounges/*`, `views/auth/register.blade.php`, `views/welcome.blade.php` y los seeders `Lounge`, `Status`, `RolePermission` y `ModelHasRoles`. No tiene rutas ni lo usa nada.
- ⏳ **PENDIENTE AL PASAR A PRODUCCIÓN: rotar las contraseñas de Super Admin** con `php artisan mera:rotate-password --super-admins` (decisión del usuario: se hará en el despliegue; ver §7).
- ⏳ **Portadas rotas en producción (3):** cursos módulo 2, 3 y 5 de Mera Way 2.0 (NetSuite); su `cover_image` apunta a archivos inexistentes. Volver a subir la portada desde el panel después del despliegue.
- ⏳ **104 de 109 cursos sin categoría:** categorizarlos para que los filtros sirvan.
- ⏳ `composer self-update` (Composer desactualizado, con avisos de seguridad).
- **Decisiones de negocio:** ¿los cursos deben ser públicos? (S8). ¿Subdominio separado para el contenido? (S5).
- **QA visual** antes de unificar CSS e iconos y de limpiar `public/` (P6, P7, U9).

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
