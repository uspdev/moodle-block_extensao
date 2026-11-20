<?php
/**
 * Suporte: lista de chamados.
 *  - aba "Meus chamados": os chamados do usuário logado;
 *  - aba "Atendimento" (admin e gerentes de categoria): chamados que ele atende.
 * A lista é paginada no banco para aguentar milhares de chamados fechados.
 */

require_once(__DIR__ . '/../../../config.php');

use block_extensao\local\Chamados;

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$context = context_system::instance();
$atendente = Chamados::eh_atendente();

$aba    = optional_param('aba', $atendente ? 'atendimento' : 'meus', PARAM_ALPHA);
if (!in_array($aba, ['atendimento', 'meus', 'atendentes']) || ($aba === 'atendimento' && !$atendente)) $aba = 'meus';
$status = optional_param('status', $aba === 'atendimento' ? 'abertos' : 'todos', PARAM_ALPHA);
if (!in_array($status, ['abertos', Chamados::ABERTO, Chamados::ESCALADO, Chamados::FECHADO, 'todos'])) $status = 'abertos';
$busca  = trim(optional_param('busca', '', PARAM_TEXT));
$pagina = optional_param('page', 0, PARAM_INT);

$url = new moodle_url('/blocks/extensao/pages/suporte.php', ['aba' => $aba, 'status' => $status]);
if ($busca !== '') $url->param('busca', $busca);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Suporte');
$PAGE->set_heading('Suporte');

$registros = [];
$total = 0;
if ($aba !== 'atendentes') {
    list($registros, $total) = Chamados::listar($aba, [
        'status' => $status === 'todos' ? '' : $status,
        'busca' => $busca,
    ], $pagina);
}

$usuarios = Chamados::usuarios(array_map(fn($r) => $r->userid, $registros));
$linhas = [];
foreach ($registros as $r) {
    $linhas[] = [
        'id' => $r->id,
        'titulo' => format_string($r->titulo),
        'url' => (new moodle_url('/blocks/extensao/pages/chamado.php', ['id' => $r->id]))->out(false),
        'aberto' => $r->status === Chamados::ABERTO,
        'escalado' => $r->status === Chamados::ESCALADO,
        'fechado' => $r->status === Chamados::FECHADO,
        'curso' => $r->courseid ? format_string($r->curso ?? ('Curso ' . $r->courseid)) : '',
        'url_curso' => ($r->courseid && $r->curso !== null)
            ? (new moodle_url('/course/view.php', ['id' => $r->courseid]))->out(false) : '',
        'unidade' => $r->unidade !== null ? trim(format_string($r->unidade)) : '',
        'solicitante' => isset($usuarios[$r->userid]) ? fullname($usuarios[$r->userid]) : '',
        'atualizado' => userdate($r->timemodified, '%d/%m/%Y %H:%M'),
        'criado' => userdate($r->timecreated, '%d/%m/%Y'),
        'criado_ha' => Chamados::tempo_relativo($r->timecreated),
        'atualizado_ha' => Chamados::tempo_relativo($r->timemodified),
    ];
}

// Quantos chamados aguardam o administrador (indicador no filtro)
$n_escalados = 0;
if ($aba !== 'atendentes') {
    list(, $n_escalados) = Chamados::listar($aba, ['status' => Chamados::ESCALADO], 0, 1);
}

$abas = [];
$opcoes_abas = $atendente ? ['atendimento' => 'Atendimento'] : [];
$opcoes_abas += ['meus' => 'Meus chamados', 'atendentes' => 'Atendentes'];
foreach ($opcoes_abas as $chave => $nome) {
    $abas[] = ['nome' => $nome, 'ativa' => $aba === $chave,
        'url' => (new moodle_url('/blocks/extensao/pages/suporte.php', ['aba' => $chave]))->out(false)];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_extensao/suporte', [
    'abas' => $abas,
    'tem_abas' => !empty($abas),
    'atendimento' => $aba === 'atendimento',
    'lista' => $aba !== 'atendentes',
    'aba_atendentes' => $aba === 'atendentes',
    // E-mails dos atendentes só para quem atende (admin e gerentes)
    'cidades' => $aba === 'atendentes' ? Chamados::atendentes_por_unidade($atendente) : [],
    'url_publica' => (new moodle_url('/blocks/extensao/pages/atendentes.php'))->out(false),
    'aba' => $aba,
    'url_acao' => (new moodle_url('/blocks/extensao/pages/suporte.php'))->out(false),
    'url_novo' => (new moodle_url('/blocks/extensao/pages/chamado_novo.php'))->out(false),
    'busca' => $busca,
    'st_abertos' => $status === 'abertos',
    'st_aberto' => $status === Chamados::ABERTO,
    'st_fechado' => $status === Chamados::FECHADO,
    'st_escalado' => $status === Chamados::ESCALADO,
    'n_escalados' => $n_escalados,
    'st_todos' => $status === 'todos',
    'linhas' => $linhas,
    'tem_linhas' => !empty($linhas),
    'total' => $total,
    'paginacao' => $OUTPUT->paging_bar($total, $pagina, Chamados::POR_PAGINA, $url),
]);
echo $OUTPUT->footer();
