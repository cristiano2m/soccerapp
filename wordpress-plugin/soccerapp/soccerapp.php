<?php
/**
 * Plugin Name: SoccerAPP
 * Plugin URI:  http://localhost/torneo/
 * Description: Muestra datos de torneos de fútbol desde SoccerAPP en tu sitio WordPress. Usa shortcodes para mostrar posiciones, goleadores, calendario, resultados y más.
 * Version:     1.0.0
 * Author:      SoccerAPP
 * Text Domain: soccerapp
 */

if (!defined('ABSPATH')) exit;

// ═══════════════════════════════════════════════════════════════════════════════
// CONSTANTS & CONFIG
// ═══════════════════════════════════════════════════════════════════════════════

define('SOCCERAPP_VERSION', '1.0.0');
define('SOCCERAPP_CACHE_TTL', 300); // 5 minutos

function soccerapp_api_url(): string
{
    return rtrim(get_option('soccerapp_api_url', ''), '/');
}

function soccerapp_api_token(): string
{
    return get_option('soccerapp_api_token', '');
}

// ═══════════════════════════════════════════════════════════════════════════════
// API CLIENT
// ═══════════════════════════════════════════════════════════════════════════════

function soccerapp_fetch(string $endpoint, bool $cache = true): mixed
{
    $url       = soccerapp_api_url() . $endpoint;
    $token     = soccerapp_api_token();
    $cache_key = 'soccerapp_' . md5($url);

    if ($cache) {
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;
    }

    $response = wp_remote_get($url, [
        'timeout' => 10,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['success']) || !$data['success']) {
        return null;
    }

    $result = $data['data'];
    if ($cache) {
        set_transient($cache_key, $result, SOCCERAPP_CACHE_TTL);
    }
    return $result;
}

// ═══════════════════════════════════════════════════════════════════════════════
// CSS EMBEBIDO
// ═══════════════════════════════════════════════════════════════════════════════

function soccerapp_enqueue_styles(): void
{
    wp_add_inline_style('wp-block-library', soccerapp_get_css());
}
add_action('wp_enqueue_scripts', 'soccerapp_enqueue_styles');

function soccerapp_get_css(): string
{
    return '
.sa-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #1a1a2e; }
.sa-title { font-size: 1.1rem; font-weight: 700; padding: 12px 16px; background: #1a1a2e; color: #f5c518; border-radius: 8px 8px 0 0; display: flex; align-items: center; gap: 8px; margin: 0; }
.sa-title .sa-icon { font-size: 1.4rem; }

/* Posiciones */
.sa-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.sa-table th { background: #f0f0f0; text-align: center; padding: 6px 8px; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: .04em; }
.sa-table td { padding: 8px; border-bottom: 1px solid #eee; text-align: center; }
.sa-table td:first-child, .sa-table th:first-child { text-align: left; padding-left: 12px; }
.sa-table tr:hover td { background: #f9f9f9; }
.sa-pos-num { font-weight: 700; color: #888; width: 28px; display: inline-block; text-align: center; }
.sa-pos-1 .sa-pos-num { color: #f5c518; }
.sa-pos-2 .sa-pos-num, .sa-pos-3 .sa-pos-num { color: #aaa; }
.sa-team-name { display: flex; align-items: center; gap: 8px; font-weight: 600; }
.sa-team-logo { width: 24px; height: 24px; object-fit: contain; border-radius: 2px; }
.sa-team-logo-ph { width: 24px; height: 24px; border-radius: 50%; background: #1a1a2e; display: inline-flex; align-items: center; justify-content: center; color: #f5c518; font-size: 0.65rem; font-weight: 700; }
.sa-pts { font-weight: 800; color: #1a1a2e; }
.sa-card-wrap { border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; margin-bottom: 16px; }

/* Goleadores */
.sa-goals-list { list-style: none; margin: 0; padding: 0; }
.sa-goals-list li { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid #f0f0f0; }
.sa-goals-list li:last-child { border-bottom: none; }
.sa-goals-rank { font-size: 0.75rem; font-weight: 700; color: #aaa; width: 22px; text-align: center; }
.sa-goals-rank-1 { color: #f5c518; font-size: 0.95rem; }
.sa-goals-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; background: #eee; }
.sa-goals-avatar-ph { width: 32px; height: 32px; border-radius: 50%; background: #1a1a2e; color: #f5c518; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; }
.sa-goals-info { flex: 1; }
.sa-goals-name { font-weight: 600; font-size: 0.9rem; }
.sa-goals-team { font-size: 0.75rem; color: #888; }
.sa-goals-count { font-size: 1.1rem; font-weight: 800; color: #1a1a2e; }
.sa-goals-count::after { content: "⚽"; font-size: 0.85rem; margin-left: 2px; }

/* Partidos (calendario / resultados) */
.sa-match { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-bottom: 1px solid #f0f0f0; gap: 8px; }
.sa-match:last-child { border-bottom: none; }
.sa-match-team { display: flex; align-items: center; gap: 6px; flex: 1; font-size: 0.9rem; font-weight: 600; }
.sa-match-team.visita { flex-direction: row-reverse; text-align: right; }
.sa-match-logo { width: 22px; height: 22px; object-fit: contain; }
.sa-match-center { text-align: center; min-width: 70px; }
.sa-match-score { font-size: 1.1rem; font-weight: 800; background: #1a1a2e; color: #f5c518; padding: 4px 10px; border-radius: 6px; letter-spacing: .05em; }
.sa-match-vs { font-size: 0.75rem; color: #aaa; font-weight: 500; }
.sa-match-time { font-size: 0.75rem; color: #888; margin-top: 3px; }
.sa-match-jornada { font-size: 0.7rem; text-transform: uppercase; color: #aaa; letter-spacing: .06em; padding: 6px 14px 2px; font-weight: 600; }

/* Equipos */
.sa-equipos { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; padding: 14px; }
.sa-equipo-card { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 16px 10px; border: 1px solid #eee; border-radius: 10px; text-align: center; transition: box-shadow .2s; }
.sa-equipo-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.sa-equipo-logo { width: 56px; height: 56px; object-fit: contain; }
.sa-equipo-logo-ph { width: 56px; height: 56px; border-radius: 50%; background: #1a1a2e; color: #f5c518; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 700; }
.sa-equipo-nombre { font-size: 0.82rem; font-weight: 700; }
.sa-equipo-jugadores { font-size: 0.72rem; color: #888; }

/* Marcador en vivo */
.sa-live-wrap { background: #1a1a2e; color: #fff; border-radius: 10px; padding: 20px; text-align: center; }
.sa-live-badge { display: inline-flex; align-items: center; gap: 6px; background: #e53935; color: #fff; font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; letter-spacing: .08em; margin-bottom: 12px; }
.sa-live-badge::before { content: "●"; animation: sa-blink 1s infinite; }
@keyframes sa-blink { 0%,100%{opacity:1}50%{opacity:0} }
.sa-live-teams { display: flex; align-items: center; justify-content: center; gap: 12px; }
.sa-live-team { display: flex; flex-direction: column; align-items: center; gap: 8px; }
.sa-live-logo { width: 60px; height: 60px; object-fit: contain; }
.sa-live-name { font-size: 0.85rem; font-weight: 600; }
.sa-live-score { font-size: 3rem; font-weight: 900; color: #f5c518; letter-spacing: .05em; padding: 0 16px; }
.sa-live-status { font-size: 0.75rem; color: #aaa; margin-top: 8px; }

/* Misc */
.sa-empty { padding: 24px; text-align: center; color: #aaa; font-size: 0.85rem; }
.sa-loading { padding: 24px; text-align: center; color: #aaa; }
.sa-error { padding: 12px 16px; background: #fff3f3; color: #c62828; border-left: 3px solid #c62828; font-size: 0.85rem; border-radius: 0 6px 6px 0; margin: 0; }
';
}

// ═══════════════════════════════════════════════════════════════════════════════
// SHORTCODE HELPERS
// ═══════════════════════════════════════════════════════════════════════════════

function soccerapp_wrap(string $title, string $icon, string $content): string
{
    return '<div class="sa-wrap sa-card-wrap">'
         . '<h3 class="sa-title"><span class="sa-icon">' . $icon . '</span>' . esc_html($title) . '</h3>'
         . $content
         . '</div>';
}

function soccerapp_team_logo(string $logoUrl, string $name, int $size = 24): string
{
    if ($logoUrl) {
        return '<img class="sa-team-logo" src="' . esc_url($logoUrl) . '" alt="' . esc_attr($name) . '" width="' . $size . '" height="' . $size . '">';
    }
    $initials = strtoupper(mb_substr($name, 0, 2));
    return '<span class="sa-team-logo-ph" aria-hidden="true">' . esc_html($initials) . '</span>';
}

function soccerapp_needs_config(): string
{
    return '<p class="sa-error">⚠️ SoccerAPP no está configurado. Ve a <strong>Ajustes → SoccerAPP</strong> y configura la URL y el token de la API.</p>';
}

// ═══════════════════════════════════════════════════════════════════════════════
// [soccerapp_posiciones torneo_id="1" titulo="Tabla de Posiciones"]
// ═══════════════════════════════════════════════════════════════════════════════

function soccerapp_sc_posiciones(array $atts): string
{
    if (!soccerapp_api_url()) return soccerapp_needs_config();

    $a = shortcode_atts(['torneo_id' => 0, 'titulo' => 'Tabla de Posiciones'], $atts);
    $data = soccerapp_fetch('/torneos/' . (int) $a['torneo_id'] . '/posiciones');

    if ($data === null) return '<p class="sa-error">No se pudieron cargar las posiciones.</p>';
    if (empty($data))   return '<p class="sa-empty">Aún no hay posiciones registradas.</p>';

    $rows = '';
    foreach ($data as $i => $e) {
        $pos   = $i + 1;
        $cls   = $pos <= 3 ? " sa-pos-{$pos}" : '';
        $logo  = soccerapp_team_logo($e['logo_url'] ?? '', $e['nombre'] ?? '');
        $rows .= '<tr class="sa-pos' . $cls . '">'
               . '<td><span class="sa-pos-num">' . $pos . '</span></td>'
               . '<td><span class="sa-team-name">' . $logo . esc_html($e['nombre']) . '</span></td>'
               . '<td>' . (int) ($e['pj'] ?? 0) . '</td>'
               . '<td>' . (int) ($e['pg'] ?? 0) . '</td>'
               . '<td>' . (int) ($e['pe'] ?? 0) . '</td>'
               . '<td>' . (int) ($e['pp'] ?? 0) . '</td>'
               . '<td>' . (int) ($e['gf'] ?? 0) . '</td>'
               . '<td>' . (int) ($e['gc'] ?? 0) . '</td>'
               . '<td>' . (int) ($e['dg'] ?? 0) . '</td>'
               . '<td class="sa-pts"><strong>' . (int) ($e['pts'] ?? 0) . '</strong></td>'
               . '</tr>';
    }

    $table = '<table class="sa-table">'
           . '<thead><tr><th>#</th><th>Equipo</th><th>PJ</th><th>PG</th><th>PE</th><th>PP</th><th>GF</th><th>GC</th><th>DG</th><th>Pts</th></tr></thead>'
           . '<tbody>' . $rows . '</tbody>'
           . '</table>';

    return soccerapp_wrap($a['titulo'], '🏆', $table);
}
add_shortcode('soccerapp_posiciones', 'soccerapp_sc_posiciones');

// ═══════════════════════════════════════════════════════════════════════════════
// [soccerapp_goleadores torneo_id="1" limit="5" titulo="Goleadores"]
// ═══════════════════════════════════════════════════════════════════════════════

function soccerapp_sc_goleadores(array $atts): string
{
    if (!soccerapp_api_url()) return soccerapp_needs_config();

    $a = shortcode_atts(['torneo_id' => 0, 'limit' => 10, 'titulo' => 'Tabla de Goleadores'], $atts);
    $data = soccerapp_fetch('/torneos/' . (int) $a['torneo_id'] . '/goleadores?limit=' . (int) $a['limit']);

    if ($data === null) return '<p class="sa-error">No se pudieron cargar los goleadores.</p>';
    if (empty($data))   return '<p class="sa-empty">Aún no hay goles registrados.</p>';

    $items = '';
    foreach ($data as $i => $g) {
        $rank    = $i + 1;
        $rankCls = $rank === 1 ? ' sa-goals-rank-1' : '';
        $items  .= '<li>'
                 . '<span class="sa-goals-rank' . $rankCls . '">' . $rank . '</span>'
                 . '<span class="sa-goals-avatar-ph">' . esc_html(mb_substr($g['nombre'] ?? 'J', 0, 2)) . '</span>'
                 . '<span class="sa-goals-info">'
                 .   '<span class="sa-goals-name">' . esc_html($g['nombre'] ?? '') . '</span>'
                 .   '<span class="sa-goals-team">' . esc_html($g['equipo'] ?? '') . '</span>'
                 . '</span>'
                 . '<span class="sa-goals-count">' . (int) ($g['goles'] ?? 0) . '</span>'
                 . '</li>';
    }

    return soccerapp_wrap($a['titulo'], '⚽', '<ul class="sa-goals-list">' . $items . '</ul>');
}
add_shortcode('soccerapp_goleadores', 'soccerapp_sc_goleadores');

// ═══════════════════════════════════════════════════════════════════════════════
// [soccerapp_calendario torneo_id="1" jornada="" limit="20"]
// ═══════════════════════════════════════════════════════════════════════════════

function soccerapp_sc_calendario(array $atts): string
{
    if (!soccerapp_api_url()) return soccerapp_needs_config();

    $a = shortcode_atts(['torneo_id' => 0, 'jornada' => '', 'limit' => 20, 'titulo' => 'Calendario'], $atts);
    $qs = '?estado=pendiente';
    if ($a['jornada'] !== '') $qs .= '&jornada=' . (int) $a['jornada'];
    $data = soccerapp_fetch('/torneos/' . (int) $a['torneo_id'] . '/partidos' . $qs);

    if ($data === null) return '<p class="sa-error">No se pudo cargar el calendario.</p>';

    $limited = array_slice($data, 0, (int) $a['limit']);
    if (empty($limited)) return soccerapp_wrap($a['titulo'], '📅', '<p class="sa-empty">No hay partidos pendientes.</p>');

    $html = '';
    $lastJornada = null;
    foreach ($limited as $p) {
        $j = $p['jornada_numero'] ?? '';
        if ($j !== $lastJornada) {
            $html .= '<div class="sa-match-jornada">Jornada ' . (int) $j . '</div>';
            $lastJornada = $j;
        }
        $hora = $p['hora'] ? esc_html(substr($p['hora'], 0, 5)) : 'Por confirmar';
        $html .= '<div class="sa-match">'
               . '<div class="sa-match-team">' . soccerapp_team_logo($p['local_logo'] ?? '', $p['local_nombre'] ?? '') . esc_html($p['local_nombre'] ?? '') . '</div>'
               . '<div class="sa-match-center"><div class="sa-match-vs">VS</div><div class="sa-match-time">' . $hora . '</div></div>'
               . '<div class="sa-match-team visita">' . soccerapp_team_logo($p['visita_logo'] ?? '', $p['visita_nombre'] ?? '') . esc_html($p['visita_nombre'] ?? '') . '</div>'
               . '</div>';
    }

    return soccerapp_wrap($a['titulo'], '📅', $html);
}
add_shortcode('soccerapp_calendario', 'soccerapp_sc_calendario');

// ═══════════════════════════════════════════════════════════════════════════════
// [soccerapp_resultados torneo_id="1" jornada="" limit="20"]
// ═══════════════════════════════════════════════════════════════════════════════

function soccerapp_sc_resultados(array $atts): string
{
    if (!soccerapp_api_url()) return soccerapp_needs_config();

    $a = shortcode_atts(['torneo_id' => 0, 'jornada' => '', 'limit' => 20, 'titulo' => 'Resultados'], $atts);
    $qs = '?estado=finalizado';
    if ($a['jornada'] !== '') $qs .= '&jornada=' . (int) $a['jornada'];
    $data = soccerapp_fetch('/torneos/' . (int) $a['torneo_id'] . '/partidos' . $qs);

    if ($data === null) return '<p class="sa-error">No se pudieron cargar los resultados.</p>';

    $limited = array_slice(array_reverse($data), 0, (int) $a['limit']);
    if (empty($limited)) return soccerapp_wrap($a['titulo'], '🏅', '<p class="sa-empty">No hay resultados aún.</p>');

    $html = '';
    $lastJornada = null;
    foreach ($limited as $p) {
        $j = $p['jornada_numero'] ?? '';
        if ($j !== $lastJornada) {
            $html .= '<div class="sa-match-jornada">Jornada ' . (int) $j . '</div>';
            $lastJornada = $j;
        }
        $gl = $p['goles_local']  ?? 0;
        $gv = $p['goles_visita'] ?? 0;
        $html .= '<div class="sa-match">'
               . '<div class="sa-match-team">' . soccerapp_team_logo($p['local_logo'] ?? '', $p['local_nombre'] ?? '') . esc_html($p['local_nombre'] ?? '') . '</div>'
               . '<div class="sa-match-center"><div class="sa-match-score">' . (int)$gl . ' - ' . (int)$gv . '</div></div>'
               . '<div class="sa-match-team visita">' . soccerapp_team_logo($p['visita_logo'] ?? '', $p['visita_nombre'] ?? '') . esc_html($p['visita_nombre'] ?? '') . '</div>'
               . '</div>';
    }

    return soccerapp_wrap($a['titulo'], '🏅', $html);
}
add_shortcode('soccerapp_resultados', 'soccerapp_sc_resultados');

// ═══════════════════════════════════════════════════════════════════════════════
// [soccerapp_equipos torneo_id="1"]
// ═══════════════════════════════════════════════════════════════════════════════

function soccerapp_sc_equipos(array $atts): string
{
    if (!soccerapp_api_url()) return soccerapp_needs_config();

    $a = shortcode_atts(['torneo_id' => 0, 'titulo' => 'Equipos participantes'], $atts);
    $data = soccerapp_fetch('/torneos/' . (int) $a['torneo_id'] . '/equipos');

    if ($data === null) return '<p class="sa-error">No se pudieron cargar los equipos.</p>';
    if (empty($data))   return '<p class="sa-empty">No hay equipos inscritos.</p>';

    $cards = '';
    foreach ($data as $e) {
        $logo = $e['logo_url']
            ? '<img class="sa-equipo-logo" src="' . esc_url($e['logo_url']) . '" alt="' . esc_attr($e['nombre']) . '">'
            : '<div class="sa-equipo-logo-ph">' . esc_html(mb_strtoupper(mb_substr($e['nombre'], 0, 2))) . '</div>';
        $jug = (int) ($e['total_jugadores'] ?? 0);
        $cards .= '<div class="sa-equipo-card">'
                . $logo
                . '<span class="sa-equipo-nombre">' . esc_html($e['nombre']) . '</span>'
                . '<span class="sa-equipo-jugadores">' . $jug . ' jugador' . ($jug !== 1 ? 'es' : '') . '</span>'
                . '</div>';
    }

    return soccerapp_wrap($a['titulo'], '👥', '<div class="sa-equipos">' . $cards . '</div>');
}
add_shortcode('soccerapp_equipos', 'soccerapp_sc_equipos');

// ═══════════════════════════════════════════════════════════════════════════════
// [soccerapp_marcador partido_id="5"]
// Marcador en tiempo real — usa JavaScript para polling cada 30s
// ═══════════════════════════════════════════════════════════════════════════════

function soccerapp_sc_marcador(array $atts): string
{
    if (!soccerapp_api_url()) return soccerapp_needs_config();

    $a = shortcode_atts(['partido_id' => 0, 'refresh' => 30], $atts);
    $pid = (int) $a['partido_id'];
    if (!$pid) return '<p class="sa-error">Especifica partido_id en el shortcode.</p>';

    $data = soccerapp_fetch('/partidos/' . $pid, false); // sin cache para datos en vivo
    if ($data === null) return '<p class="sa-error">Partido #' . $pid . ' no encontrado.</p>';

    $isLive = ($data['estado'] ?? '') === 'en_curso';
    $local  = $data['local'] ?? [];
    $visita = $data['visita'] ?? [];
    $gl     = (int) ($local['goles']  ?? 0);
    $gv     = (int) ($visita['goles'] ?? 0);

    $localLogo  = $local['logo_url']  ? '<img class="sa-live-logo" src="' . esc_url($local['logo_url'])  . '" alt="">' : '<div class="sa-live-logo"></div>';
    $visitaLogo = $visita['logo_url'] ? '<img class="sa-live-logo" src="' . esc_url($visita['logo_url']) . '" alt="">' : '<div class="sa-live-logo"></div>';

    $badge = $isLive
        ? '<div class="sa-live-badge">EN VIVO</div>'
        : '<div class="sa-live-badge" style="background:#555">' . strtoupper(esc_html($data['estado'] ?? 'Pendiente')) . '</div>';

    $uid  = 'sa-live-' . $pid;
    $html = '<div class="sa-live-wrap" id="' . $uid . '">'
          . $badge
          . '<div class="sa-live-teams">'
          .   '<div class="sa-live-team">' . $localLogo . '<span class="sa-live-name">' . esc_html($local['nombre'] ?? '') . '</span></div>'
          .   '<div class="sa-live-score" id="' . $uid . '-score">' . $gl . ' - ' . $gv . '</div>'
          .   '<div class="sa-live-team">' . $visitaLogo . '<span class="sa-live-name">' . esc_html($visita['nombre'] ?? '') . '</span></div>'
          . '</div>'
          . '<div class="sa-live-status" id="' . $uid . '-status">Jornada ' . (int) ($data['jornada'] ?? 0) . '</div>'
          . '</div>';

    if ($isLive) {
        $apiEndpoint = soccerapp_api_url() . '/partidos/' . $pid;
        $token       = soccerapp_api_token();
        $interval    = max(15, (int) $a['refresh']) * 1000;
        $html .= '<script>
(function(){
  var uid="' . esc_js($uid) . '", interval=' . $interval . ';
  function refresh(){
    fetch("' . esc_js($apiEndpoint) . '",{headers:{"Authorization":"Bearer ' . esc_js($token) . '"}})
    .then(function(r){return r.json();})
    .then(function(d){
      if(!d.success) return;
      var data=d.data;
      var sc=document.getElementById(uid+"-score");
      if(sc) sc.textContent=(data.local.goles||0)+" - "+(data.visita.goles||0);
      var st=document.getElementById(uid+"-status");
      if(st && data.estado!=="en_curso"){
        st.textContent="Partido finalizado";
        clearInterval(timer);
      }
    }).catch(function(){});
  }
  var timer=setInterval(refresh, interval);
})();
</script>';
    }

    return $html;
}
add_shortcode('soccerapp_marcador', 'soccerapp_sc_marcador');

// ═══════════════════════════════════════════════════════════════════════════════
// ADMIN: SETTINGS PAGE
// ═══════════════════════════════════════════════════════════════════════════════

add_action('admin_menu', function () {
    add_options_page(
        'SoccerAPP',
        'SoccerAPP',
        'manage_options',
        'soccerapp',
        'soccerapp_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('soccerapp_settings', 'soccerapp_api_url',   ['sanitize_callback' => 'esc_url_raw']);
    register_setting('soccerapp_settings', 'soccerapp_api_token', ['sanitize_callback' => 'sanitize_text_field']);
});

function soccerapp_settings_page(): void
{
    $apiUrl   = get_option('soccerapp_api_url', '');
    $apiToken = get_option('soccerapp_api_token', '');

    // Verificar conexión
    $connectionStatus = '';
    if ($apiUrl && $apiToken) {
        $test = soccerapp_fetch('/torneos', false);
        if ($test !== null) {
            $connectionStatus = '<span style="color:#2ecc71;font-weight:600;">✓ Conectado — ' . count($test) . ' torneo(s) encontrado(s)</span>';
        } else {
            $connectionStatus = '<span style="color:#e74c3c;font-weight:600;">✗ Sin conexión — verifica la URL y el token</span>';
        }
    }

    ?>
    <div class="wrap">
        <h1>⚽ SoccerAPP — Configuración</h1>
        <form method="post" action="options.php">
            <?php settings_fields('soccerapp_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="soccerapp_api_url">URL de la API</label></th>
                    <td>
                        <input name="soccerapp_api_url" id="soccerapp_api_url" type="url"
                               value="<?= esc_attr($apiUrl) ?>"
                               placeholder="http://tudominio.com/torneo/api/v1"
                               class="regular-text">
                        <p class="description">URL base de la API REST de SoccerAPP (incluye <code>/api/v1</code>)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="soccerapp_api_token">Token de acceso (Bearer)</label></th>
                    <td>
                        <input name="soccerapp_api_token" id="soccerapp_api_token" type="password"
                               value="<?= esc_attr($apiToken) ?>"
                               class="regular-text">
                        <p class="description">Créalo en SoccerAPP → Sistema → API Tokens</p>
                    </td>
                </tr>
                <?php if ($connectionStatus): ?>
                <tr>
                    <th>Estado</th>
                    <td><?= $connectionStatus ?></td>
                </tr>
                <?php endif; ?>
            </table>
            <?php submit_button('Guardar configuración'); ?>
        </form>

        <h2>Shortcodes disponibles</h2>
        <table class="widefat striped" style="max-width:800px;">
            <thead><tr><th>Shortcode</th><th>Descripción</th></tr></thead>
            <tbody>
                <tr><td><code>[soccerapp_posiciones torneo_id="1"]</code></td><td>Tabla de posiciones completa</td></tr>
                <tr><td><code>[soccerapp_goleadores torneo_id="1" limit="5"]</code></td><td>Top goleadores</td></tr>
                <tr><td><code>[soccerapp_calendario torneo_id="1"]</code></td><td>Próximos partidos pendientes</td></tr>
                <tr><td><code>[soccerapp_resultados torneo_id="1"]</code></td><td>Últimos resultados</td></tr>
                <tr><td><code>[soccerapp_equipos torneo_id="1"]</code></td><td>Grid de equipos del torneo</td></tr>
                <tr><td><code>[soccerapp_marcador partido_id="5" refresh="30"]</code></td><td>Marcador en vivo (se refresca automáticamente)</td></tr>
            </tbody>
        </table>
    </div>
    <?php
}
