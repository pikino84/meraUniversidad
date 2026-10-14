# Instructivo: Reportes de curso (por carpeta)

> Documento de trabajo para Claude Code. Complementa a `CLAUDE.md` y **no lo sustituye**: las
> reglas obligatorias de `CLAUDE.md §0` (español, sin commit/push sin permiso, backup antes de
> migrar, pruebas solo en sqlite, build solo en local) aplican a todo lo que se haga aquí.
> Antes de empezar, leer `CLAUDE.md` §0, §1, §3, §5, §8 y §10.

Creado: 2026-10-14. Estado: **en desarrollo en la rama `qa`** (decisiones de §10 resueltas el 2026-10-14).

---

## 1. Objetivo

Que cada **carpeta de curso** (`CourseGroup`, CLAUDE.md §10) tenga su **reporte de avance,
evaluación y satisfacción**, igual al reporte que hoy se arma a mano:
`Curso_Introduccion_a_la_Higiene_2025-2026.html` (copia en
`storage/app/reportes/referencia/plantilla_reporte.html`).

Un administrador sube los 3 Excel que exporta la plataforma de capacitación, el sistema calcula
los datos y muestra el reporte dentro del panel. Puede haber varios cortes (fechas) por carpeta.

## 2. De dónde salen los datos (y por qué hay que subir los Excel)

La BD `merabl_cursos` **no tiene** inscripciones, avances, calificaciones ni respuestas: solo el
catálogo de módulos. Esos datos viven en la plataforma externa de capacitación y solo se obtienen
como exportación. Por eso **cada corte requiere que nos compartan (o descarguemos) los 3 archivos**:

| Archivo (nombre típico) | Contenido |
|---|---|
| `Reporte de inscripciones <CLAVE> _ <Curso> <fecha>.xlsx` | Una fila por persona inscrita al curso |
| `<fecha> Encuesta de satisfacción - <Curso> responses.xlsx` | Una fila por respuesta de encuesta |
| `Reporte de personal <fecha>.xlsx` | Plantilla activa completa: localidad, área y puesto de cada persona |

Si en el futuro la plataforma ofrece API, se reemplaza solo la etapa de "lectura" (§5.2); el
resto del diseño no cambia. **No** proponerlo como parte de esta fase.

## 3. Material de referencia

| Ruta | Versionado | Qué es |
|---|---|---|
| `docs/reportes/INSTRUCTIVO_REPORTES.md` | Sí | Este documento |
| `docs/reportes/catalogo_higiene.csv` | Sí | Temas de comentarios abiertos del curso de Higiene (sin datos personales) |
| `storage/app/reportes/referencia/plantilla_reporte.html` | No (`storage/` está en `.gitignore`) | Reporte base: CSS, d3 v7.9.0 y la lógica de las gráficas |
| `storage/app/reportes/referencia/generar_reporte.py` | No | **Implementación de referencia en Python.** Es el "oráculo": la versión PHP debe dar exactamente los mismos datos |
| `storage/app/reportes/referencia/codigos_texto.csv` | No | Clasificación ya hecha de las 1,634 respuestas abiertas de Higiene, por No. de empleado |
| `storage/app/reportes/referencia/LEEME.md` | No | Uso del script Python y diccionario de columnas |

Los Excel reales están en `C:\Users\jesus.castro\Downloads\` (corte del 2026-10-14). **Nunca**
copiarlos al repo ni usarlos como fixtures de pruebas: traen CURP, RFC, NSS, teléfonos, datos de
salud y familiares. Las pruebas usan Excel sintéticos generados en el propio test (§8).

⚠ El repo es público (CLAUDE.md §5). Nada de lo que está en `storage/app/reportes/` debe salir
de `storage/`.

## 4. Columnas que se leen

Los Excel traen en amarillo las columnas útiles. Se buscan **por nombre de encabezado** (sin
acentos, sin mayúsculas, sin `*`), nunca por posición ni por color. Si falta una columna
obligatoria, se rechaza el archivo con un mensaje claro que diga cuál falta.

### Reporte de personal (hoja 1)
| Columna | Uso |
|---|---|
| `No. de empleado` | Llave de cruce |
| `Unidad de negocio` | → **localidad** (§6.1) |
| `Departamento` | → **área** (§6.2) |
| `Puesto` | → **puesto** con mayúscula inicial en cada palabra (equivalente a `str.title()` de Python: `Cajero(a)` → `Cajero(A)`) |

⚠ Este archivo tiene **dos** columnas `Dirección` y varias `Teléfono móvil` / `Beneficiario`
repetidas: tomar siempre la **primera** coincidencia. Hay 2 filas sin número de empleado: se ignoran.

### Reporte de inscripciones (hoja 1)
| Columna | Uso |
|---|---|
| `No. Empleado` | Llave |
| `Estatus` | `completed` = completó |
| `Progreso %` | `0` (o vacío) y no completado = **todavía no empieza** |
| `Calificación` | Solo de quienes completaron. Rangos: ≥80, 70–79, 60–69, <60 |
| `Iniciado` | Mes (`YYYY-MM`) de inicio de quienes completaron |

### Encuesta de satisfacción (hoja 1)
| Columna (se busca por fragmento del encabezado) | Favorable si la respuesta es |
|---|---|
| `No. Empleado` | Llave |
| `Estatus colaborador` | `deleted` → localidad y área = `Ya no activo` |
| "…temas tratados son útiles…" → `f_utilidad` | `Muy de acuerdo`, `De acuerdo` |
| "…riesgos invisibles…" → `f_riesgos` | `Sí` |
| "…ejemplos y casos presentados…" → `f_ejemplos` | `Muy claros y aplicables` |
| "…herramientas prácticas…" → `f_herramientas` | `Sí, muchas` |
| "…capaz de aplicar…" → `f_capaz` | `Sí` |
| "…fácil fue acceder…" → `f_facil` | `Muy fácil`, `Fácil` |
| "Los recursos…" → `f_recursos` | `Muy útiles`, `Útiles` |
| "…Recomendarías este curso…" → `f_recomienda` | `Si`, `Sí` |
| "…más te gustó…" | Texto libre → temas `v..` (§6.4) |
| "…Qué mejorarías…" | Texto libre → temas `m..` (§6.4) |

**Satisfacción** = % de respuestas favorables en las **8** preguntas.

Columnas en amarillo que el reporte base **no usa**: nombres, `Dirección`, `Centro de trabajo`,
`Completado`, `Última actividad`, `Nombre de Curso`, problemas técnicos, escala 0–10 y
sugerencias. No guardarlas.

⚠ Las preguntas cerradas son **propias de cada curso**. La configuración de la encuesta debe
vivir por carpeta (§5.1), con las 8 de Higiene como valor por defecto.

## 5. Diseño propuesto en Laravel

### 5.1 Datos (migración solo aditiva, con backup previo, regla 2)
- `course_reports`: `id`, `course_group_id` (FK, `cascadeOnDelete`), `cut_date` (fecha de corte),
  `title`, `data` (JSON con los 4 conjuntos de §7), `summary` (JSON con los totales para la
  lista), `warnings` (JSON), `created_by`, timestamps. Activity log en crear y borrar.
- `course_report_settings` (o columnas JSON en `course_groups`, a decidir): configuración de la
  encuesta (preguntas → respuestas favorables) y el catálogo de temas (`codigo`, `tipo`
  mejora/valora, `etiqueta`, `palabras_clave`). Valores por defecto: los de Higiene.
- Reglas generales de agrupación (localidades, exclusiones, áreas, umbrales): en
  `config/mera.php → reports` (§6).

### 5.2 Servicios (`app/Services/Reports/`)
Mismo patrón que `CourseManager`: reciben **rutas**, no el `Request`, para poder pasar a un Job.
- `ReportWorkbookReader`: lee la hoja 1 en *streaming* y devuelve solo las columnas de §4.
  Valida los encabezados. Librería: **`openspout/openspout`** (lectura por streaming, poca
  memoria). Elegir una versión que acepte **PHP 8.1** (producción es 8.1.34, CLAUDE.md §1) y
  confirmarla con `composer why-not php 8.1` antes de instalar. Alternativa más pesada:
  `phpoffice/phpspreadsheet`.
- `PersonnelDirectory`: No. de empleado → (localidad, área, puesto) con las reglas de §6.
- `CourseReportBuilder`: arma `cubo`, `inicio_mes`, `encuesta` y `catalogo` (§7) y la lista de
  avisos.
- `SurveyAnswerClassifier`: texto libre → temas (§6.4).

### 5.3 Flujo y pantallas (super admin y admin; ver respuestas en §10)
1. En `course-groups/{id}` (show): nueva sección **"Reportes"** con la lista de cortes (fecha,
   % completado, promedio, satisfacción) y el botón **"Nuevo corte"**.
2. Formulario: los 3 archivos `.xlsx`, fecha de corte (sugerida desde el nombre del archivo) y
   título (por defecto: nombre de la carpeta). Con un `FormRequest`: `mimes:xlsx`, tamaño máximo
   en `config/mera.php` y autorización.
3. Al enviar: guardar en `storage/app/tmp/report-uploads/<uuid>/` (privado) → construir → guardar
   `course_reports` → **borrar los Excel siempre** (`finally`). No se guarda ningún Excel ni
   ningún dato personal: solo agregados por localidad/área/puesto y banderas 0/1 por respuesta.
4. Al terminar: redirigir al reporte con los avisos (p. ej. "16 inscritos de unidades
   excluidas", "N respuestas sin clasificar").
5. Vista del reporte `course-groups/{id}/reportes/{report}`: el diseño de la plantilla dentro del
   layout del panel. Botones: **Descargar HTML** (archivo autocontenido igual al base, para
   compartir por correo) y **Borrar corte** (con `data-confirm`).
6. Usar el loader y el bloqueo de botones al enviar (pendiente de CLAUDE.md §6): procesar los
   3 archivos tarda varios segundos.

### 5.4 Frontend
- Pasar la lógica de la plantilla (último `<script>` del HTML base) a
  `resources/js/modules/course-report.js` con `import * as d3 from 'd3'` (**d3@7.9.0** fijo en
  `package.json`). El `d3` de `public/bower_components` es otra versión: no usarlo.
- Datos: `<script type="application/json" id="report-data">` con `json_encode` y
  `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT`. **Nunca** `{{ }}` dentro de `<script>`
  (CLAUDE.md §3).
- Estilos: pasar las clases `.lx-*` de la plantilla a `resources/css/app.css` con prefijo `mera-`
  o dentro de un contenedor `.mera-report`. Los colores ya son de la marca (§8): `#55B882` ≈
  verde, `#93C01F` acento, `#024D25` MERA green, `#81CFF4` light blue. Montserrat ya está cargada.
- Build **solo en local** (regla 7) y ZIP de `public/build` para el despliegue (§5).

## 6. Reglas de cálculo (idénticas al script de referencia)

### 6.1 Localidad (`Unidad de negocio` del reporte de personal)
| Valor en el Excel | Localidad |
|---|---|
| `Panama` / `Bajio` / `Ciudad Juarez` / `Torreon` / `Cancun Radio` | `Panamá` / `Bajío` / `Ciudad Juárez` / `Torreón` / `Cancún Radio` |
| `Mazatlán ` (con espacio) | `Mazatlán` (se recortan espacios siempre) |
| `Cancún T2 Abt2` | `Cancún T2` |
| `MERA Bogota`, `MERA Medellin`, `MDE GUACAMOLE GRILL` | `Colombia` |
| Vacío, con `LATAM`, `BOG` o `MED` en departamento o puesto | `Colombia` |
| Vacío (otros casos) | **Se excluye** |
| `Cancun C4` | **Se excluye** (personal de Presidencia; tampoco está en el reporte base) |
| Cualquier otro | Igual, recortado |

### 6.2 Área (`Departamento`, sin `*`)
| Departamento | Área |
|---|---|
| `Piso`, `Restaurantes` | Piso / Restaurantes |
| `Cocina` | Cocina |
| `Bar` | Bar |
| `Almacen` | Almacén |
| `Comisariato Foodcourt`, `Comisariato Food Court PB` | Comisariato |
| `Mantenimiento`, `Limpieza` | Mantenimiento y limpieza |
| Todo lo demás (incluidos `Limpieza FC`, `Limpieza Sat.`, `Comedor`) | Corporativo y administrativo / otros |

### 6.3 Inscripciones
- Inscrito que no está en el reporte de personal → se omite y se avisa.
- `completados`: `Estatus = completed`. `sin_iniciar`: no completado y `Progreso % = 0`. Los
  demás están en curso (no se muestran por separado).
- `calif_n`, `calif_suma`, `b80`, `b70`, `b60`, `b50`: solo completados con calificación.
- `inicio_mes`: completados, por mes de `Iniciado`.

### 6.4 Comentarios abiertos (temas)
- `v_respondio` = 1 si la respuesta a "¿qué te gustó?" tiene contenido real (no vacía, no `.`, no
  "nada"). `v_todo` = 1 si dice "todo" / "todo bien" / "todo en general".
- `v01…v09` y `m01…m12`: temas del catálogo (`docs/reportes/catalogo_higiene.csv`). Una respuesta
  puede tener varios temas. "Qué piden mejorar" solo cuenta temas de **contenido**, no "nada".
- Para Higiene, la clasificación ya hecha está en `codigos_texto.csv` y **tiene prioridad**:
  importarla (por No. de empleado) para que los números coincidan con el reporte base.
- Respuestas nuevas: clasificador por palabras clave del catálogo + lista de **"Pendientes de
  clasificar"** en el reporte, donde un admin puede corregir los temas. ⚠ Decisión abierta (§10).

### 6.5 Umbrales de la vista (ya están en el JS de la plantilla; no cambiarlos)
Podio: localidades con ≥20 personas · reprobar = calificación <80 · "va bien" <35 % bajo 80 ·
"necesita apoyo" ≥45 % · puestos con ≥15 evaluados · satisfacción por localidad con ≥30
respuestas, sin "Ya no activo".

## 7. Formato de los datos (contrato con el JS; no cambiar nombres)

`window.__DATA` de la plantilla = 4 CSV con estos encabezados:
```
cubo:       localidad,area,puesto,asignados,completados,sin_iniciar,calif_n,calif_suma,b80,b70,b60,b50
inicio_mes: localidad,area,mes,n
encuesta:   localidad,area,f_utilidad,f_riesgos,f_ejemplos,f_herramientas,f_capaz,f_facil,f_recursos,f_recomienda,m01..m12,v01..v09,v_todo,v_respondio
catalogo:   codigo,tipo,etiqueta
```
En Laravel se pueden guardar como arreglos JSON en lugar de CSV. El JS se adapta en un solo
punto (`dash.data(id)`) y el resto de la lógica no cambia.

## 8. Pruebas (sqlite `:memory:`, regla 3)
- `tests/Feature/CourseReportTest.php`: subir 3 Excel **sintéticos** generados en el test con el
  *writer* de la librería (10–20 filas, datos inventados) y verificar:
  - Totales del cubo, rangos de calificación e `inicio_mes`.
  - Cada regla de §6.1 y §6.2 (una fila por caso).
  - Persona `deleted` → `Ya no activo`. Inscrito sin personal → aviso.
  - Encabezado faltante → error de validación con el nombre de la columna.
  - Los Excel subidos **ya no existen** después de procesar, con éxito y con error.
  - Autorización: un usuario sin rol de panel recibe 403.
- Unitarias para `SurveyAnswerClassifier` y para la normalización de encabezados.

## 9. Criterio de aceptación (con los Excel reales del 2026-10-14, solo en local)
1. Correr el script de referencia y guardar su salida:
   ```bash
   cd storage/app/reportes/referencia
   python generar_reporte.py --encuesta "<…responses.xlsx>" --inscripciones "<…inscripciones….xlsx>" \
     --personal "<Reporte de personal 2026-10-14.xlsx>" --titulo "Curso: Introducción a la Higiene 2025-2026" \
     --salida esperado.html
   ```
2. Crear el corte en el panel con los mismos 3 archivos (carpeta del curso de Higiene).
3. Comparar los datos fila por fila contra `esperado.html` (puede ser un comando artisan de
   verificación de solo lectura). Deben coincidir **exactamente**:

| Indicador | Valor esperado |
|---|---|
| Asignados | 3,301 (el reporte base decía 3,300: allí faltaba 1 "Gerente De Operaciones" de Corporativo sin iniciar; se acepta la diferencia) |
| Completaron | 2,623 (79 %) |
| Todavía no empiezan | 563 |
| Calificación promedio | 77.5 (61 % sacó 80 o más) |
| Satisfacción | 76 % de 1,634 respuestas |
| Filas `cubo` / `inicio_mes` / `encuesta` | 592 / 444 / 1,634 |
| Avisos | 16 inscritos de unidades excluidas o sin unidad |

4. Revisión visual en navegador (escritorio y móvil): filtros por localidad y área, clic en el
   ranking, tooltips, modo oscuro y "Descargar HTML".

## 10. Decisiones abiertas (preguntar al usuario ANTES de programar)
1. ¿Quién ve los reportes: super admin y admin, o solo super admin?
2. Comentarios abiertos de cortes nuevos: ¿clasificación por palabras clave con corrección
   manual en pantalla, o solo carga de un CSV de temas ya clasificado?
3. ¿Se conservan todos los cortes (historial) o solo el último por carpeta?
4. ¿El reporte se queda solo en el panel, o también se necesita un link para compartir sin
   sesión? (Por defecto: solo panel + "Descargar HTML". Una página pública NO: son datos internos.)
5. ¿La configuración de la encuesta y del catálogo se edita en pantalla o basta con un seeder por
   curso en esta fase?

### Respuestas del usuario (2026-10-14)
1. **Acceso:** **super admin y admin** (roles del panel).
2. **Comentarios abiertos:** clasificación por **palabras clave + corrección manual en pantalla** (lista "Pendientes de clasificar").
3. **Historial:** se conservan **todos los cortes** de cada carpeta.
4. **Compartir:** además del panel y "Descargar HTML", **link para compartir sin sesión**. Diseño: token aleatorio largo por corte, apagado por defecto, con botones "Activar link", "Regenerar" (invalida el anterior) y "Desactivar"; página con `noindex`, solo agregados (sin datos personales) y sin acciones de edición.
5. **Configuración de encuesta y catálogo:** **seeder por curso** en esta fase (Higiene por defecto).
6. **Nuevo (no estaba contemplado): actualizar un corte.** Editar título y fecha, y **volver a subir los 3 Excel** para recalcular el mismo corte (p. ej. si se subió un archivo equivocado). Se registra en el historial de actividad.
7. **API de la plataforma de capacitación:** queda fuera de esta fase; **mencionarlo y preguntarlo en el correo** que se redacte al final de este desarrollo (CLAUDE.md §6, "Para el correo").

## 11. Orden sugerido de trabajo

### Avance (rama `qa`, 2026-10-14)
- ✅ **Paso 1:** decisiones de §10 resueltas con el usuario (ver "Respuestas del usuario").
- ✅ **Paso 2:** `openspout/openspout` **v4.25.0** (la última que acepta PHP 8.1; desde 4.26 exige 8.2) y `composer.json` fijado a `config.platform.php = 8.1.34`. Lector `App\Services\Reports\ReportWorkbookReader` (hoja 1 en streaming, encabezados normalizados como el script, primera coincidencia, celda vacía = `null`) con 6 pruebas.
- ✅ **Paso 3:** `PersonnelDirectory`, `SurveyAnswerClassifier` y `CourseReportBuilder`, más el comando de solo lectura `php artisan mera:report-verify <personal> <inscripciones> <encuesta> --expected=<esperado.html>`. Con los Excel reales del 2026-10-14 **coincide exactamente** con `generar_reporte.py` en los 4 conjuntos, fila por fila (592 / 444 / 1,634 / 22), y los indicadores de §9 (3,301 · 2,623 = 79 % · 563 · 77.5 con 61 % ≥ 80 · 76 % de 1,634 · aviso de 16 excluidos). Tarda ~22 s en local. 6 pruebas con Excel sintéticos.
  - Reglas generales en `config/mera.php → reports`; encuesta de Higiene (preguntas favorables, catálogo con palabras clave y archivo de clasificación previa) en `config/report_surveys.php`.
  - **Satisfacción** = % de personas con las **8** respuestas favorables (así la calcula el JS de la plantilla, `k-rec`).
  - La clasificación previa de Higiene se copió a `storage/app/reportes/codigos/higiene.csv` (privado; trae números de empleado). **En producción hay que subirla a mano** a esa misma ruta.
- ⏳ Paso 4 en adelante: migración, controlador, vistas, actualización de cortes, link para compartir, JS/CSS.

1. Leer este documento, `CLAUDE.md` y el script de referencia. Responder §10 con el usuario.
2. Instalar la librería de Excel (versión compatible con PHP 8.1) y escribir el lector con pruebas.
3. `CourseReportBuilder` + pruebas de §8 hasta igualar el script de referencia (§9).
4. Migración (backup antes) + controlador, `FormRequest`, policy, rutas y vistas.
5. JS/CSS del reporte, build local y revisión visual.
6. Agregar a `CLAUDE.md` (§3 Arquitectura, §9 Bitácora y §5 despliegue) lo construido, con el
   permiso del usuario. Sin commit ni push hasta que el usuario lo autorice (regla 1).
