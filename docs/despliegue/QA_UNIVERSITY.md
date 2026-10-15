# Entorno QA: university.meracorporation.com → rama `qa`

> Complementa a `CLAUDE.md` §5 (despliegue a producción). Las reglas de `CLAUDE.md` §0 aplican igual:
> backup antes de tocar datos (regla 2), build solo en local (regla 7), nada de commit/push sin permiso
> (regla 1). Antes de seguir esta guía, leer las **lecciones de §5** (sobre todo 1, 2, 5 y 7).

Creado: 2026-10-15. Estado: **por configurar.**

## Qué se arma

| | Producción | QA |
|---|---|---|
| Dominio | `cursos.meracorporation.com` | `university.meracorporation.com` |
| Document root (cPanel) | `cursos.meracorporation.com/public` | `qa.cursos.meracorporation.com/public` |
| Carpeta | `~/cursos.meracorporation.com` | `~/qa.cursos.meracorporation.com` |
| Rama | `dev` | `qa` |
| Base de datos | `merabl_cursos` | `merabl_cursos_qa` (copia de producción) |
| Archivos de cursos (9 GB) | Sí | **No** (no caben: disco al 98 %) |
| Correo | SMTP real | `MAIL_MAILER=log` (no sale nada) |

**Por qué dos carpetas:** una carpeta tiene una sola rama activa. Mientras `university.` apuntaba a
`cursos.meracorporation.com/public`, servía producción. La carpeta de QA se crea con
**`git worktree`**: usa el mismo `.git` de producción (casi no ocupa disco), pero tiene su propia
rama. Git impide además tener `dev` activa en las dos carpetas a la vez.

⚠ **Nunca** `git pull origin qa` en la carpeta de producción, ni `git pull origin dev` en la de QA.
⚠ QA usa **su propia BD**. Si el `.env` de QA apuntara a `merabl_cursos`, las migraciones de `qa`
se aplicarían en producción. El paso 6 lo verifica antes de migrar.

---

## Configuración inicial (una sola vez)

Todo en la Terminal de cPanel salvo donde se indique. Variables de la sesión:
```bash
umask 022 && TS=$(date +%Y%m%d_%H%M)
PROD=~/cursos.meracorporation.com
QA=~/qa.cursos.meracorporation.com
```

### 0. Revisiones previas
```bash
quota -s 2>/dev/null || df -h ~          # espacio: QA necesita ~150 MB (código + vendor + build)
ls -la $QA 2>/dev/null                   # ¿cPanel ya creó la carpeta?
git -C $PROD branch --list qa            # ¿ya existe la rama qa en el servidor?
git -C $PROD status --short              # producción no debe cambiar con nada de esta guía
```
- Si `$QA` **no existe**: seguir.
- Si existe y solo tiene una carpeta `public/` vacía (la crea cPanel al configurar el subdominio):
  `rmdir $QA/public $QA`. Si `rmdir` falla, la carpeta **no está vacía**: detenerse y revisar qué
  tiene antes de borrar nada (regla 2).

### 1. Carpeta de QA con la rama `qa`
```bash
cd $PROD && git fetch origin qa
git worktree add --track -b qa $QA origin/qa      # si el paso 0 mostró que qa ya existe: git worktree add $QA qa
git worktree list                                  # dos líneas: $PROD [dev] y $QA [qa]
git -C $QA log --oneline -1                        # debe ser el último commit de origin/qa (hoy 684ba13)
```

### 2. Carpetas que no vienen en git
```bash
cd $QA
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/framework/testing storage/logs bootstrap/cache
mkdir -p storage/app/reportes/codigos && chmod 700 storage/app/reportes storage/app/reportes/codigos
```

### 3. Base de datos de QA (cPanel → Bases de datos MySQL)
1. Crear la base `cursos_qa` (cPanel la deja como **`merabl_cursos_qa`**).
2. Crear el usuario `usr_qa` (queda como **`merabl_usr_qa`**) con una contraseña generada por cPanel.
3. Agregar ese usuario **solo** a `merabl_cursos_qa`, con **todos los privilegios**.
4. **No** dar acceso a este usuario a `merabl_cursos`.

### 4. `.env` de QA
```bash
cd $QA && cp $PROD/.env .env && chmod 600 .env && nano .env
```
Cambiar **solo** esto:
```dotenv
APP_NAME="Mera Universidad QA"
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://university.meracorporation.com
DB_DATABASE=merabl_cursos_qa
DB_USERNAME=merabl_usr_qa
DB_PASSWORD='<la del paso 3>'
MAIL_MAILER=log
SESSION_DOMAIN=
```
- `MAIL_MAILER=log`: la copia de la BD trae los correos reales de los usuarios, y QA no debe mandarles
  nada. Los correos quedan en `storage/logs/laravel.log`.
- Si `MERA_COURSES_URL` está definido en el `.env` de producción, dejarlo vacío en QA.
- Después: `php artisan key:generate --force` (llave propia de QA). No afecta las contraseñas.

### 5. Copiar los datos de producción a QA
Mismo método que la lección 5 de §5: sin teclear contraseñas, con archivos temporales que se borran al final.
```bash
umask 077
get(){ grep -E "^$1=" "$2" | head -1 | cut -d= -f2- | sed -e "s/^['\"]//" -e "s/['\"]\$//"; }
cnf(){ printf '[client]\nuser=%s\npassword="%s"\nhost=%s\n' "$(get DB_USERNAME $1)" "$(get DB_PASSWORD $1)" "$(get DB_HOST $1)"; }
cnf $PROD/.env > ~/.my_prod.cnf && cnf $QA/.env > ~/.my_qa.cnf

mysqldump --defaults-extra-file=$HOME/.my_prod.cnf --single-transaction --routines --triggers --no-tablespaces \
  merabl_cursos > ~/backups_cursos/bd_para_qa_$TS.sql
ls -lh ~/backups_cursos/bd_para_qa_$TS.sql          # no debe estar vacío
mysql --defaults-extra-file=$HOME/.my_qa.cnf merabl_cursos_qa < ~/backups_cursos/bd_para_qa_$TS.sql

rm -f ~/.my_prod.cnf ~/.my_qa.cnf && umask 022
```

### 6. Dependencias, verificación de la BD y migraciones
```bash
cd $QA
php -m | grep -iE '^(zip|xmlreader)$'                       # openspout necesita las dos
~/bin/composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan tinker --execute="echo DB::connection()->getDatabaseName(), PHP_EOL;"
```
**Debe decir `merabl_cursos_qa`.** Si dice `merabl_cursos`, **detenerse** y corregir el `.env`.
```bash
php artisan migrate --force          # en QA: crea course_reports
php artisan storage:link
php artisan mera:harden-storage
```

### 7. Archivos que no están en git
- **`.user.ini`** (límites de subida de 512M y 300 s): `find $PROD -maxdepth 2 -name .user.ini`
  y copiarlo al mismo lugar relativo dentro de `$QA`.
- **Clasificación previa de Higiene:** subir `higiene.csv` con el Administrador de archivos a
  `qa.cursos.meracorporation.com/storage/app/reportes/codigos/higiene.csv` y luego
  `chmod 600 $QA/storage/app/reportes/codigos/higiene.csv`. Trae números de empleado: nunca a git.
- **Archivos de cursos:** no se copian. En QA las carpetas de curso, los cortes y los reportes
  funcionan, pero "Ver módulo" y las portadas dan 404. Para probar un módulo, subir un ZIP de prueba
  desde el panel de QA.

### 8. Frontend
**En local**, en la rama `qa` y sin cambios pendientes, seguir el procedimiento "Frontend" de §5:
`npm run build` y luego `storage/deploy/build_<commit>.zip`. El manifest debe tener **4 entradas**.
Subir el ZIP con el Administrador de archivos a **`qa.cursos.meracorporation.com/public/`**.
```bash
cd $QA/public && umask 022
test -f build_<commit>.zip || echo "FALTA EL ZIP: NO CONTINUAR"
rm -rf build_nuevo && mkdir build_nuevo && unzip -q build_<commit>.zip -d build_nuevo
cat build_nuevo/build/manifest.json                       # 4 entradas
mv build_nuevo/build build && rmdir build_nuevo && rm build_<commit>.zip   # primera vez: no hay build anterior
find build -type f -exec chmod 644 {} + && find build -type d -exec chmod 755 {} +
```

### 9. Cachés y permisos
```bash
cd $QA
php artisan config:cache && php artisan route:cache && php artisan view:cache
find public storage/app/public -type f ! -perm -o=r -exec chmod 644 {} +
find public storage/app/public -type d ! -perm -o=x -exec chmod 755 {} +
find public storage/app/public -perm -o=w ! -type l      # no debe mostrar nada (lección 7)
```

### 10. Cron de QA (pedir autorización: el crontab es compartido con ~15 sitios)
Para que en QA también se borren cada hora los Excel temporales (`mera:purge-report-uploads`),
**agregar una línea** con `crontab -e`, sin tocar las demás:
```
* * * * * cd /home/merabl/qa.cursos.meracorporation.com && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```
Respaldo antes: `crontab -l > ~/backups_cursos/crontab_$TS.txt`.

### 11. Que QA no quede abierto (recomendado)
La BD de QA es una copia con datos reales (usuarios y reportes). Opciones:
- **cPanel → Privacidad de directorios** sobre `qa.cursos.meracorporation.com/public`: pide usuario y
  contraseña antes de mostrar el sitio.
  ⚠ cPanel escribe esas reglas en `public/.htaccess`, que **está en git**. Antes de cada `git pull`
  en QA, revisar `git -C $QA status`; si `.htaccess` aparece modificado, respaldarlo
  (`cp public/.htaccess ~/backups_cursos/htaccess_qa_$TS`) y volver a aplicarlo después del pull.
- ✅ **No se indexa en Google** (en `qa` desde el 2026-10-15): `SecurityHeaders` manda
  `X-Robots-Tag: noindex, nofollow, noarchive` en toda respuesta de Laravel cuando `APP_ENV` no es
  `production` (QA es `staging`). Producción no cambia. Requiere tener ese commit en QA
  (`git pull origin qa` + `php artisan config:cache`). Verificar:
  `curl -sI https://university.meracorporation.com/login | grep -i x-robots-tag`.
  `robots.txt` se deja igual a propósito: con `Disallow`, Google no leería el `noindex`. Si
  `university.` ya aparece en Google, pedir la baja en Search Console (Retirada de URLs).

### 12. Verificar
- `https://university.meracorporation.com/login` → muestra "Mera Universidad QA" y el certificado SSL
  es válido (si no, cPanel → SSL/TLS Status → ejecutar AutoSSL para `university.`).
- `curl -sI https://university.meracorporation.com/login | head -5` → 200 (o 401 si se activó el paso 11).
- `https://cursos.meracorporation.com/login` sigue igual y `git -C $PROD log --oneline -1` no cambió.
- Iniciar sesión en QA con una cuenta propia (las contraseñas son las de producción al momento de la copia).
- Carpeta de Higiene → Reportes → Nuevo corte con los Excel del 2026-10-14: deben salir las cifras de
  `docs/reportes/INSTRUCTIVO_REPORTES.md` §9, y `ls $QA/storage/app/tmp/report-uploads` debe quedar vacío.
- Crear un usuario de prueba: la invitación debe aparecer en `$QA/storage/logs/laravel.log` y **no** llegar por correo.

---

## Actualizar QA (rutina)
```bash
cd ~/qa.cursos.meracorporation.com && umask 022 && TS=$(date +%Y%m%d_%H%M)
git status --short                                   # debe estar limpio (ver paso 11 si sale .htaccess)
git rev-parse HEAD > ~/backups_cursos/commit_anterior_qa_$TS.txt
git pull origin qa
~/bin/composer install --no-dev --optimize-autoloader   # solo si cambió composer.lock
php artisan optimize:clear
php artisan migrate --force                              # solo si hay migraciones (dump de merabl_cursos_qa antes)
php artisan config:cache && php artisan route:cache && php artisan view:cache
# + build: si el commit cambia el frontend, instalar build_<commit>.zip en $QA/public con el
#   procedimiento seguro de §5 (aquí sí hay build anterior: respaldarlo como build_qa_<fecha>)
# + chmod del paso 9
```
**Refrescar los datos de QA** con los de producción: repetir el paso 5 (con `mysqldump` de
`merabl_cursos_qa` antes) y luego `php artisan migrate --force` en QA.

**Pasar a producción:** cuando QA esté aprobado, `qa` → `dev` (con autorización, regla 1) y desplegar
en producción con la guía de §5 ("Despliegue de reportes de curso").

## Quitar QA (si alguna vez hace falta)
```bash
cp ~/qa.cursos.meracorporation.com/.env ~/backups_cursos/env_qa_$(date +%Y%m%d_%H%M)
cd ~/cursos.meracorporation.com && git worktree remove --force ~/qa.cursos.meracorporation.com
```
(`--force` porque la carpeta tiene archivos que no son de git: `.env`, `vendor/`, `storage/`, `public/build`.)
Después: borrar la BD y el usuario de QA en cPanel, quitar la línea del cron y apuntar `university.` a
donde corresponda. La rama `qa` sigue en GitHub.
