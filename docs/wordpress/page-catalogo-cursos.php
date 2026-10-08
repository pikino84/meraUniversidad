<?php
/*
Template Name: Catálogo de Cursos Mera
*/

/**
 * Catálogo de cursos MERA University (meracorporation.com/cursos-university/).
 *
 * CÓMO SUSTITUIR: ver docs/wordpress/README.md. Resumen: reemplazar el CONTENIDO del archivo de
 * plantilla actual del tema (el que tiene "Template Name: Catálogo de Cursos Mera") por este.
 * Mantener el mismo nombre de archivo y la misma línea "Template Name" para que la página
 * siga asignada a esta plantilla.
 *
 * Cambios respecto a la versión anterior:
 *  - Se renderiza en el servidor (PHP): Google indexa los cursos, funciona sin JavaScript
 *    y no depende de CORS.
 *  - Paginación (?pagina=N) y buscador (?q=texto); 12 cursos por página.
 *  - Todo se escapa con esc_html/esc_url/esc_attr (antes, HTML en el nombre o descripción
 *    de un curso se ejecutaba en meracorporation.com → XSS).
 *  - URLs sin doble slash ("…com//storage/…").
 *  - Caché de 10 min (transient) + última copia buena si la API falla.
 *  - Imagen de respaldo si una portada no existe.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('mera_cursos_catalogo')) {
    /**
     * Lista completa de cursos desde la API (cacheada).
     *
     * @return array{0: array<int, array<string, mixed>>|null, 1: bool} [cursos|null, ¿viene de la copia de respaldo?]
     */
    function mera_cursos_catalogo()
    {
        $cache_key = 'mera_cursos_catalogo_v1';
        $cached = get_transient($cache_key);

        if (is_array($cached)) {
            return [$cached, false];
        }

        $response = wp_remote_get('https://cursos.meracorporation.com/api/courses', [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json'],
        ]);

        $courses = null;

        if (! is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $decoded = json_decode(wp_remote_retrieve_body($response), true);
            $courses = is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : null;
        }

        if ($courses === null) {
            // La API no respondió: usar la última copia buena (si existe) sin cachearla como nueva.
            $backup = get_option($cache_key.'_backup');

            return [is_array($backup) ? $backup : null, true];
        }

        set_transient($cache_key, $courses, 10 * MINUTE_IN_SECONDS);
        update_option($cache_key.'_backup', $courses, false);

        return [$courses, false];
    }

    /**
     * URL absoluta sin doble slash. Acepta tanto el formato nuevo (course_url / cover_image_url)
     * como el antiguo ("/storage/…" o "storage/…").
     */
    function mera_cursos_url($absolute, $relative)
    {
        if (is_string($absolute) && preg_match('#^https?://#i', $absolute)) {
            return $absolute;
        }

        if (! is_string($relative) || $relative === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $relative)) {
            return $relative;
        }

        return 'https://cursos.meracorporation.com/'.ltrim($relative, '/');
    }

    /** Texto en minúsculas y sin acentos, para buscar sin importar tildes. */
    function mera_cursos_normalizar($text)
    {
        return strtolower(remove_accents((string) $text));
    }
}

$mera_per_page = 12;
$mera_query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
$mera_page = isset($_GET['pagina']) ? max(1, absint($_GET['pagina'])) : 1;

[$mera_courses, $mera_from_backup] = mera_cursos_catalogo();

if (is_array($mera_courses)) {
    // Más recientes primero. La API antigua no trae "id" pero ya viene del más viejo al más nuevo.
    if (isset($mera_courses[0]['id'])) {
        usort($mera_courses, function ($a, $b) {
            return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
        });
    } else {
        $mera_courses = array_reverse($mera_courses);
    }

    if ($mera_query !== '') {
        $needle = mera_cursos_normalizar($mera_query);
        $mera_courses = array_values(array_filter($mera_courses, function ($c) use ($needle) {
            return strpos(mera_cursos_normalizar(($c['name'] ?? '').' '.($c['description'] ?? '').' '.($c['category'] ?? '')), $needle) !== false;
        }));
    }
}

$mera_total = is_array($mera_courses) ? count($mera_courses) : 0;
$mera_last_page = max(1, (int) ceil($mera_total / $mera_per_page));
$mera_page = min($mera_page, $mera_last_page);
$mera_items = is_array($mera_courses) ? array_slice($mera_courses, ($mera_page - 1) * $mera_per_page, $mera_per_page) : [];
$mera_base_url = get_permalink();
$mera_fallback = 'https://cursos.meracorporation.com/images/logo_university.svg';

$mera_page_url = function ($page) use ($mera_base_url, $mera_query) {
    $args = [];
    if ($page > 1) {
        $args['pagina'] = $page;
    }
    if ($mera_query !== '') {
        $args['q'] = $mera_query;
    }

    return add_query_arg($args, $mera_base_url).'#catalogo';
};

get_header(); ?>

<div id="cursos_footer" class="mera-cat-wrap">
  <section id="catalogo" class="mera-cat" aria-labelledby="mera-cat-title">

    <header class="mera-cat__header">
      <h1 id="mera-cat-title" class="mera-cat__title">Catálogo de Cursos</h1>

      <form class="mera-cat__search" role="search" method="get" action="<?php echo esc_url($mera_base_url); ?>#catalogo">
        <label class="mera-cat__sr" for="mera-cat-q">Buscar curso</label>
        <input id="mera-cat-q" type="search" name="q" value="<?php echo esc_attr($mera_query); ?>" placeholder="Buscar curso…" maxlength="100">
        <button type="submit">Buscar</button>
        <?php if ($mera_query !== '') : ?>
          <a class="mera-cat__clear" href="<?php echo esc_url($mera_base_url); ?>#catalogo">Limpiar</a>
        <?php endif; ?>
      </form>
    </header>

    <?php if ($mera_courses === null) : ?>

      <p class="mera-cat__status">No pudimos cargar los cursos en este momento. Intenta de nuevo más tarde.</p>

    <?php elseif ($mera_total === 0) : ?>

      <p class="mera-cat__status">
        <?php echo $mera_query !== ''
            ? 'No hay cursos que coincidan con “'.esc_html($mera_query).'”.'
            : 'No hay cursos disponibles por el momento.'; ?>
      </p>

    <?php else : ?>

      <p class="mera-cat__status" aria-live="polite">
        <?php echo esc_html($mera_total.($mera_total === 1 ? ' curso' : ' cursos').' · página '.$mera_page.' de '.$mera_last_page); ?>
      </p>

      <div class="mera-cat__grid">
        <?php foreach ($mera_items as $c) :
            $name = (string) ($c['name'] ?? '');
            $desc = (string) ($c['description'] ?? '');
            $desc = function_exists('mb_strimwidth') ? mb_strimwidth($desc, 0, 170, '…', 'UTF-8') : $desc;
            $cover = mera_cursos_url($c['cover_image_url'] ?? null, $c['cover_image'] ?? null);
            $link = mera_cursos_url($c['course_url'] ?? null, $c['url'] ?? null);
            ?>
          <article class="mera-cat__card">
            <img src="<?php echo esc_url($cover ?: $mera_fallback); ?>" alt="" loading="lazy" decoding="async"
                 onerror="this.onerror=null;this.src='<?php echo esc_url($mera_fallback); ?>';this.classList.add('is-fallback');">
            <div class="mera-cat__body">
              <?php if (! empty($c['category'])) : ?>
                <span class="mera-cat__tag"><?php echo esc_html($c['category']); ?></span>
              <?php endif; ?>
              <h2 class="mera-cat__name"><?php echo esc_html($name); ?></h2>
              <p class="mera-cat__desc"><?php echo esc_html($desc); ?></p>
              <?php if ($link) : ?>
                <a class="mera-cat__link" href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener"
                   aria-label="<?php echo esc_attr('Ver curso '.$name.' (se abre en una pestaña nueva)'); ?>">Ver curso</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($mera_last_page > 1) :
          $pages = array_unique(array_filter([1, $mera_page - 1, $mera_page, $mera_page + 1, $mera_last_page], function ($p) use ($mera_last_page) {
              return $p >= 1 && $p <= $mera_last_page;
          }));
          sort($pages);
          ?>
        <nav class="mera-cat__pager" aria-label="Paginación de cursos">
          <?php if ($mera_page > 1) : ?>
            <a href="<?php echo esc_url($mera_page_url($mera_page - 1)); ?>" rel="prev" aria-label="Página anterior">‹</a>
          <?php endif; ?>

          <?php $prev = 0; foreach ($pages as $p) : ?>
            <?php if ($prev && $p - $prev > 1) : ?><span aria-hidden="true">…</span><?php endif; ?>
            <?php if ($p === $mera_page) : ?>
              <span class="is-current" aria-current="page"><?php echo (int) $p; ?></span>
            <?php else : ?>
              <a href="<?php echo esc_url($mera_page_url($p)); ?>" aria-label="Página <?php echo (int) $p; ?>"><?php echo (int) $p; ?></a>
            <?php endif; ?>
          <?php $prev = $p; endforeach; ?>

          <?php if ($mera_page < $mera_last_page) : ?>
            <a href="<?php echo esc_url($mera_page_url($mera_page + 1)); ?>" rel="next" aria-label="Página siguiente">›</a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>

    <?php endif; ?>
  </section>
</div>

<style>
  /* Línea de marca MERA (media kit). La tipografía se hereda del sitio. */
  .mera-cat { --g:#024D25; --g2:#55882B; --lime:#93C01F; --sky:#81CFF4; --aqua:#8CBCC4;
    max-width:1200px; margin:0 auto; padding:190px 20px 72px; scroll-margin-top:130px; } /* 190px: espacio del header fijo de Divi (igual que antes) */
  .mera-cat__header { display:flex; flex-direction:column; align-items:center; gap:22px; margin-bottom:12px; text-align:center; }
  .mera-cat__title { margin:0; padding:0; color:var(--g); font-weight:900; text-transform:uppercase; font-size:clamp(1.9rem,4vw,3rem); line-height:1.1; }
  .mera-cat__search { display:flex; flex-wrap:wrap; gap:8px; justify-content:center; width:100%; max-width:560px; }
  .mera-cat__search input { flex:1 1 240px; min-width:0; padding:12px 20px; border:1px solid var(--aqua); border-radius:999px; font:inherit; font-size:15px; }
  .mera-cat__search input:focus { outline:none; border-color:var(--sky); box-shadow:0 0 0 4px rgba(129,207,244,.3); }
  .mera-cat__search button { padding:12px 26px; border:0; border-radius:999px; background:var(--g2); color:#fff; font:inherit; font-weight:700; cursor:pointer; }
  .mera-cat__search button:hover, .mera-cat__search button:focus-visible { background:var(--g); }
  .mera-cat__clear { align-self:center; color:var(--g2); font-weight:600; }
  .mera-cat__status { margin:8px 0 22px; color:#6b7280; font-size:.95rem; text-align:center; }
  .mera-cat__grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(min(100%,300px),1fr)); gap:24px; }
  .mera-cat__card { display:flex; flex-direction:column; overflow:hidden; border:1px solid var(--aqua); border-radius:14px; background:#fff;
    box-shadow:0 4px 18px rgba(2,77,37,.06); transition:transform .2s, box-shadow .2s; }
  .mera-cat__card:hover { transform:translateY(-4px); border-color:var(--sky); box-shadow:0 12px 30px rgba(2,77,37,.14); }
  .mera-cat__card img { display:block; width:100%; aspect-ratio:16/9; object-fit:cover; background:#f2f7f4; }
  .mera-cat__card img.is-fallback { object-fit:contain; padding:28px; }
  .mera-cat__body { display:flex; flex:1; flex-direction:column; gap:10px; padding:18px; }
  .mera-cat__tag { align-self:flex-start; padding:3px 10px; border-radius:999px; background:rgba(147,192,31,.18); color:var(--g); font-size:.75rem; font-weight:700; }
  .mera-cat__name { margin:0; padding:0; color:var(--g); font-size:1.15rem; font-weight:700; line-height:1.3; }
  .mera-cat__desc { flex:1; margin:0; padding:0; color:#4b5563; font-size:.93rem; line-height:1.55; }
  .mera-cat__link { display:block; margin-top:6px; padding:11px 16px; border-radius:6px; background:var(--g2); color:#fff !important;
    font-weight:600; text-align:center; text-decoration:none; transition:background .2s; }
  .mera-cat__link:hover, .mera-cat__link:focus-visible { background:var(--lime); }
  .mera-cat__pager { display:flex; flex-wrap:wrap; gap:8px; justify-content:center; align-items:center; margin-top:40px; }
  .mera-cat__pager a, .mera-cat__pager span { display:inline-flex; align-items:center; justify-content:center; min-width:44px; height:44px; padding:0 14px;
    border-radius:999px; font-weight:700; text-decoration:none; }
  .mera-cat__pager a { border:1px solid var(--aqua); background:#fff; color:var(--g); }
  .mera-cat__pager a:hover, .mera-cat__pager a:focus-visible { border-color:var(--g2); background:var(--g2); color:#fff; }
  .mera-cat__pager .is-current { background:var(--g); color:#fff; }
  .mera-cat__sr { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap; }
  @media (max-width:600px) { .mera-cat { padding:150px 16px 56px; } }
</style>

<?php get_footer(); ?>
