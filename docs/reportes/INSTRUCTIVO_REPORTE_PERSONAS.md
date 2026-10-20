# Instructivo: Seguimiento de capacitación por año (todas las personas y todos los cursos)

> Documento de trabajo para Claude Code. Es **aparte** de `docs/reportes/INSTRUCTIVO_REPORTES.md`
> (reporte por curso, ya en producción) y de `docs/carpetas/INSTRUCTIVO_VERSIONES.md` (años y países).
> Las reglas de `CLAUDE.md` §0 aplican igual (español, sin commit/push sin permiso, backup antes de migrar,
> pruebas solo en sqlite, build solo en local, PHP 8.1). Antes de empezar, leer `CLAUDE.md` §0, §3 y §5,
> `INSTRUCTIVO_REPORTES.md` §4–§7 y este documento completo.

Creado: 2026-10-15 (revisado el mismo día con la referencia de Capacitación). §10 respondido el 2026-10-19.
El mismo día por la tarde llegaron 5 encuestas más (§6.1) y se acordó la división **General / Específico**
con la columna `Alcance` del catálogo (§7.3). Estado: **en implementación** (catálogo, corte, tablero, colaboradores y ficha
listos en local, más §7.3 y encuestas por curso; faltan links por localidad, descarga y comparación con el corte
anterior; avance en §12)
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
- **Decisión del usuario (2026-10-20): CONECTARLOS** (no unificar todavía, no dejarlos separados):
  - La pestaña **Reportes** de cada versión de carpeta muestra arriba **"En el seguimiento anual"**: las cifras de
    su curso en el **último corte** que lo trae (asignados, realizado, aprobado, sin iniciar y, si hay, la
    encuesta), con link al tablero filtrado por ese curso. Debajo siguen los **"Cortes del curso"** (el reporte
    detallado con sus 3 Excel, que se comparte y se descarga).
  - El vínculo es el **código del catálogo de la versión** (`catalog_code`, en "Editar versión"). Sin código, la
    tarjeta invita a agregarlo; si el curso no cuenta para el seguimiento o no está en ningún corte, lo dice.
  - Al revés: el tablero del seguimiento filtrado por un curso enlaza a sus versiones de carpeta.
  - Diferencia que hay que explicar a Capacitación: el corte por curso dice **"Completaron"** (= realizado); el
    seguimiento separa **realizado** y **aprobado**.
  - Unificar (dejar de subir los 3 Excel por curso) se decide con el uso real, cuando el seguimiento tenga link
    para compartir y "Descargar HTML".
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

### 6.1 Encuestas recibidas (2026-10-19, respuesta 6 de §10)
Copias en `storage/app/reportes/referencia/encuestas/` (**no versionadas: traen nombres**).

| Encuesta (archivo) | Curso (se identifica por el título) | Respuestas (activos) | Recomienda | NPS |
|---|---|---|---|---|
| Protocolo de Huracán 2026 | `NRM-EMG-002-EL-26` | 1,182 (1,081) | 98 % | +80 |
| Tus áreas, tu seguridad: uso y conservación | `NRM-SEG-009-EL-26` | 1,286 (1,128) | 98 % | +81 |
| Orientación a Resultados | `CMP-OPE-003-EL-26` | 1,238 (1,130) | 98 % | +78 |
| Despega con MERA 2026 | `NEG-CUL-003-EL-26` | 1,721 (1,485) | 98 % | +79 |
| Salas VIP Lounge by MERA | `NEG-OPE-006-EL-26` (Inducción Concierge Salas VIP; **el único dudoso**) | 24 (24) | 96 % | +83 |

NPS = % que puso 9–10 menos % que puso 0–6.

**Común a todas** (mismo formato que la de Higiene):
- 10 columnas fijas (cuestionario, fecha, No. Empleado, colaborador, estatus `active`/`deleted`, departamento,
  puesto, centro, entidad, resultado), una columna de bienvenida y otra de cierre (vacías), y las preguntas.
- Sin columnas resaltadas.
- ⚠ **Las respuestas traen espacios al final** (`'Mucho  '`, `'Sí '`): recortar siempre antes de comparar.

**Lo que cambia por curso:**
- Las cerradas (6–9 Likert) cambian de texto y de opciones: "Mucho/Algo/Poco/Nada", "Sí, totalmente/Sí,
  parcialmente/No", "Totalmente/En su mayoría/Parcialmente/No", "Muy satisfecho(a)…".
- **Despega con MERA es de cultura y pertenencia:** identificación con la cultura (escala de 5), tiempo que se
  visualiza en MERA, qué le falta para integrarse, antigüedad y abiertas largas. No encaja en "favorable en las
  8 preguntas": necesita su propia vista con estas distribuciones.
- Abiertas: "lo más valioso / lo que más te gustó", "qué mejorarías", "situación real que te gustaría incluir"
  (Salas VIP) y comentarios.

**Diseño:**
1. **Indicadores comunes sin configurar nada:** % que recomienda (Sí/No), **NPS** (0–10) y % con problemas
   técnicos. Van en "Lo que opinan" y en una tabla **"Satisfacción por curso"** para comparar cursos.
2. **Configuración por encuesta** en `config/report_surveys.php`, como Higiene (base común + lo propio de cada
   curso): preguntas cerradas → respuestas favorables, y abiertas → catálogo de temas. La carga avisa qué
   columnas no reconoció.
3. **Modo "sin configurar":** sin configuración, se muestran los indicadores comunes y la distribución de cada
   pregunta cerrada (detectada por tener ≤ 6 respuestas distintas), sin "favorable". Ninguna encuesta se queda
   fuera.
4. **El curso de cada encuesta se propone solo:** al subirla, la app compara el título del archivo o del
   cuestionario (`Encuesta de Satisfacción - <título>`) con los títulos del catálogo y el usuario confirma o
   cambia en un selector. Hace falta saberlo para mostrar la encuesta dentro de su curso (como la referencia
   con Higiene), para poner su nombre en la comparación y para validar que quien respondió tenga el curso en el
   corte. **No** es una tarea para Capacitación.
5. **Temas de las abiertas:** igual que Higiene (palabras clave + corrección manual). Hasta tener el catálogo
   de temas de cada curso, se muestran "por clasificar".
6. **Respuestas vs realizados:** avisar si un encuestado no tiene el curso en el corte de progreso.

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

### 7.3 Cursos **generales** y **específicos** (acordado con Capacitación, 2026-10-19)
**Problema que planteó Capacitación:** hay cursos que no son para todos: los BL (presenciales con actividades
en la plataforma) y también varios EL para ciertas áreas o puestos. Si se suman todos, las localidades o áreas
que los reciben "parecen" tomar más cursos y más horas.

**Lo que dicen los datos** (corte 2026-10-12, 35 cursos de la referencia, 3,312 personas):
- **No es solo BL.** 13 cursos llegan a > 90 % de las personas, y los demás son específicos:
  - BL: Manipulación PREVERISK 32 %, Liderazgo Positivo 22 %, Gestión de Personal 8 %, Coaching 2 %…;
  - EL: Corresponsal Cambiario MX 44 %, Negociación 26 %, Alérgenos 24 %, Planeación 21 %, Inteligencia
    Emocional 18 %…
- Distorsionan **las cifras absolutas por localidad:**
  - cursos por persona: 15.1–18.7 con todos, 14.2–15.9 solo con generales;
  - horas por persona: 18.3–25.0 con todos, 17.2–18.9 solo con generales.
- El **% de avance** casi no cambia (1–4 puntos; Oaxaca de 19 % a 12 %), porque es sobre lo asignado. Los BL se
  completan casi siempre (88–100 %).

**Decisión:**
1. **Columna nueva `Alcance` en el Excel del catálogo**, con valores `General` o `Específico`. La llena
   Capacitación **tanto en la tabla de nomenclaturas como en la tabla con la información completa de los
   cursos**. Es la **única fuente**.
2. **En la app** (sobre el paso 2 ya hecho, §12):
   - Migración aditiva: `course_catalog.scope` (`general` / `specific`, nullable).
   - `CourseCatalogImporter` busca la columna **por encabezado** (`Alcance`, sin acentos ni mayúsculas) en
     **cualquier hoja** que tenga las columnas del curso (código o Ruta/Sub/#/Mod/Año/País). Si el mismo curso
     trae alcances distintos en dos hojas, **gana la tabla de nomenclaturas** y se avisa.
   - **Excepción a "no pisar lo del panel":** `scope` **sí se actualiza en cada importación**, porque su fuente
     es el Excel. En la pantalla del catálogo se **muestra pero no se edita**, para que no haya dos versiones de
     la verdad.
   - Si viene vacía (o el catálogo es viejo, sin la columna): respaldo con `Nivel` ("Todo MERA" = General; lo
     demás = Específico) y la importación **avisa** qué cursos lo tomaron así. Caso a revisar: Manipulación de
     Alimentos EL 2026 (`NRM-HIG-005`) dice "Cocina", pero está asignado al 99.5 %.
   - **No confundir con `in_tracking`** ("Cuenta"): ese decide si el curso entra al seguimiento; `scope` decide
     cómo se compara.
3. **Tablero:**
   - **Las comparaciones entre localidades, áreas y puestos usan solo los generales** por defecto: ranking,
     avance por área, cursos y horas por persona, y dónde reprueban más. El interruptor "Incluir cursos
     específicos" recalcula con todos, y la tarjeta lo indica.
   - Etiquetas: "Cursos por persona (generales)" y "Horas por persona (generales)". El % de avance siempre es
     "de sus cursos asignados".
   - **Nueva sección "Cursos específicos y presenciales":** por curso, a quién va (áreas y puestos con
     asignados), % realizado y aprobado, sin iniciar, y modalidad EL/BL. Sin ranking entre localidades; si se
     filtra uno, el ranking solo incluye a las localidades que lo tienen.
   - "Cómo va cada curso" lleva etiquetas "Específico" y "BL".
   - Nota en la sección: en un BL, el avance mide las **actividades en línea**, no la asistencia presencial.
4. **Ficha individual:** cursos separados en "Generales" y "Específicos de su puesto o área".
5. **Versiones de carpeta:** muestran la etiqueta General/Específico leyéndola del catálogo por su
   `catalog_code` (no se guarda en la versión).

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
- ✅ **§7.3 Generales y específicos (2026-10-20, sin commit):** migración aditiva `course_catalog.scope`; el importador
  busca «Alcance» en **cualquier hoja** (por Ruta/Sub/#/Mod/Año/País o por código escrito), gana la primera hoja si
  no coinciden (aviso) y **siempre** actualiza el alcance; sin columna, respaldo con «Nivel» («Todo MERA» = General)
  y aviso. Con el catálogo v12 (sin «Alcance» todavía) quedan 8 generales y 42 específicos de 2026: **el respaldo no
  es fiel** (p. ej. `NRM-HIG-005` dice «Cocina»); se corrige solo cuando Capacitación llene la columna.
  Catálogo: columna y filtro «Alcance» (solo lectura). Tablero: ranking, avance por área, «dónde reprueban más» y
  cursos/horas por persona **solo con generales** por defecto (aviso en pantalla), interruptor «Incluir cursos
  específicos»; con un curso elegido, cuenta ese curso. Etiquetas «Específico» y «BL» en «Cómo va cada curso».
  Sección **«Cursos específicos y presenciales»** (a quién va: 3 áreas y 3 puestos con más asignados, % realizado,
  aprobado y sin iniciar, nota de BL). Ficha: «Cursos generales» y «Específicos de su puesto o área». Versiones de
  carpeta: etiqueta General/Específico leída del catálogo por su `catalog_code`.
- ✅ **§6.1 Encuestas por curso (2026-10-20, sin commit):** tabla `progress_report_surveys` (migración aditiva), una
  encuesta por curso y por corte (se reemplaza al volver a subirla). `ProgressSurveyAnalyzer` (lector rápido, todas
  las columnas): indicadores comunes sin configurar (**% recomienda, NPS, % problemas técnicos**), distribución de
  cada pregunta cerrada y respuestas abiertas (solo texto; nunca nombre ni número). Con `course_codes` en
  `config/report_surveys.php` (Higiene: `NRM-HIG-002-EL-26`) también la satisfacción con la regla del reporte por
  curso: **Higiene 76.1 %** (igual que el reporte por curso). El curso se **propone por el título** entre los cursos
  del corte (5 de 6 reconocidos; Salas VIP pide elegirlo). Aviso de respuestas de personas sin el curso en el corte.
  Con las encuestas reales: Huracán recomienda 97.6 % / NPS +80; Tus áreas 98.2 % / +81; Orientación a Resultados
  97.7 % / +78; Despega 98.1 % / +79; Salas VIP 95.8 % / +83 (coincide con la tabla de §6.1). Tablero: sección
  «Lo que opinan» (tabla para comparar cursos; con un curso elegido, respuestas por pregunta) y formulario para
  agregar encuestas. Falta: temas de las abiertas por curso (palabras clave) y vista propia de Despega.
- ✅ **Paso 3 — `OrgMapper` (2026-10-19):** reglas de localidad/área/puesto en una clase; `PersonnelDirectory` la usa.
  `mera:report-verify` con los Excel reales de Higiene: **coincide fila por fila** (sin cambios en el reporte por curso).
- ✅ **Pasos 4-6 — Corte, tablero y ficha (2026-10-19, sin commit):**
  - Migración aditiva `2026_10_20_000001_create_progress_reports_tables` (`progress_reports`, `progress_report_people`,
    `progress_report_items`). Sin RFC, CURP, nómina ni dirección.
  - `FastSheetReader` (XMLReader directo sobre el .xlsx): el Excel de progreso (74,574 filas) se lee en **~13 s**; con
    openspout tardaba ~143 s. Mismas cifras con ambos lectores.
  - `ProgressReportBuilder`: localidad/área/puesto del **reporte de personal** (bajas y unidades excluidas fuera, con aviso),
    estado por asignación (aprobado / reprobado / en curso / sin iniciar) con las reglas del catálogo, códigos fuera del
    catálogo con aviso (solo de 2026 en adelante). `ProgressReportManager`: Excel en carpeta temporal privada y borrados
    siempre, inserción por lotes, se conservan los últimos 6 cortes.
  - **Medición con los Excel reales (12-oct + personal 14-oct), 2026, 37 cursos:** 58,405 asignaciones, 3,300 personas,
    al 100 % 40,984 (70.2 %), realizado 40,851 (69.9 %), aprobado 33,001 (56.5 %), reprobado 7,850, en curso 1,993,
    sin iniciar 15,561, promedio 86.7 (n = 40,649). Contra §9 (58,598 / 3,312 / 41,095 / 15,636 / 86.7) la diferencia son
    las 11 bajas (222 asignaciones) que ahora se excluyen. Higiene 2026: 3,299 / 2,612 realizados / 1,596 aprobados.
    Liderazgo Positivo 2026: 509 realizados (prototipo: 511). Armado completo en memoria: ~34 s.
  - Pantallas (`/seguimiento`, admin y super admin): lista de cortes, nuevo corte (2 Excel; fecha del nombre del
    archivo), tablero con filtros (año, curso, ruta, localidad, área, centro de trabajo; en la URL): tarjetas, cómo va cada
    curso, ranking de localidades (≥ 10 personas), avance por área, mes a mes, evaluación por rangos y dónde reprueban más;
    consulta de colaboradores por estado con búsqueda, paginada y **CSV** (queda en el historial); **ficha individual**
    imprimible con sus cursos y su avance en cortes anteriores.
  - Rendimiento del tablero: 1 consulta sin JOIN + cálculo en PHP (8.6 s → 1.5 s en local sin OPcache).
- ✅ **Conexión con los reportes por curso (2026-10-20, sin commit):** `ProgressCourseSummary` (por `catalog_code`),
  tarjeta "En el seguimiento anual" en la pestaña Reportes de la versión y links del tablero a las carpetas (§5).
- ✅ **Tablero reorganizado (2026-10-20, pedido del usuario; sin commit):** URL legible `/seguimiento/2026-10-12` (columna
  `slug`; `/seguimiento/1` redirige con 301), **editar nombre y fecha de corte** (la fecha cambia el link). Vista: barra de
  filtros compacta (año, curso, localidad, área; ruta, centro y "incluir específicos" en "Más filtros"), se aplica al
  cambiar (`auto-submit.js`) y muestra los filtros activos como etiquetas; 4 indicadores (realizado, aprobado, sin iniciar,
  promedio) con "¿Cómo se calcula?"; **una pestaña a la vez** (`?vista=`): Resumen (barra de avance del año, cursos y
  localidades con menor avance, mes a mes), Cursos (orden por menor/mayor avance o nombre + específicos y presenciales),
  Localidades (ranking y dónde reprueban más), Áreas y puestos (puestos con menor avance), Evaluación (rangos, dónde
  reprueban, promedio por curso) y Opiniones (encuestas). Cada pestaña calcula solo lo suyo.
- ✅ **§7.2.7 Links para compartir (2026-10-20, sin commit):** tabla `progress_report_shares`; pantalla "Compartir" del
  corte: un link por localidad o de toda la empresa, copiar, ver y desactivar (404). El link abre el tablero sin sesión
  (`noindex`), sin nombres, sin consulta de colaboradores ni respuestas abiertas; las respuestas cerradas con menos de 3
  menciones se agrupan en "Otras respuestas"; el de una localidad queda fijo en ella (aunque se pida otra en la URL) y no
  muestra comparaciones entre localidades. Queda en el historial y "Apagar todo lo compartido" también los apaga.
- ⏳ Pendiente: "Descargar HTML" (§7.2.8), comparación con el corte anterior (§7.2.2), temas de las respuestas abiertas
  por curso y medir el tiempo del corte en QA (§9).

