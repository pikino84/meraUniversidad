# Instructivo: carpetas de curso por **año** y **versión por país**

> Documento de trabajo para Claude Code. Complementa a `CLAUDE.md` (§10 "Carpetas de cursos") y **no lo
> sustituye**: las reglas de `CLAUDE.md` §0 aplican igual (español, sin commit/push sin permiso, backup
> antes de migrar, pruebas solo en sqlite, build solo en local, PHP 8.1). Antes de empezar, leer
> `CLAUDE.md` §0, §3, §5, §10 y este documento completo.

Creado: 2026-10-15. Estado: **por implementar** (rama `qa`, verificar en `university.meracorporation.com`).

---

## 1. Qué se pide

Hoy una carpeta de curso se divide en **Español / Inglés** (`courses.language` = `es`/`en`). Capacitación aclaró
que "Inglés" en realidad es **la versión para EUA**, y que los cursos cambian por **año** y por **país**:

```
Carpeta (curso)               p. ej. "Introducción a la Higiene"
└── Año                       2026, 2027, …
    └── Versión (país)        México · Latam · EUA   (+ las que agreguen después)
        └── Módulos en orden  (registros actuales de `courses`, sin mover archivos)
```

- Versiones iniciales: **México**, **Latam** y **EUA**.
- Debe poder **agregarse** una versión nueva desde el panel, sin programar. Caso previsto: dividir Latam en
  **Panamá**, **Colombia**, etc. Hoy no se hace porque los cursos casi no cambian entre esos países.
- El **idioma** deja de ser la división. Pasa a ser un dato de la versión: EUA = inglés, las demás = español.
  Así se siguen usando los textos traducidos de la página pública (`lang/en/program.php`).

## 2. Relación con la nomenclatura del catálogo (`Catalogo_MERA_v12`)

La nomenclatura oficial **ya codifica año y país**:

```
NRM-HIG-002-EL-26        Ruta-Sub-#-Modalidad-Año            (sin sufijo = GLOBAL)
NRM-SEG-005-EL-26-MX     Ruta-Sub-#-Modalidad-Año-País       (MX = solo México)
```

- `Ruta`: NRM (Normativo), NEG (Negocio), CMP (Competencias). `Mod`: EL (e-learning), BL (blended).
- El **#** cambia de un año a otro: "Fundamentos de Seguridad" es `NRM-SEG-001-EL-25` en 2025 y
  `NRM-SEG-002-EL-26` en 2026. Por eso la carpeta **no se puede deducir del código**: cada **versión
  (carpeta + año + país)** guarda su código del catálogo en un campo propio (`catalog_code`, opcional).
- Ese campo es el puente con los reportes:
  - **Reporte de curso:** al crear un corte, se avisa si el "Nombre de Curso" del Excel de inscripciones no
    trae el código de la versión.
  - **Reporte por persona:** permite pasar de una persona a la carpeta y la versión del curso
    (`docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md`).
- Hoy el catálogo solo usa `GLOBAL` y `MX`. Las versiones Latam y EUA probablemente usarán sufijos nuevos
  (`-LATAM`/`-LA`, `-US`). **Confirmar con Capacitación** (§9) y guardar el sufijo en la versión.

## 3. Modelo de datos (migraciones solo aditivas, con backup previo — regla 2)

### 3.1 `markets` — catálogo de versiones por país (editable en el panel)
| Campo | Ejemplo | Notas |
|---|---|---|
| `id` | | |
| `code` | `MX`, `LATAM`, `US` | Único. En mayúsculas. Igual al sufijo del catálogo cuando exista |
| `name` | México, Latam, EUA | Lo que se muestra |
| `slug` | `mexico`, `latam`, `eua` | Para la URL pública |
| `language` | `es` / `en` | Idioma de la página pública de esa versión |
| `parent_id` | Panamá → Latam | Nullable. Sirve para agrupar y como **respaldo** (§5.3) |
| `position` | | Orden en pestañas y listas |
| `is_active` | | Una versión inactiva no se ofrece para nuevos módulos |

Seeder o migración de datos: crear **México (es)**, **Latam (es)** y **EUA (en)**.

### 3.2 `course_group_versions` — una fila por carpeta + año + país
| Campo | Notas |
|---|---|
| `course_group_id` | FK, `cascadeOnDelete` |
| `year` | `smallint`, p. ej. 2026 |
| `market_id` | FK a `markets`, `restrictOnDelete` |
| `catalog_code` | Nullable. P. ej. `NRM-HIG-002-EL-26` (§2) |
| `title` | Nullable. Título propio de esa versión; si va vacío, el de la carpeta (EUA: título en inglés) |
| `description`, `evaluation_url`, `survey_url` | Nullable. Hoy viven en `course_groups` como `_es`/`_en`: pasan aquí por versión |
| `is_published`, `published_at` | La página pública se publica **por versión** |
| `position` | |
| Único | `(course_group_id, year, market_id)` |

### 3.3 Cambios en tablas existentes
- `courses`: agregar `course_group_version_id` (nullable, FK `nullOnDelete`).
  - Se **conservan** `course_group_id`, `language` y `position`: la API los publica y no se pueden quitar
    (CLAUDE.md §10).
  - `course_group_id` se sigue llenando, igual al de la versión, para no romper filtros ni conteos.
  - `language` se llena con el idioma de la versión.
- `course_reports`: agregar `course_group_version_id` (nullable). Un corte pertenece a una versión. Los
  cortes existentes se asignan en la migración de datos (§6).
- `course_groups`: **no se borra nada**. `name_en`, `description_es/en`, `evaluation_url_*`, `survey_url_*` e
  `is_published` quedan como respaldo hasta confirmar la migración. Quitarlos es otra fase, con permiso.

## 4. Panel (solo administradores)

- **Detalle de carpeta:**
  - Pestañas de **año** (2026 · 2027 · "+ Año").
  - Dentro de cada año, pestañas de **versión** (México · Latam · EUA · "+ Versión") con los módulos en orden
    (subir/bajar como hoy).
  - Cada versión muestra:
    - su código del catálogo;
    - su recuadro "Página pública" (Publicar/Apagar, link, copiar);
    - su sección "Reportes".
- **"+ Año"** crea el año vacío, o **copia la estructura** de otro año: crea las mismas versiones, sin
  módulos o con los mismos módulos asignados, a elegir. Copiar **no duplica archivos**: un módulo pertenece
  a una sola versión, así que "con los mismos módulos" solo se ofrece si se trata de *moverlos*. Decisión en §9.
- **"Mover a…"** (Módulos y carpeta): Carpeta → Año → Versión. Conserva "+ Nueva carpeta…".
- **Formulario del módulo:** Carpeta, Año, Versión y n.º de módulo (opcionales).
- **Versiones (países):** nueva pantalla "Versiones" (menú de Carpetas, solo super admin):
  - Lista con crear, renombrar, ordenar, activar/desactivar y elegir "pertenece a" (padre).
  - No se puede borrar una versión que tenga módulos o cortes; solo desactivarla.
- **Lista de carpetas:** conteo por año y versión. Ejemplo: "2026: MX 5 · Latam 5 · EUA 0".

## 5. Página pública y URLs

### 5.1 Restricción
**Ninguna URL de módulo cambia** (`storage/cursos/<carpeta>/index.html`, CLAUDE.md §10). Las páginas
públicas que ya se compartieron deben seguir abriendo: si cambian, con **redirección 301**.

### 5.2 Nuevas URLs (propuesta)
| URL | Muestra |
|---|---|
| `/<slug-carpeta>` | Versión **por defecto**: año más reciente publicado + versión por defecto (config `mera.versions.default_market`, propuesta `MX`) |
| `/<slug-carpeta>/<año>` | Ese año, versión por defecto |
| `/<slug-carpeta>/<año>/<slug-versión>` | Esa versión exacta. P. ej. `/introduccion-a-la-higiene/2026/eua` |
| `/en/<slug-carpeta>` (actual) | **301** → versión EUA del año más reciente |
| `/<slug-carpeta>/en` (heredada) | **301** → igual que la anterior |

- Selector en la página: **año** y **versión**, solo con las que estén publicadas y tengan módulos.
- `hreflang`: `es-MX` (México), `es-419` (Latam), `en-US` (EUA), tomado de `markets.language` + país.
- Avance por dispositivo (`localStorage`): clave `carpeta:<slug>:<año>:<versión>`. Para no perder el avance
  de quien ya empezó, la versión que hoy es "es" debe **leer también la clave vieja**
  `carpeta:<slug>:es` (migración en JS).
- `CourseGroup::reservedSlugs()` no cambia. Un **año** o un slug de versión nunca son el primer segmento, así
  que no chocan con rutas existentes. Las rutas nuevas se agregan **antes** de las de carpeta, al final de
  `routes/web.php`.

### 5.3 Respaldo por versión padre
Si se pide `/…/2026/panama` y Panamá no tiene módulos, se muestra **Latam** (su padre) con un aviso
pequeño ("Versión Latam"). Así se puede crear "Panamá" sin tener que duplicar todos los cursos.

### 5.4 Programas (`config/mera.php → programs`)
`/proceso-de-manipulacion-de-alimentos` y `/food-handling-process` siguen igual. En la config,
`languages.<idioma>.course_group` pasa a aceptar además `version => ['year' => 2026, 'market' => 'LATAM']`
(o `'EUA'` para inglés). Si se omite, se usa: año más reciente, versión por defecto del idioma
(es → la de §5.2; en → EUA). Hoy los 5 módulos del programa son **LATAM** (slugs `modulo-N-manejo-higienico-latam`).

## 6. Migración de lo existente (comando con `--dry-run`, como `mera:program-folder`)

`php artisan mera:versions-from-languages [--dry-run] [--year=2026] [--es=<market>] [--en=US]`
1. Respaldo previo (regla 2): `mysqldump` + JSON en `storage/backups/` con `id, course_group_id, language,
   position` de todos los módulos.
2. Por cada carpeta y cada idioma con módulos, crea la versión `(carpeta, año, mercado)`:
   - `es` → el mercado de `--es`. **Decisión en §9**; propuesta: México, salvo carpetas marcadas como Latam.
   - `en` → `--en` (EUA).
3. Asigna `course_group_version_id` conservando `position`.
4. Copia a la versión `description_*`, `evaluation_url_*`, `survey_url_*`, `is_published` y `name_en`
   (como `title` de EUA).
5. Asigna `course_reports.course_group_version_id` a la versión `es` del año de su `cut_date`.
6. **Idempotente:** si la versión ya existe, no la duplica. Imprime una tabla con lo que hizo o haría.

⚠ Caso conocido: la carpeta `manipulacion-de-alimentos` (programa) tiene **5 módulos LATAM** en Español.
Con la propuesta irían a **Latam**, no a México. El comando debe permitir indicar el mercado por carpeta
(`--map=manipulacion-de-alimentos:LATAM`).

### 6.1 Lo que hay hoy en producción (BD del 2026-10-15, 14:28)
Hay 10 carpetas, todas apagadas, y 120 módulos: **84 sin carpeta**, 28 en Español y 8 en Inglés.

**Capacitación ya nombra cada carpeta con la nomenclatura** (`CÓDIGO | Título`) y **crea una carpeta por año**
para el mismo curso. Eso confirma el diseño:

| # | Nombre actual | Slug | Módulos | Lectura |
|---|---|---|---|---|
| 1 | `NRM-HIG-005-EL-26 \| Manipulación de Alimentos` | `manipulacion-de-alimentos` | 5 es (LATAM) | 2026 · **Latam** |
| 2 | `NRM-HIG-002-EL-26 \| Introducción a la Higiene 2026` | `introduccion-a-la-higiene` | 5 **en** | 2026 · **EUA**. Los 5 en español (ids 20–25) están **sin carpeta** |
| 4 | `NEG-OPE-016-EL-26-MX \| Introducción al Puesto Salas VIP 2` | `introduccion-al-puesto-salas-vip-2` | 6 es | 2026 · México (sufijo MX) |
| 5 | `NRM-SEG-002-EL-26 \| Fundamentos Seguridad 2026` | `fundamentos-seguridad` | 3 es + 3 en | 2026 · México/Latam + EUA |
| 6 | `NRM-SEG-001-EL-25 \| Fundamentos Seguridad 2025` | `fundamentos-seguridad-2025` | 2 es | **2025** de la carpeta 5. ⚠ Sus módulos son de **EPP** (`NRM-SEG-006`): revisar con Capacitación |
| 7 | `NEG-CUL-001-EL-25 \| Experiencia MERA 2025` | `estandares-servicio-25` | 5 es | 2025. ¿Mismo curso que `NEG-CUL-002-EL-26` Estándares de Servicio? |
| 8 | `NRM-SEG-003-EL-25 \| Hostigamiento Acoso 2025` | `hostigamiento-acoso-25` | 1 es | **2025** de la carpeta 9 |
| 9 | `NRM-SEG-004-EL-26 \| Hostigamiento Acoso 2026` | `hostigamiento-acoso-26` | 3 es | 2026 |
| 10 | `NRM-SEG-005-EL-26-MX \| Factores Psicosociales` | `factores-psicosociales-26` | 3 es | 2026 · México (sufijo MX) |
| 11 | `NRM-SEG-009-EL-26 \| Áreas Seguridad` | `areas-seguridad-26` | 0 | 2026 |

Consecuencias para el comando de §6:
- **Leer el código del nombre de la carpeta** (`^CÓDIGO \| `, con el patrón de
  `INSTRUCTIVO_REPORTE_PERSONAS.md` §4). De ahí salen **año**, **país** (sufijo; sin sufijo = GLOBAL) y
  `catalog_code` de la versión.
- **Fusionar carpetas del mismo curso de años distintos** (6→5 y 8→9; ¿7 con un futuro Estándares 2026?).
  Esto es un **mapa explícito** que confirma el usuario (`--merge=6:5,8:9`), **nunca automático**. Al fusionar:
  - la carpeta absorbida queda vacía y **no se borra**;
  - su slug sigue funcionando con **301** a la versión del año correspondiente;
  - el nombre de la carpeta final queda sin código ni año (p. ej. "Fundamentos Seguridad"), porque el código
    pasa a la versión.
- **GLOBAL** (sin sufijo) no es México ni Latam. Una versión con módulos en español y código GLOBAL va a la
  versión que diga el usuario (§9.1). Ninguna carpeta actual usa sufijo `-LATAM` ni `-US`.
- "Inglés" = **EUA** en todas las carpetas actuales (2 y 5): confirmado por los títulos de los módulos.
- Los **84 módulos sin carpeta** no se tocan: los acomoda Capacitación con "Mover a…", que ya pide año y versión.

## 7. API (`/api/courses`)
Solo **agregar** campos (CLAUDE.md §10): `year`, `market` (`code`), `market_name` y `catalog_code`. `language`,
`course_group_id`, `course_group` y `position` siguen iguales. El WordPress del catálogo no se toca.

## 8. Pruebas (sqlite `:memory:`, regla 3) y QA
- **Feature:**
  - CRUD de mercados: no se puede borrar uno con módulos; desactivar sí.
  - Versiones: único `(carpeta, año, mercado)`.
  - Mover en lote a una versión.
  - Publicar por versión. Un idioma o versión sin módulos da 404.
  - Redirecciones 301 de `/en/<slug>` y `/<slug>/en`.
  - Respaldo a la versión padre.
  - Programa con `version`.
  - API con los campos nuevos y los viejos sin cambios.
- **Migración de datos:** el comando en `--dry-run` no escribe; real + idempotente; con `--map`.
- **QA** en `university.meracorporation.com`, con la BD de QA (`docs/despliegue/QA_UNIVERSITY.md`):
  - correr el comando de §6 y revisar Higiene y `manipulacion-de-alimentos`;
  - revisar las páginas públicas y los links viejos;
  - escritorio y 375 px.

## 9. Decisiones abiertas (preguntar al usuario ANTES de programar)
1. Los módulos que hoy están en **Español**: ¿a qué versión van, **México** o **Latam**? ¿Hay carpetas que
   sean de un país en particular? (La de manipulación de alimentos es Latam.)
2. **Año** de lo existente: ¿todo es 2026? ¿Hay módulos de 2025 que deban quedar en 2025?
3. **Versión por defecto** en `/<slug-carpeta>`: ¿México?
4. **Sufijos del catálogo** para Latam y EUA (`-LATAM`, `-LA`, `-US`…): ¿ya los tiene definidos Capacitación?
5. **"+ Año"**: ¿basta con crear el año vacío y copiar solo la *estructura* (versiones, links, descripción)?
6. **¿Un módulo puede estar en dos versiones a la vez?** Ejemplo: el mismo video para México y Latam.
   El diseño de arriba dice que **no** (un módulo, una versión), igual que hoy con carpeta + idioma. Si se
   necesitara, haría falta una tabla pivote. Es más trabajo y cambia la API, así que solo si lo piden.

7. **Fusión de carpetas por año** (§6.1): ¿se juntan Fundamentos Seguridad 2025 + 2026 (6→5) y Hostigamiento
   2025 + 2026 (8→9)? ¿Experiencia MERA 2025 (7) y Estándares de Servicio 2026 son el mismo curso? ¿Los módulos
   de la carpeta 6 son de verdad de Fundamentos 2025, o de EPP?
8. **Higiene 2026:** ¿sus 5 módulos en español sin carpeta (ids 20–25) van a la versión México o Latam de la
   carpeta 2?

## 10. Orden sugerido
1. Responder §9 con el usuario.
2. Migraciones (backup antes) + modelos `Market` y `CourseGroupVersion` + `CourseGroupOrganizer` por versión,
   con pruebas.
3. Comando de §6 (`--dry-run` primero) y pruebas.
4. Panel (pestañas año/versión, "Mover a…", formulario del módulo, pantalla "Versiones").
5. Página pública, rutas nuevas, 301 y programa. Reportes por versión.
6. API (campos nuevos). Build local, QA en el servidor y documentación en `CLAUDE.md` (§3, §10 y bitácora),
   con permiso.
