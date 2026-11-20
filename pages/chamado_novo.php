<?php
/**
 * Abrir um chamado de suporte (qualquer usuário logado).
 */

require_once(__DIR__ . '/../../../config.php');

use block_extensao\local\Chamados;
use block_extensao\form\chamado_form;

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$context = context_system::instance();
$url = new moodle_url('/blocks/extensao/pages/chamado_novo.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Novo chamado');
$PAGE->set_heading('Suporte — novo chamado');

$voltar = new moodle_url('/blocks/extensao/pages/suporte.php', ['aba' => 'meus']);
$form = new chamado_form($url, ['cursos' => Chamados::cursos_usuario($USER->id)]);

if ($form->is_cancelled()) {
    redirect($voltar);
}
if ($dados = $form->get_data()) {
    $id = Chamados::criar($dados);
    redirect(new moodle_url('/blocks/extensao/pages/chamado.php', ['id' => $id]),
        'Chamado aberto. Você receberá um e-mail a cada resposta.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Editor e anexos vazios (áreas de rascunho)
$inicial = (object) ['mensagem' => '', 'mensagemformat' => FORMAT_HTML];
$inicial = file_prepare_standard_editor($inicial, 'mensagem', Chamados::opcoes_editor(), $context,
    'block_extensao', 'mensagem', null);
$draft = file_get_submitted_draft_itemid('anexos');
file_prepare_draft_area($draft, $context->id, 'block_extensao', 'anexo', null, Chamados::opcoes_anexos());
$inicial->anexos = $draft;
$form->set_data($inicial);

echo $OUTPUT->header();
echo html_writer::start_div('block-extensao-suporte');
$form->display();
echo html_writer::end_div();
echo $OUTPUT->footer();
