<?php
/**
 * Página administrativa com tabela dos ambientes Moodle/Apolo
 * Path: /blocks/extensao/pages/ambientes.php
 */

// 1. requires
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/tablelib.php');

// 2. Esta Página é restrita estritamente a admins
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context); 

// 3. Configurações da Página (Metadados e Layout)
$pageurl = new moodle_url('/blocks/extensao/pages/ambientes.php');
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'block_extensao'));
$PAGE->set_heading('Painel de Administração de Ambientes - Apolo/Moodle');

// 4. Captura o termo de busca enviado via parâmetro GET
$search = optional_param('search', '', PARAM_TEXT);
if (!empty($search)) $pageurl->param('search', $search);

// data início no formato desde 1 de Jan como padrão
$datainicio = optional_param('datainicio', '', PARAM_TEXT);
if (empty($datainicio)) $datainicio = date('Y-01-01');
$datainicio_ts = strtotime($datainicio . ' 00:00:00');

// data fim como padrão na data de hoje
$datafim = optional_param('datafim', '', PARAM_TEXT);
if (empty($datafim)) $datafim = date('Y-m-d');
$datafim_ts = strtotime($datafim . ' 23:59:59');

$filtro_moodle = optional_param('filtro_moodle', '', PARAM_ALPHA);

// 5. Busca no Banco de Dados (Filtrando apenas por nome_curso_apolo)
$params = [];
$where  = "1=1";

if (!empty($search)) {
    // Agrupamos com parênteses para que o OR não quebre os filtros de data abaixo
    $where .= " AND ("
        . $DB->sql_like('nome_curso_apolo', ':search_nome', false)
        . " OR " . $DB->sql_like('codofeatvceu', ':search_codofeatvceu', false)
        . " OR " . $DB->sql_like('nomatvceu', ':search_nomatvceu', false)
        . " OR " . $DB->sql_like('codcurceu', ':search_codcurceu', false)
        . ")";
    
    // Injetamos o mesmo termo de busca nos dois placeholders
    $params['search_nome'] = "%{$search}%";
    $params['search_codofeatvceu']  = "%{$search}%";
    $params['search_nomatvceu'] = "%{$search}%";
    $params['search_codcurceu']  = "%{$search}%";
}

// Filtro por intervalo de datas
// Regra:
//   dtainiofeatv >= data inicial
//   dtafimofeatv <= data final
$where .= " AND dtainiofeatv >= :datainicio";
$where .= " AND dtafimofeatv <= :datafim";

$params['datainicio'] = $datainicio_ts;
$params['datafim'] = $datafim_ts;

// Filtro do id_moodle
if ($filtro_moodle === 'com') {
    $where .= " AND id_moodle IS NOT NULL AND id_moodle <> 0";
} else if ($filtro_moodle === 'sem') {
    $where .= " AND (id_moodle IS NULL OR id_moodle = 0)";
}

// Busca os registros ordenando pelo código da oferta de forma decrescente
$registros = $DB->get_records_select(
    'block_extensao_turma',
    $where,
    $params,
    'codofeatvceu DESC'
);


// 4. Preparação dos dados para o Mustache
$turmas_formatadas = [];
// 4. Preparação dos dados para o Mustache
$turmas_formatadas = [];
if ($registros) {

    // coleta ministrantes
    $codigos_turmas = array_keys($registros);
    $ministrantes_por_turma = [];

    if (!empty($codigos_turmas)) {
        // Monta a query com o IN (?,?,?) de forma segura
        list($in_sql, $in_params) = $DB->get_in_or_equal($codigos_turmas, SQL_PARAMS_NAMED);
        $todos_ministrantes = $DB->get_records_select('block_extensao_ministrante', "codofeatvceu $in_sql", $in_params);

        // Agrupa os ministrantes por turma e por papel (responsável ou apenas ministrante)
        foreach ($todos_ministrantes as $m) {
            $cod = $m->codofeatvceu;
            if (!isset($ministrantes_por_turma[$cod])) {
                $ministrantes_por_turma[$cod] = [
                    'responsaveis' => [],
                    'ministrantes' => []
                ];
            }

            // Verifica se é responsável (considerando que 1 = Sim)
            if ((int)$m->responsavel === 1) {
                $ministrantes_por_turma[$cod]['responsaveis'][] = format_string($m->codpes) . ' - ' . format_string($m->nompes) . ' (' . $m->codema . ')';
            } else {
                $ministrantes_por_turma[$cod]['ministrantes'][] = format_string($m->codpes) . ' - ' . format_string($m->nompes) . ' (' . $m->codema . ')';
            }
        }
    }

    foreach ($registros as $r) {
        $responsaveis_formatados = [];
        if (!empty($ministrantes_por_turma[$r->codofeatvceu]['responsaveis'])) {
            foreach ($ministrantes_por_turma[$r->codofeatvceu]['responsaveis'] as $nome) {
                $responsaveis_formatados[] = ['nome' => $nome];
            }
        }

        $ministrantes_formatados = [];
        if (!empty($ministrantes_por_turma[$r->codofeatvceu]['ministrantes'])) {
            foreach ($ministrantes_por_turma[$r->codofeatvceu]['ministrantes'] as $nome) {
                $ministrantes_formatados[] = ['nome' => $nome];
            }
        }

        // CORREÇÃO AQUI: Agora passamos as variáveis para o array mapeado do Mustache
        $turmas_formatadas[] = [
            'codofeatvceu'       => $r->codofeatvceu,
            'nome_curso_apolo'   => format_string($r->nome_curso_apolo),
            'codcurceu'          => $r->codcurceu,
            'codedicurceu'       => $r->codedicurceu,
            'numseqofeedi'       => $r->numseqofeedi,
            'sglund'             => $r->sglund,
            'nomatvceu'          => $r->nomatvceu,
            'id_moodle'          => $r->id_moodle,
            'data_inicio'        => !empty($r->dtainiofeatv) ? userdate($r->dtainiofeatv, '%d/%m/%Y') : '-',
            'data_fim'           => !empty($r->dtafimofeatv) ? userdate($r->dtafimofeatv, '%d/%m/%Y') : '-',
            'responsaveis'       => $responsaveis_formatados, // <-- Adicionado
            'ministrantes'       => $ministrantes_formatados, // <-- Adicionado
            'tem_responsaveis'   => !empty($responsaveis_formatados),
            'tem_ministrantes'   => !empty($ministrantes_formatados),
        ];
    }
}

// 5. Renderização utilizando o template Mustache
echo $OUTPUT->header();

echo $OUTPUT->render_from_template('block_extensao/ambientes', [
    'url_acao'      => $pageurl->out_omit_querystring(),
    'valor_busca'   => $search,
    'datainicio'    => $datainicio,
    'datafim'       => $datafim,
    'tem_registros' => !empty($turmas_formatadas),
    'turmas'        => $turmas_formatadas,
    'sesskey'       => sesskey(),
    'wwwroot'       => $CFG->wwwroot,
    'filtro_moodle_todos' => empty($filtro_moodle),
    'filtro_moodle_com'   => $filtro_moodle === 'com',
    'filtro_moodle_sem'   => $filtro_moodle === 'sem',
]);

echo $OUTPUT->footer();