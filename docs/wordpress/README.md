# Catálogo de cursos en WordPress (meracorporation.com/cursos-university/)

La página usa una **plantilla de página PHP** del tema hijo **Divi-child**, llamada "Catálogo de Cursos Mera". No es un módulo de Divi.

## Archivos

| Archivo | Qué es |
|---|---|
| `page-catalogo-cursos.php` | **Versión recomendada.** Se renderiza en PHP (indexable, sin JS, sin CORS), con paginación `?pagina=N`, buscador `?q=` y caché de 10 minutos |
| `cursos-university.html` | Alternativa 100 % JavaScript (para un módulo "Código" de Divi). Solo si no se puede editar el tema |

## Cómo sustituir (versión recomendada)

1. **Backup:** en cPanel → Administrador de archivos, ir a `public_html/wp-content/themes/Divi-child/` y ubicar el archivo que contiene `Template Name: Catálogo de Cursos Mera`. Si no se sabe cuál es, buscar el texto con "Buscar" en el Administrador de archivos. **Descargar una copia** antes de tocarlo (o duplicarlo como `nombre.php.bak`).
2. **Reemplazar el contenido** de ese archivo por el de `page-catalogo-cursos.php`. **Mantener el mismo nombre de archivo** y la línea `Template Name: Catálogo de Cursos Mera`: WordPress guarda la plantilla asignada por nombre de archivo, así que la página la sigue usando sin hacer nada más.
   - Alternativa: Apariencia → Editor de archivos de tema → Divi-child → el archivo de la plantilla.
3. **Limpiar caché:** Divi → Opciones del tema → Constructor → Avanzado → "Static CSS File Generation" → *Clear*. Si hay plugin de caché o LiteSpeed Cache, purgarlo también.
4. **Verificar:**
   - `https://meracorporation.com/cursos-university/` → 12 cursos y paginación al final.
   - `?pagina=2` → la siguiente página.
   - Buscar "huracán" → filtra.
   - "Ver curso" abre `https://cursos.meracorporation.com/storage/...` **sin** `//`.
5. **Reversa:** volver a subir la copia del paso 1.

## Notas

- Funciona con la API actual y con la nueva (lee `course_url`/`cover_image_url` si existen; si no, arma la URL sin doble slash). No importa el orden del despliegue.
- El CSS viejo del catálogo (`.cursos-container`, `.curso-card`, … en `Divi-child/style.css`, líneas ~5342-5426) deja de usarse. Se puede borrar después; mientras tanto no estorba. Se conserva `#cursos_footer`, que da el degradado de fondo.
- La tipografía se hereda del sitio (hoy Open Sans) para que combine con el header. Si el sitio adopta Montserrat (media kit), la página la toma sola.
- Caché: los cursos nuevos aparecen en un máximo de 10 minutos. Para forzarlo, borrar el transient `mera_cursos_catalogo_v1` (p. ej. con el plugin "Transients Manager") o esperar.
- Requiere PHP 7.4+ en WordPress (el sitio usa 8.1).
