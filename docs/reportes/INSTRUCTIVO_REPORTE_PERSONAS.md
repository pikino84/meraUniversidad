# Instructivo: Seguimiento de capacitación por año (todas las personas y todos los cursos)

> Documento de trabajo para Claude Code. Es **aparte** de `docs/reportes/INSTRUCTIVO_REPORTES.md`
> (reporte por curso, ya en producción) y de `docs/carpetas/INSTRUCTIVO_VERSIONES.md` (años y países).
> Las reglas de `CLAUDE.md` §0 aplican igual (español, sin commit/push sin permiso, backup antes de migrar,
> pruebas solo en sqlite, build solo en local, PHP 8.1). Antes de empezar, leer `CLAUDE.md` §0, §3 y §5,
> `INSTRUCTIVO_REPORTES.md` §4–§7 y este documento completo.

Creado: 2026-10-15 (revisado el mismo día con la referencia de Capacitación). §10 respondido el 2026-10-19
(queda abierto: exportaciones de encuestas de otros cursos). Estado: **por implementar**
(rama `qa`, verificar en `university.meracorporation.com`).

---

## 1. Qué se pide

Capacitación compartió un prototipo: **"Capacitación 2026: seguimiento por localidad"**. Es un HTML con d3,
del mismo estilo que el reporte por curso. Copia en `storage/app/reportes/referencia/seguimiento_2026_referencia.html`
(**no versionado: trae nombres de personas**).

Es la **base**, no el resultado final: hay que replicarlo dentro de la app, optimizarlo y mejorarlo (§7).

Responde **"¿cómo va la capacitación del año, persona por persona y curso por curso?"**. Toma todos los
cursos del año a la vez, no uno solo como el reporte actual.

## 2. Qué tiene la referencia (secciones y reglas, extraídas de su código)

| Sección | Contenido | Regla |
|---|---|---|
| Filtros | Localidad y área (en la URL) | Clic en el ranking = filtrar por localidad |
| Tarjetas | **Cursos por persona** (asignados ÷ personas) · **Avance en sus cursos** (completados ÷ asignados) · **Duración de sus cursos** (horas asignadas ÷ personas) · **Horas que llevan** (horas reales ÷ personas, y % de las asignadas) | Horas reales = `Duración` × `Progreso global` (**cuenta el avance parcial**) |
| Cómo va cada curso | Barra por curso: % de asignados que completó, en dos columnas | |
| Ranking de localidades | Todos los cursos juntos, por % completado | Podio y numeración solo para localidades con **≥ 10 personas**. Del lugar 13 en adelante, en ámbar |
| Avance mes a mes | Barras de completados por mes de **inicio**, y acumulado contra el total asignado | Igual que el reporte por curso |
| **Consulta de colaboradores** | Dos pestañas: **personas reprobadas por curso** (calificación < 80) y **pendientes por iniciar** (progreso 0). Filtro por curso, búsqueda por nombre o número, "Ver más" y **Descargar CSV** | Una persona puede salir en varios cursos |
| Avance por área | % completado por área (personas entre paréntesis) | |
| Evaluación | Selector de curso (o "todos"). Calificaciones por rango, dónde reprueban más (área y localidad), puestos que van bien o necesitan apoyo | **Estado relativo al promedio:** "va bien" si reprueba menos que el promedio; "necesita apoyo" si reprueba ≥ 10 puntos más que el promedio (y ≥ 10 %). Mínimos: todos los cursos → grupos con ≥ 100 evaluaciones y puestos con ≥ 30 evaluaciones y ≥ 15 personas; un curso → 10, 5 y 0 |
| Lo que opinan | Encuesta de **Higiene** (la única). Se ve con "todos los cursos" o con Higiene | Mismas gráficas del reporte por curso |
| Modo "bloqueado" | `window.__LOCK = '<localidad>'`: misma página fija en una sola localidad, para dársela a esa localidad | Oculta el filtro de localidad y las comparaciones entre localidades |

Cursos incluidos: **35 de 2026** (lista `cursos`: código, etiqueta, horas, `encuesta` 0/1 y `evaluacion` 0/1).
**No incluye** `NRM-SEG-007-EL-26-MX` (Alturas y EPP, 4 asignados) ni `NEG-TEC-005-BL-26` (NetSuite
Administrativo, 5 asignados). Hay que confirmar si se quitaron a propósito (§10).

## 3. Fuente: "Reporte de progreso de capacitaciones" (exportación de la plataforma)

La referencia se armó con este Excel, más el reporte de personal y la encuesta de Higiene. Es un Excel
(hoja `Reporte`) con **una fila por persona y curso asignado**. El corte del 2026-10-12 trae 74,574 filas,
3,328 personas y 60 cursos de 2025 y 2026, y pesa 13.8 MB. **No** se copia al proyecto: trae CURP, RFC y nómina.

No trae columnas resaltadas. Las necesarias se buscan por nombre de encabezado, como `ReportWorkbookReader`:

| Columna | Uso |
|---|---|
| `No. Empleado` | Llave de la persona |
| `Nombre`, `Apellido Paterno`, `Apellido Materno` | Consulta de colaboradores y ficha (**dato personal**: §8) |
| `Unidad de Negocio` → localidad · `Departamento` → área · `Puesto` | Mismas reglas que el reporte por curso (`config('mera.reports')`) |
| `Centro de Trabajo` | Restaurante o sala. Filtro nuevo (§7) |
| `Nombre de Curso` | `CÓDIGO \| Título`. El código da ruta, sub, #, modalidad, **año** y **país** (patrón en §4) |
| `Duración curso (hrs)` | Horas asignadas y reales |
| `Progreso global` | 0–1. **Completado = 1**. **Sin iniciar = 0** |
| `Iniciado`, `Completado` | Mes de avance y ficha |
| `Calificación Evaluación` | Rangos, promedio y reprobados (< 80) |
| `Progreso módulo 1…14` | Solo para la ficha individual (§7) |

**No se guardan:** nómina, RFC, CURP, dirección, razón social, proveedor ni certificado (viene vacío).

## 4. Hallazgos del análisis de datos

1. **Todos los códigos siguen el patrón** `^(NRM|NEG|CMP)-([A-Z]{3})-(\d{3})-(EL|BL)-(\d{2})(?:-([A-Z]{2,}))?$`.
   Sin sufijo = GLOBAL. El **año** sale del código: `-26` = 2026, y así se filtra el seguimiento.
2. **15 de los 60 códigos de la plataforma no están en el catálogo v12** (`NEG-LID-*` → `CMP-LID-*`,
   `NEG-TEC-*`/`NEG-SRV-*` → `NEG-OPE-*`). La propuesta está en `docs/reportes/equivalencias_codigos.csv`:
   14 equivalencias claras y 1 sin equivalente. La referencia muestra los códigos de la plataforma tal cual.
   En la app, el **nombre** sale del catálogo (título corto) y el código del catálogo se guarda como alias.
3. **337 filas tienen fecha `Completado`, pero progreso < 1.** Regla: completado = progreso 1.
4. **Cursos sin evaluación** (p. ej. Inducción a la Empresa): sin calificación. La referencia los marca con
   `evaluacion = 0` y no los ofrece en "Evaluación".
5. **Diferencias con la referencia:** las cifras cuadran **por curso** con diferencias de 1–2 personas, salvo
   `NEG-LID-003-BL-26` (Liderazgo Positivo 2026): la referencia cuenta **511 completados** y el Excel tiene
   **643** con progreso 1 (solo 524 con calificación). La referencia salió de **otra exportación** (otra fecha)
   y por eso el total no cuadra exacto con ninguna variante. Se usa para la **estructura y las reglas**, no
   como cifra de aceptación (§9).
6. **El Excel de progreso basta como directorio:** tiene unidad, departamento y puesto de cada persona. De
   1,439 encuestados activos de Higiene, 1,438 están en él. No hace falta el reporte de personal, salvo para
   decidir quién "ya no está activo" (§10).

## 5. Relación con el reporte por curso (ya en producción) — **conclusión**

Prueba con Higiene 2026, usando solo el Excel de progreso del 12 de octubre: 3,310 asignados, 2,619
completados (79.1 %), 572 sin iniciar, promedio 77.5 y 61.1 % con 80 o más. Con el reporte por curso
(3 Excel del 14 de octubre) salió 3,301, 2,623 (79 %), 563, 77.5 y 61 %. **Es la misma información.**

- El seguimiento con el filtro de **un curso** ya cubre el avance y la evaluación del reporte por curso. La
  referencia lo hace en "Evaluación" con el selector de curso.
- **Recomendación:**
  1. Construir el **seguimiento anual** como reporte nuevo (este instructivo).
  2. Hacerle **un selector de curso global**: al elegir un curso, todo el tablero muestra solo ese curso, más
     su encuesta si la tiene.
  3. **No tocar el reporte por curso actual.** Cuando el seguimiento esté en producción, preguntar si el
     reporte por curso se sigue usando o si basta con el seguimiento + la encuesta. Esa es la decisión de
     "unificar", y se toma con el uso real.
- **Reuso obligatorio** (no duplicar):
  - lectura por streaming (`ReportWorkbookReader`);
  - las reglas de localidad y área, extraídas a una clase común (p. ej. `OrgMapper`), que usen ambos;
  - el clasificador de la encuesta y `config/report_surveys.php`;
  - el patrón de temporales y su purga;
  - los componentes JS/CSS del tablero (`report.css`, ranking, barras y gráficas de `report-dashboard.js`),
    pasados a módulos compartidos.

## 6. Modelo de datos (migraciones aditivas, backup previo — regla 2)

| Tabla | Contenido | Volumen por corte |
|---|---|---|
| `course_catalog` | Catálogo (`code` único, `route`, `sub`, `number`, `modality`, `year`, `country`, `title`, `short_title`, `reference`, `competency`, `level`, `hours`, `instructor`, `aliases` JSON) | ~70 |
| `progress_reports` | Corte: `cut_date`, `title`, `summary`, `warnings`, `created_by`, `share_token`, timestamps | 1 |
| `progress_report_people` | `employee_number`, `full_name`, `locality`, `work_center`, `area`, `position`, totales precalculados | ~3,300 |
| `progress_report_items` | Persona × curso: `platform_code`, `catalog_code`, `year`, `country`, `route`, `progress`, `grade`, `hours`, `started_at`, `completed_at`, `modules` (JSON corto) | ~74,600 |
| `progress_report_surveys` | Encuestas del corte (una por curso que tenga): `course_code`, `survey` (clave de `report_surveys.php`), datos agregados como en `course_reports.data.encuesta` | 0–n |

- **Agregados precalculados al crear el corte**, guardados como JSON en `progress_reports`: los conjuntos
  `rank`, `cubo` e `inicio_mes` de la referencia, por año. El tablero los lee de ahí, sin recorrer 74 mil filas
  en cada visita.
- `progress_report_items` se consulta **solo** para la consulta de colaboradores y la ficha, con índices
  `(progress_report_id, year, catalog_code)` y `(progress_report_person_id)`.
- **Retención:** últimos N cortes (`config('mera.progress.keep_reports')`, propuesta 6).
- **Catálogo:**
  - Se carga subiendo `Catalogo_MERA_v*.xlsx` (super admin). La columna `Nomenclatura` es una fórmula: leer
    el valor calculado o armarlo con Ruta/Sub/#/Mod/Año/País.
  - Las equivalencias se cargan como `aliases`, después de confirmarlas.
  - Copia en `storage/app/reportes/referencia/Catalogo_MERA_v12.xlsx`.
- **Encuesta:** se sube aparte, por curso, en el mismo corte ("Agregar encuesta de un curso"). Usa
  `config/report_surveys.php`, y hoy solo existe la de Higiene. Si un curso no tiene encuesta, su sección
  "Lo que opinan" no se muestra.

## 7. Vistas: replicar la referencia + mejoras

### 7.1 Igual que la referencia (§2)
Todas las secciones, reglas y umbrales de §2, con la línea MERA y el `report.css` actual. Los umbrales van en
config (`mera.progress.dashboard`), como hoy `mera.reports.dashboard`.

### 7.2 Mejoras y optimización (lo que la referencia no resuelve)
1. **Datos personales fuera del HTML.** La referencia pesa **4.3 MB**, de los que **4 MB son datos**, e
   incluye los **nombres de ~23 mil registros** (reprobados y pendientes) dentro de la página, al alcance de
   cualquiera que la reciba.
   - En la app, la **consulta de colaboradores es del lado del servidor**: paginada, con búsqueda
     (`App\Support\Like`) y CSV generado en el servidor. Solo para administradores.
   - Lo que se descarga o comparte (§8) **no lleva nombres**.
2. **Año y corte seleccionables:** selector de año (2026 por defecto, sacado del código del curso) y de corte
   (fecha). **Comparación con el corte anterior:** +/- puntos en las tarjetas y en el ranking.
3. **Selector de curso global** (§5): el filtro de curso aplica a todo el tablero, no solo a "Evaluación".
4. **Filtros extra:**
   - **ruta** (Normativo / Negocio / Competencias): lo normativo es lo obligatorio;
   - **centro de trabajo** (restaurante o sala);
   - **puesto**.
5. **Consulta de colaboradores con más pestañas:**
   - reprobadas (< 80);
   - pendientes por iniciar (progreso 0);
   - **en curso sin terminar** (0 < progreso < 1);
   - **personas al 100 %** (para reconocer).
   - Cada fila lleva a la **ficha individual**.
6. **Ficha individual** (no existe en la referencia; es lo que se pidió como "reporte individual"):
   - encabezado: número, nombre, localidad, centro, área y puesto;
   - tarjetas: asignados, completados, en curso, sin iniciar, promedio, horas reales y asignadas;
   - cursos del año con estado, avance (y puntos por módulo), calificación y fechas;
   - historial de cortes;
   - **versión imprimible**. Sin descarga HTML ni link público.
7. **Seguimiento por localidad (el `__LOCK` de la referencia) como link para compartir:**
   - Un token por localidad. El link abre el tablero fijo en esa localidad, **sin nombres**, sin consulta de
     colaboradores y sin comparativos con otras localidades. Se puede desactivar.
   - Mismo mecanismo que "Compartir sin sesión" del reporte por curso (`noindex`, 404 al desactivarlo).
   - Si alguna localidad necesita la **lista nominal** de sus pendientes, eso es acceso con usuario, que es
     otro alcance (§10).
8. **"Descargar HTML" del tablero**, autocontenido como el del reporte por curso, solo con agregados.
   Debe pesar del orden de cientos de KB, no 4 MB.
9. **Nombre de los cursos** desde el catálogo (título corto), con su código del catálogo y el de la plataforma
   en el tooltip. Cursos con calificación pero sin `evaluacion` marcada: se avisa.

## 8. Datos personales y seguridad (no negociable)
- Nombre y avance por persona: **solo administradores** (roles del reporte por curso; confirmar en §10),
  con policy propia.
- **Nada con nombres sale de la app:** ni en el HTML descargado, ni en los links por localidad, ni en el
  tablero compartido. El CSV de la consulta es solo para usuarios con sesión, y queda en el activity log.
- No se guardan RFC, CURP, nómina ni dirección. Retención de §6. Pruebas con Excel **sintéticos**
  (`tests/Concerns/BuildsSpreadsheets.php`).
- El repo es público: los documentos y CSV de `docs/` no llevan nombres ni números de empleado. La copia de la
  referencia vive en `storage/`.

## 9. Procesamiento, rendimiento y criterios de aceptación

**Procesamiento:**
- 74,574 filas × 35 columnas, 15 veces más grande que un corte de curso. Lectura por streaming, solo las
  columnas de §3, inserción por lotes de 1,000 y agregados (§6) en la misma pasada.
- **Medir en QA** el tiempo y los límites **reales de la web** (`max_execution_time`, `memory_limit`). Para
  eso, un diagnóstico de solo super admin con `ini_get()`, porque la CLI no lee `.user.ini` (CLAUDE.md §5).
- Si tarda **menos de ~60 s:** en la misma petición, con `set_time_limit(300)` y el loader.
- Si tarda **más:** cola en BD (`jobs`), con `queue:work --stop-when-empty --max-time=50` desde el **scheduler
  que ya corre**. No hay que tocar el crontab. Avisar al usuario antes: cambia el despliegue.
- El Excel temporal se borra **siempre**.

**Aceptación:** con el Excel del 2026-10-12, con las reglas de localidad y exclusión del reporte por curso
(fuera: Cancun C4 y unidad vacía no-LATAM, 16 personas / 111 filas) y **los 35 cursos de la referencia**.
Un comando de solo lectura (como `mera:report-verify`) debe imprimir:

| Indicador (2026, 35 cursos) | Valor esperado |
|---|---|
| Asignaciones / personas | calcular y fijar aquí en la primera corrida (≈ 58.6 mil / ≈ 3,310) |
| Por curso | **Higiene 2026:** 3,310 asignados, 2,619 completados (79.1 %), 572 sin iniciar, promedio 77.5, 61.1 % con 80 o más |
| Todos los cursos de 2026 (37, sin quitar los 2 de §2) | 58,598 asignaciones, 3,312 personas, 41,095 completados (70.1 %), 15,636 sin iniciar, promedio 86.7 (n = 40,759) |
| Todos los años (60 cursos) | 74,463 asignaciones, 3,312 personas, 55,106 completados (74.0 %), promedio 86.7 (n = 54,761), 72,359 h completadas |

Con la referencia (otra exportación), las cifras por curso deben quedar a ±2 personas, salvo `NEG-LID-003-BL-26`
(§4.5). Revisión visual: todas las secciones de §2 deben estar y comportarse igual: clics, tooltips, filtros
en la URL, "Ver más", CSV, escritorio y 375 px.

## 10. Decisiones abiertas (preguntar al usuario ANTES de programar)
1. **Cursos del año:** ¿se excluyen `NRM-SEG-007-EL-26-MX` y `NEG-TEC-005-BL-26` como en la referencia? ¿Por
   qué? ¿Debe haber una lista de "cursos que cuentan para el seguimiento" editable en el panel?
2. **`NEG-LID-003-BL-26`:** ¿completado = progreso 1 (643) o = con calificación (la referencia: 511)?
3. **Equivalencias de códigos** (`docs/reportes/equivalencias_codigos.csv`): ¿las confirma Capacitación?
4. **Acceso:** ¿admin y super admin? ¿Las localidades necesitan entrar con usuario a su lista nominal? Eso es
   otro alcance (roles por localidad).
5. **"Ya no activo":** ¿se cruzan con el reporte de personal para quitar las bajas (12 personas en este corte)
   o basta el Excel de progreso?
6. **Encuestas:** ¿qué otros cursos tendrán encuesta? Cada uno necesita su configuración en
   `config/report_surveys.php`.
7. **El reporte por curso actual:** ¿se mantiene igual mientras se usa el seguimiento? (Recomendado: sí; §5.)
8. **Retención:** ¿cuántos cortes guardar? (propuesta: 6)

### Respuestas del usuario (2026-10-19)
1. **Cursos del año:** **solo 2026 en adelante** (`mera.progress.tracking_from_year`; lo de 2025 queda en el
   catálogo con "No cuenta"). Lista **editable** en el panel; arranca con **los 37 cursos de 2026** (incluye
   `NRM-SEG-007-EL-26-MX` y `NEG-TEC-005-BL-26`, que la referencia quitaba). Las cifras de aceptación de §9
   pasan a ser las de "Todos los cursos de 2026 (37)".
2. **Completado y aprobado (Capacitación, 2026-10-19):** son dos cifras distintas.
   - **Realizado (completado):** vio el curso al 100 % **y presentó la evaluación**, con cualquier calificación.
     Es la regla actual del reporte por curso (Higiene: 2,623 = 79 %), que **no cambia**.
   - **Aprobado:** realizado y calificación **≥ mínima del curso** (80; **Corresponsal Cambiario: 90**). La
     mínima va en el catálogo por curso (`passing_grade`, 80 por defecto). Realizado con menos → **reprobado**.
     Higiene: 1,605 aprobados (48.6 %) y 1,018 reprobados (30.8 %).
   - Calificación sin el 100 % de avance → **no** está realizado (en curso).
   - Curso **sin evaluación** (solo Inducción a la Empresa) → realizado y aprobado con el 100 %.
   - **Calificación:** la plataforma ya exporta **la más alta** cuando se repite la evaluación: se usa tal cual.
   - Estados por persona y curso: **aprobado · reprobado · en curso · sin iniciar**.
3. **Equivalencias:** Capacitación (2026-10-19): las diferencias de 2025 "son del año pasado, no las vamos a
   tomar en cuenta, solo las del 2026". Solo hay que corregir en la plataforma los **5 códigos de 2026**
   (`NEG-TEC-005-BL-26`, `NEG-SRV-004-BL-26-MX`, `NEG-LID-001-BL-26`, `NEG-LID-003-BL-26`, `NEG-LID-006-BL-26`);
   Coaching Ejecutivo 2025 ya no está pendiente. Los alias cargados se conservan (no estorban) y el catálogo
   acepta códigos alternos por curso para cambios futuros.
4. **Acceso:** admin y super admin. Las localidades solo reciben el link sin nombres (§7.2.7).
5. **Bajas:** se sube también el **reporte de personal** y se excluye a quien ya no aparece, con aviso del
   número excluido (como el reporte por curso).
6. **Encuestas:** todos los cursos tienen encuesta; las preguntas **cambian en algunos cursos** (intentan que
   sean iguales). ⏳ Capacitación enviará exportaciones de algunas encuestas para configurarlas
   (`config/report_surveys.php` por curso, con una base común).
7. **Reporte por curso actual:** mantiene "completado" (realizado). Mejora sugerida: mostrar también
   aprobados y reprobados con la mínima del curso.
8. **Retención:** últimos **6 cortes** (configurable).

## 11. Orden sugerido
1. Responder §10 con el usuario.
2. `course_catalog`: importación del Excel del catálogo y alias, con pruebas.
3. Extraer `OrgMapper` **sin cambiar resultados**: las pruebas actuales en verde y `mera:report-verify` exacto.
4. Lector y constructor del corte + agregados + comando de verificación (§9) con el Excel real, **en local**.
5. **Medir en QA** (§9) y decidir: en la misma petición o en cola.
6. Migraciones, controlador, `FormRequest`, policy y rutas. Tablero (§7.1), luego mejoras (§7.2) y ficha.
   JS/CSS compartido con el reporte por curso.
7. Encuesta por curso dentro del corte. Links por localidad y "Descargar HTML" (solo agregados).
8. Pruebas, QA en navegador, build local y documentación en `CLAUDE.md` (§3, §5, bitácora), con permiso.

## 12. Avance (rama `qa`)
- ✅ **Paso 2 — Catálogo de cursos (2026-10-19):**
  - Migración aditiva `2026_10_20_000000_create_course_catalog_table` (`course_catalog`, modelo `CatalogCourse`):
    datos del Excel + lo que se ajusta en el panel (`passing_grade`, `has_evaluation`, `in_tracking`, `aliases`).
  - `CourseCatalogImporter`: lee el Excel con `ReportWorkbookReader` (nuevo parámetro `headerScan`: los
    encabezados del catálogo están en la fila 3), arma el código con Ruta/Sub/#/Mod/Año/País (la columna
    "Nomenclatura" es fórmula), salta secciones y filas incompletas, crea o actualiza por código **sin pisar lo
    del panel ni borrar**, y aplica `config('mera.progress.catalog_defaults')` al crear (Corresponsal Cambiario
    90; Inducción a la Empresa sin evaluación). Equivalencias desde el CSV (`importAliases`).
  - Comando `mera:catalog-import <xlsx> [--aliases=<csv>] [--dry-run]`. Con el catálogo v12 real: **72 cursos**
    (50 de 2026), 0 filas sin leer, 14 códigos alternos; idempotente. Solo cuentan para el seguimiento los de
    2026 en adelante: los 22 de 2025 quedan con "No cuenta" (`tracking_from_year`).
  - Pantalla **Catálogo de cursos** (`/catalogo-cursos`, rutas `course-catalog.*`, super admin): cargar el Excel, filtrar por año/ruta,
    buscar (también por código de la plataforma) y ajustar mínima, evaluación, si cuenta y códigos alternos.
  - `CatalogCourse::resolve()` (código o alterno), `codeFromName()`, `done()` (realizado) y `passes()` (aprobado).
  - Pruebas: `tests/Feature/Reports/CourseCatalogTest.php` (Excel sintético con la misma estructura).
- ⏳ Paso 3: extraer `OrgMapper` sin cambiar cifras del reporte por curso.

