<?php
/**
 * API pública: URL do curso no Moodle de uma turma do Apolo.
 *
 *   GET /blocks/extensao/api/curso.php?codofeatvceu=150627
 *   => {"url": "https://.../course/view.php?id=18"}
 *
 * Se a turma não existir, não tiver ambiente criado ou o código for inválido:
 *   => {"url": ""}
 *
 * Página 100% pública: não exige login nem usa sessão.
 */

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../../config.php');

$codofeatvceu = optional_param('codofeatvceu', 0, PARAM_INT);

$url = '';
if ($codofeatvceu > 0) {
    $courseid = $DB->get_field_sql(
        "SELECT c.id
           FROM {block_extensao_turma} t
           JOIN {course} c ON c.id = t.id_moodle
          WHERE t.codofeatvceu = :cod",
        ['cod' => $codofeatvceu]);
    if ($courseid) {
        $url = (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300');
echo json_encode(['url' => $url], JSON_UNESCAPED_SLASHES);
