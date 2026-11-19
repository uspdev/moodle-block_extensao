<?php
/**
 * Detalhes de uma turma Moodle/Apolo (admin ou responsável/ministrante da turma)
 * Path: /blocks/extensao/pages/turma.php
 */

// 1. Requerimentos do Moodle
require_once(__DIR__ . '/../../../config.php');

use block_extensao\local\Turmas;

// 2. Recebe o parâmetro obrigatório "codofeatvceu"
require_login();
$context = context_system::instance();
$codofeatvceu = required_param('codofeatvceu', PARAM_INT);

// 3. Acesso: admin vê qualquer turma; responsável/ministrante só as turmas
// em que atua. Qualquer outro usuário é barrado.
if (!has_capability('moodle/site:config', $context)
        && !in_array($codofeatvceu, Turmas::codofeatvceu_ministrante_usuario($USER), true)) {
    throw new required_capability_exception($context, 'moodle/site:config', 'nopermissions', '');
}

// 4. Configurações da Página
$pageurl = new moodle_url('/blocks/extensao/pages/turma.php', ['codofeatvceu' => $codofeatvceu]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'block_extensao'));
$PAGE->set_heading('Detalhes do Ambiente - ' . $codofeatvceu);

// 5. Busca o registro da turma no banco
$r = $DB->get_record('block_extensao_turma', ['codofeatvceu' => $codofeatvceu]);

$turma_data = null;

if ($r) {
    // Busca ministrantes e responsáveis
    $ministrantes_raw = $DB->get_records('block_extensao_ministrante', ['codofeatvceu' => $codofeatvceu]);
    
    $responsaveis_formatados = [];
    $ministrantes_formatados = [];

    if ($ministrantes_raw) {
        foreach ($ministrantes_raw as $m) {
            $info_pessoa = format_string($m->codpes) . ' - ' . format_string($m->nompes);
            if (!empty($m->codema)) {
                $info_pessoa .= ' (' . format_string($m->codema) . ')';
            }

            if ((int)$m->responsavel === 1) {
                $responsaveis_formatados[] = ['nome' => $info_pessoa];
            } else {
                $ministrantes_formatados[] = ['nome' => $info_pessoa];
            }
        }
    }

    $matriculados = (new \block_extensao\local\Service\Query())->matriculados($r->codofeatvceu);
    $matriculados = (new \block_extensao\local\Service\Query())-> informacoesUsuario($matriculados);

    if (!empty($matriculados)) {
        $unicos = [];
        
        foreach ($matriculados as $m) {
            $codpes = $m['codpes'];
            
            // Evita duplicados agrupando pelo codpes
            if (!isset($unicos[$codpes])) {
                $email = !empty($m['codema']) ? ' (' . format_string($m['codema']) . ')' : '';
                
                $unicos[$codpes] = [
                    'codpes' => $codpes,
                    'nome'   => format_string($m['nompes']),
                    'email'  => format_string($m['codema'] ?? ''),
                    'texto'  => $codpes . ' - ' . format_string($m['nompes']) . $email
                ];
            }
        }
        
        // Reindexa o array para o Mustache
        $matriculados_formatados = array_values($unicos);
    }

    // Formata o objeto único para o Mustache
    $turma_data = [
        'codofeatvceu'       => $r->codofeatvceu,
        'nome_curso_apolo'   => format_string($r->nome_curso_apolo),
        'codcurceu'          => $r->codcurceu,
        'codedicurceu'       => $r->codedicurceu,
        'numseqofeedi'       => $r->numseqofeedi,
        'sglund'             => $r->sglund,
        'nomatvceu'          => format_string($r->nomatvceu),
        'matriculados'       => $matriculados_formatados,
        'tem_matriculados'   => !empty($matriculados_formatados),
        'id_moodle'          => $r->id_moodle,
        // Quem acessa esta página (admin ou responsável/ministrante da turma)
        // pode criar o ambiente; criar_ambiente.php valida de novo.
        'url_criar'          => (new moodle_url('/blocks/extensao/pages/criar_ambiente.php', [
            'turma'   => $r->codofeatvceu,
            'sesskey' => sesskey(),
        ]))->out(false),
        'data_inicio'        => !empty($r->dtainiofeatv) ? userdate($r->dtainiofeatv, '%d/%m/%Y') : '-',
        'data_fim'           => !empty($r->dtafimofeatv) ? userdate($r->dtafimofeatv, '%d/%m/%Y') : '-',
        'responsaveis'       => $responsaveis_formatados,
        'ministrantes'       => $ministrantes_formatados,
        'tem_responsaveis'   => !empty($responsaveis_formatados),
        'tem_ministrantes'   => !empty($ministrantes_formatados),
    ];
}

// 6. Renderização
echo $OUTPUT->header();

echo $OUTPUT->render_from_template('block_extensao/turma', [
    'encontrado' => !empty($turma_data),
    'turma'      => $turma_data,
    'wwwroot'    => $CFG->wwwroot,
    'codofeatvceu_buscado' => $codofeatvceu
]);

echo $OUTPUT->footer();