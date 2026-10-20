# Pruebas en QA: versiones, seguimiento anual y URLs en español (2026-10-20)

> Guion para que un agente con navegador (Claude Desktop / Claude in Chrome) pruebe QA con la sesión
> del usuario. Cada paso dice qué hacer y qué debe pasar. Al final, entregar la tabla de resultados.

## Contexto
- **QA:** `https://university.meracorporation.com`, rama `qa` en **`d3f3a65`**, BD `merabl_cursos_qa`
  (copia de producción del 2026-10-15). Los archivos de los módulos **no** están en QA: "Ver módulo" y
  las portadas dan 404 (esperado, no reportarlo como error).
- **Producción:** `https://cursos.meracorporation.com`. **No hacer nada ahí** (ni iniciar sesión ni
  probar); todavía no tiene estos cambios.
- Ya aplicado en QA: 7 migraciones y `mera:versions-from-languages` (11 versiones). **Antes de probar**, el build `d3f3a65` debe estar instalado: `https://university.meracorporation.com/build/manifest.json` debe incluir `app-DOUg8CUY.css`.

## Reglas para el agente
1. Solo `university.meracorporation.com`.
2. No crear, editar ni borrar usuarios, módulos ni carpetas reales. Sí se permite: importar el catálogo,
   crear y borrar **cortes del seguimiento**, subir encuestas, crear y desactivar links para compartir.
3. Si se publica algo para probar, apagarlo al final (o usar "Apagar todo lo compartido").
4. Los Excel traen **nombres y números de empleado**: no copiarlos en el reporte, no pegarlos en chats
   ni subirlos a otro sitio que no sea QA.
5. Si una página da 500 o 504, anotar la URL, la hora y lo que se hizo, y seguir con el siguiente paso.

## Archivos (en el equipo del usuario)
| Para | Archivo |
|---|---|
| Catálogo | `Downloads/Catalogo_MERA_v12 (1).xlsx` (o `storage/app/reportes/referencia/Catalogo_MERA_v12.xlsx`) |
| Progreso | `Downloads/Reporte de progreso de capacitaciones 2026-10-12.xlsx` |
| Personal | `Downloads/Reporte de personal 2026-10-14.xlsx` |
| Encuesta de Higiene | `Downloads/2026-10-14 Encuesta de satisfacción - Introducción a la Higiene y Procedimientos 2026 responses.xlsx` |
| Otras encuestas | `Downloads/2026-10-19 Encuesta de Satisfacción - … responses.xlsx` (Huracán, Despega, Orientación a Resultados, Tus áreas, Salas VIP) |

## Pasos

### 1. Acceso y URLs en español
- Abrir `/login` → debe terminar en **`/iniciar-sesion`**. Iniciar sesión → **`/panel`**.
- `/dashboard` → `/panel`; `/courses` → `/modulos`; `/course-groups` → `/carpetas`.
- Abajo, fijo: cintillo **"Entorno de pruebas"** con el botón **"Apagar todo lo compartido (N activos)"**.
- Los estilos se ven bien (si se ven rotos o sin formato, el build no se actualizó: detenerse y avisar).

### 2. Carpetas por año y versión
- `/carpetas` → abrir "NRM-SEG-002-EL-26 | Fundamentos Seguridad 2026": año **2026** con las versiones
  **México** y **EUA** como tarjetas.
- Entrar a una versión: pestañas **Módulos / Link para compartir / Reportes**; ruta de navegación arriba.
- "NRM-SEG-001-EL-25 | Fundamentos Seguridad 2025" debe estar en el año **2025**.
- "NRM-HIG-005-EL-26 | Manipulación de Alimentos" en **2026 · Latam**.
- `/versiones` (super admin): México, Latam y EUA.
- Página pública `/proceso-de-manipulacion-de-alimentos`: abre y lista **5 módulos** (los enlaces a los
  módulos darán 404 en QA: esperado).
- **Borrar:** en cualquier pantalla con botón de eliminar, pulsarlo y **cancelar**. Debe abrir la
  confirmación (en producción hoy no abre). No confirmar.

### 3. Catálogo de cursos
- `/catalogo-cursos` → subir el Excel del catálogo. Debe decir cuántos cursos importó (≈72) y avisos si
  faltan datos. La lista va **de 10 en 10**, con filtros por año, ruta, alcance y búsqueda.
- Los cursos de 2025 aparecen con **"No cuenta"**.

### 4. Corte del seguimiento anual (medir tiempo)
- `/seguimiento/nuevo` → subir **Progreso** y **Personal**, fecha de corte **12/10/2026**.
- **Medir el tiempo** desde "Crear" hasta que aparece el tablero. En local tarda ~70 s.
  Si sale **504** o se queda cargando más de 5 minutos, anotarlo: es el dato más importante de esta prueba.
- Cifras esperadas en **Resumen** (sin filtros, año 2026):

| Indicador | Esperado |
|---|---|
| Personas | 3,300 |
| Asignaciones | 58,405 |
| Realizado | 69.9 % |
| Aprobado | 56.5 % |
| Calificación promedio | 86.7 |

- La URL queda como `/seguimiento/2026-10-12`.
- Recorrer las 6 pestañas: Resumen, Cursos, Localidades, Áreas y puestos, Evaluación, Opiniones. Sin
  errores; en móvil (≈375 px) las pestañas se desplazan de lado, **sin barra vertical**.
- Filtros: elegir una localidad → se aplica solo y aparece la etiqueta del filtro; quitarla.
- "Colaboradores": buscar a cualquier persona, abrir su ficha, "Exportar CSV" descarga un archivo.
- "Editar": cambiar el nombre del corte y guardar (luego dejarlo como estaba).

### 5. Encuestas y temas
- Pestaña **Opiniones** → subir la encuesta de Higiene. Debe proponer el curso "Introducción a la
  Higiene 2026" (NRM-HIG-002-EL-26). Satisfacción ≈ **76 %**.
- Subir 2 encuestas más (p. ej. Huracán y Despega). Elegir cada curso en Opiniones: tarjetas
  **"Lo que más valoran"** y **"Qué piden mejorar"** con barras.
- "Clasificar comentarios": pestañas Por revisar / Sin tema / Todos; marcar un tema en un comentario,
  Guardar → mensaje "N comentarios revisados".

### 6. Compartir y descargar
- "Links para compartir": crear uno de **toda la empresa** y otro de **una localidad**.
- Abrir cada link en una ventana **de incógnito** (sin sesión): se ve el tablero **sin nombres** de
  personas y sin comentarios abiertos (solo totales por tema). El de localidad no deja cambiar la localidad.
- Desactivar un link → al recargarlo en incógnito da **404**.
- "Descargar HTML": baja un archivo; abrirlo desde el disco → pestañas funcionan, sin nombres.

### 7. Comparación con otro corte (opcional)
- Crear un segundo corte con los **mismos Excel** y fecha **15/09/2026**. En el corte del 12/10 debe
  aparecer "Cambios contra el corte del 15/09/2026" (con los mismos datos, los cambios son 0).
- **Borrar** el corte del 15/09 (confirmación → Eliminar).

### 8. Conexión con la versión del curso
- `/carpetas` → "NRM-HIG-002-EL-26 | Introducción a la Higiene 2026" → versión 2026 · EUA → pestaña
  **Reportes**: tarjeta **"En el seguimiento anual"** con las cifras del curso y link al tablero filtrado.

### 9. Limpieza
- Pulsar **"Apagar todo lo compartido"** en el cintillo → el contador queda en 0.
- El corte del 12/10 **se queda** (para que el usuario lo revise).
- El usuario revisa por terminal que `storage/app/tmp/report-uploads` quede vacío.

## Resultado (entregar al usuario)
| Paso | Resultado (OK / Falla) | Notas (URL, mensaje, captura) |
|---|---|---|
| 1. URLs y cintillo | | |
| 2. Carpetas y versiones | | |
| 3. Catálogo | | |
| 4. Corte: **tiempo = ___ s** | | cifras |
| 5. Encuestas y temas | | |
| 6. Compartir y descargar | | |
| 7. Comparación | | |
| 8. Versión ↔ seguimiento | | |
| 9. Limpieza | | |
