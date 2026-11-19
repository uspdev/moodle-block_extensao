<?php
/**
 * Detalhes de uma turma Moodle/Apolo (admin, responsável/ministrante da turma ou gerente da categoria)
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
// em que atua; gerente de categoria só as turmas da unidade/campus da
// categoria. Qualquer outro usuário é barrado.
if (!has_capability('moodle/site:config', $context)) {
    $filtro_usuario = Turmas::filtro_sql_turmas_usuario($USER);
    $permitido = false;
    if ($filtro_usuario !== null) {
        list($where_usuario, $params_usuario) = $filtro_usuario;
        $permitido = $DB->record_exists_select('block_extensao_turma',
            "codofeatvceu = :codturma AND $where_usuario", ['codturma' => $codofeatvceu] + $params_usuario);
    }
    if (!$permitido) {
        throw new moodle_exception('nopermissions', 'error', '', 'ver esta turma (você não é responsável, ministrante ou gerente da unidade dela)');
    }
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
    $matriculados_formatados = [];

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
            $codpes = trim((string) $m['codpes']);
            
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

    // Se o ambiente Moodle existe, verifica a situação de cada aluno no curso
    $tem_curso_moodle = !empty($r->id_moodle) && $DB->record_exists('course', ['id' => $r->id_moodle]);
    if ($tem_curso_moodle && !empty($matriculados_formatados)) {
        $lista_codpes = array_map(function ($m) { return (string) $m['codpes']; }, $matriculados_formatados);

        list($in_user, $params_user) = $DB->get_in_or_equal($lista_codpes, SQL_PARAMS_NAMED, 'usr');
        list($in_idn, $params_idn)   = $DB->get_in_or_equal($lista_codpes, SQL_PARAMS_NAMED, 'idn');
        $where_codpes = "u.deleted = 0 AND (u.username $in_user OR u.idnumber $in_idn)";
        $params_codpes = $params_user + $params_idn;

        // Contas existentes no Moodle (para distinguir "sem conta" de "não inscrito")
        $com_conta = [];
        foreach ($DB->get_records_sql("SELECT u.id, u.username, u.idnumber FROM {user} u WHERE $where_codpes", $params_codpes) as $u) {
            $com_conta[trim($u->username)] = true;
            $com_conta[trim((string) $u->idnumber)] = true;
        }

        // Inscrições no curso (qualquer método de inscrição)
        $sql = "SELECT ue.id, u.username, u.idnumber, ue.timecreated, ue.status AS ue_status, e.status AS e_status
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid AND $where_codpes";
        $inscricoes = [];
        foreach ($DB->get_records_sql($sql, ['courseid' => $r->id_moodle] + $params_codpes) as $ins) {
            $ativa = ((int) $ins->ue_status === ENROL_USER_ACTIVE && (int) $ins->e_status === ENROL_INSTANCE_ENABLED);
            foreach ([trim($ins->username), trim((string) $ins->idnumber)] as $chave) {
                if ($chave === '') continue;
                // Se houver mais de uma inscrição, prioriza a ativa e, depois, a mais antiga
                $atual = $inscricoes[$chave] ?? null;
                if (!$atual || ($ativa && !$atual['ativa'])
                        || ($ativa === $atual['ativa'] && $ins->timecreated < $atual['timecreated'])) {
                    $inscricoes[$chave] = ['ativa' => $ativa, 'timecreated' => (int) $ins->timecreated];
                }
            }
        }

        foreach ($matriculados_formatados as &$m) {
            $chave = (string) $m['codpes'];
            if (isset($inscricoes[$chave])) {
                $m['inscrito']        = true;
                $m['inscrito_ativo']  = $inscricoes[$chave]['ativa'];
                $m['data_inscricao']  = $inscricoes[$chave]['timecreated']
                    ? userdate($inscricoes[$chave]['timecreated'], '%d/%m/%Y %H:%M') : '';
            } else {
                $m['inscrito'] = false;
                $m['sem_conta'] = empty($com_conta[$chave]);
            }
        }
        unset($m);
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
        'tem_curso_moodle'   => $tem_curso_moodle,
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