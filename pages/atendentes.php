<?php
/**
 * Página pública com os atendentes (gerentes) de cada unidade, agrupados por
 * cidade. Mesmo conteúdo da aba "Atendentes" do Suporte, mas sem login e sem
 * e-mails (só nomes).
 *
 * Path: /blocks/extensao/pages/atendentes.php
 */

require_once(__DIR__ . '/../../../config.php');

use block_extensao\local\Chamados;

$context = context_system::instance();
$PAGE->set_url(new moodle_url('/blocks/extensao/pages/atendentes.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Atendentes');
$PAGE->set_heading('Suporte — atendentes por unidade');

echo $OUTPUT->header();
echo html_writer::start_div('block-extensao-suporte');
echo $OUTPUT->render_from_template('block_extensao/atendentes', [
    'cidades' => Chamados::atendentes_por_unidade(false),
]);
echo html_writer::end_div();
echo $OUTPUT->footer();
