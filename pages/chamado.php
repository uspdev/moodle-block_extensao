<?php
/**
 * Um chamado de suporte: dados, histórico completo e ações
 * (comentar, fechar, reabrir, encaminhar ao administrador, devolver à
 * unidade). Acesso: solicitante, admin e quem atende o curso do chamado
 * (gerentes de categoria).
 */

require_once(__DIR__ . '/../../../config.php');

use block_extensao\local\Chamados;
use block_extensao\form\chamado_comentario_form;

require_login(null, false);

$id = required_param('id', PARAM_INT);
$chamado = Chamados::obter($id);
if (!$chamado || !Chamados::pode_ver($chamado)) {
    throw new moodle_exception('nopermissions', 'error', '', 'ver este chamado');
}

$context = context_system::instance();
$url = new moodle_url('/blocks/extensao/pages/chamado.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Chamado #' . $id);
$PAGE->set_heading('Suporte — chamado #' . $id);

$atende = Chamados::pode_atender($chamado);
$admin = Chamados::eh_admin();
// Gerente (não admin) pode encaminhar um chamado aberto ao administrador
$pode_encaminhar = $atende && !$admin && $chamado->status === Chamados::ABERTO;
// Só chamados de curso têm unidade para onde devolver
$pode_devolver = $admin && $chamado->status === Chamados::ESCALADO && !empty($chamado->courseid);
$voltar = new moodle_url('/blocks/extensao/pages/suporte.php',
    ['aba' => ($atende && (int) $chamado->userid !== (int) $USER->id) ? 'atendimento' : 'meus']);

// Ações de estado (POST com sesskey)
$acao = optional_param('acao', '', PARAM_ALPHA);
if ($acao && confirm_sesskey()) {
    if ($acao === 'fechar') {
        Chamados::fechar($chamado);
        redirect($url, 'Chamado fechado.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($acao === 'reabrir') {
        Chamados::reabrir($chamado);
        redirect($url, 'Chamado reaberto.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($acao === 'devolver' && $pode_devolver) {
        Chamados::devolver($chamado);
        redirect($url, 'Chamado devolvido ao atendimento da unidade.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Comentário (com o chamado aberto ou com o administrador)
$form = null;
if (Chamados::esta_aberto($chamado)) {
    $form = new chamado_comentario_form($url, ['encaminhar' => $pode_encaminhar]);
    if ($dados = $form->get_data()) {
        Chamados::comentar($chamado, $dados);
        if (!empty($dados->comentarescalar) && $pode_encaminhar) {
            Chamados::escalar(Chamados::obter($id));
            redirect($url, 'Chamado encaminhado ao administrador.', null, \core\output\notification::NOTIFY_SUCCESS);
        }
        if (!empty($dados->comentarfechar)) {
            Chamados::fechar(Chamados::obter($id));
            redirect($url, 'Comentário enviado e chamado fechado.', null, \core\output\notification::NOTIFY_SUCCESS);
        }
        redirect($url, 'Comentário enviado.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
    $inicial = (object) ['id' => $id, 'mensagem' => '', 'mensagemformat' => FORMAT_HTML];
    $inicial = file_prepare_standard_editor($inicial, 'mensagem', Chamados::opcoes_editor(), $context,
        'block_extensao', 'mensagem', null);
    $draft = file_get_submitted_draft_itemid('anexos');
    file_prepare_draft_area($draft, $context->id, 'block_extensao', 'anexo', null, Chamados::opcoes_anexos());
    $inicial->anexos = $draft;
    $form->set_data($inicial);
}

// Histórico
$eventos = Chamados::eventos($id);
$usuarios = Chamados::usuarios(array_merge([$chamado->userid], array_map(fn($e) => $e->userid, $eventos)));
$nome = fn($uid) => isset($usuarios[$uid]) ? fullname($usuarios[$uid]) : 'Usuário removido';
$rotulos = [
    'abertura' => 'abriu o chamado',
    'comentario' => 'comentou',
    'fechamento' => 'fechou o chamado',
    'reabertura' => 'reabriu o chamado',
    'encaminhamento' => 'encaminhou ao administrador',
    'devolucao' => 'devolveu ao atendimento da unidade',
];
$eventos_estado = ['fechamento', 'reabertura', 'encaminhamento', 'devolucao'];
$historico = [];
foreach ($eventos as $e) {
    $historico[] = [
        'autor' => $nome($e->userid),
        'solicitante' => (int) $e->userid === (int) $chamado->userid,
        'acao' => $rotulos[$e->tipo] ?? $e->tipo,
        'tipo_' . $e->tipo => true,
        'estado' => in_array($e->tipo, $eventos_estado),
        'data' => userdate($e->timecreated, '%d/%m/%Y %H:%M'),
        'mensagem' => trim((string) $e->mensagem) !== '' ? Chamados::mensagem_html($e) : '',
        'anexos' => Chamados::anexos($e->id),
    ];
}

$curso = $chamado->courseid ? $DB->get_record('course', ['id' => $chamado->courseid], 'id, fullname') : null;

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_extensao/chamado', [
    'id' => $id,
    'titulo' => format_string($chamado->titulo),
    'aberto' => Chamados::esta_aberto($chamado),
    'escalado' => $chamado->status === Chamados::ESCALADO,
    'pode_devolver' => $pode_devolver,
    'solicitante' => $nome($chamado->userid),
    'curso' => $curso ? format_string($curso->fullname) : '',
    'unidade' => $curso ? Chamados::unidade_curso($curso->id) : '',
    'url_curso' => $curso ? (new moodle_url('/course/view.php', ['id' => $curso->id]))->out(false) : '',
    'criado' => userdate($chamado->timecreated, '%d/%m/%Y %H:%M'),
    'atualizado' => userdate($chamado->timemodified, '%d/%m/%Y %H:%M'),
    'criado_ha' => Chamados::tempo_relativo($chamado->timecreated),
    'atualizado_ha' => Chamados::tempo_relativo($chamado->timemodified),
    'fechado' => $chamado->timeclosed ? userdate($chamado->timeclosed, '%d/%m/%Y %H:%M') : '',
    'historico' => $historico,
    'url_voltar' => $voltar->out(false),
    'url_acao' => $url->out(false),
    'sesskey' => sesskey(),
    'formulario' => $form ? $form->render() : '',
]);
echo $OUTPUT->footer();
