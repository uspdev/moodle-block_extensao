<?php

namespace block_extensao\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use block_extensao\local\Chamados;

/**
 * Formulário de comentário em um chamado.
 */
class chamado_comentario_form extends \moodleform {
  public function definition() {
    $f = $this->_form;
    $f->addElement('hidden', 'id');
    $f->setType('id', PARAM_INT);

    $f->addElement('editor', 'mensagem_editor', 'Comentário', ['rows' => 6], Chamados::opcoes_editor());
    $f->setType('mensagem_editor', PARAM_RAW);
    $f->addRule('mensagem_editor', null, 'required', null, 'client');

    $f->addElement('filemanager', 'anexos', 'Anexos (imagens ou PDF)', null, Chamados::opcoes_anexos());

    $botoes = [];
    $botoes[] = $f->createElement('submit', 'comentar', 'Comentar');
    $botoes[] = $f->createElement('submit', 'comentarfechar', 'Comentar e fechar');
    if (!empty($this->_customdata['encaminhar'])) {
      $botoes[] = $f->createElement('submit', 'comentarescalar', 'Comentar e encaminhar ao administrador');
    }
    $f->addGroup($botoes, 'botoes', '', ' ', false);
  }

  public function validation($data, $files) {
    $erros = parent::validation($data, $files);
    if (trim(html_to_text($data['mensagem_editor']['text'] ?? '', 0, false)) === ''
        && strpos($data['mensagem_editor']['text'] ?? '', '<img') === false) {
      $erros['mensagem_editor'] = 'Escreva um comentário.';
    }
    return $erros;
  }
}
