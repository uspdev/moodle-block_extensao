<?php

namespace block_extensao\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use block_extensao\local\Chamados;

/**
 * Formulário de novo chamado de suporte.
 * customdata: cursos (id => nome) em que o usuário está inscrito.
 */
class chamado_form extends \moodleform {
  public function definition() {
    $f = $this->_form;
    $cursos = $this->_customdata['cursos'] ?? [];

    $f->addElement('text', 'titulo', 'Título', ['size' => 70, 'maxlength' => 255]);
    $f->setType('titulo', PARAM_TEXT);
    $f->addRule('titulo', null, 'required', null, 'client');
    $f->addRule('titulo', null, 'maxlength', 255, 'client');

    $opcoes = [0 => 'Não é referente a nenhum curso'] + $cursos;
    $f->addElement('select', 'courseid', 'Curso relacionado', $opcoes);
    $f->setType('courseid', PARAM_INT);

    $f->addElement('editor', 'mensagem_editor', 'Descreva sua dúvida ou problema', null, Chamados::opcoes_editor());
    $f->setType('mensagem_editor', PARAM_RAW);
    $f->addRule('mensagem_editor', null, 'required', null, 'client');

    $f->addElement('filemanager', 'anexos', 'Anexos (imagens ou PDF)', null, Chamados::opcoes_anexos());

    $this->add_action_buttons(true, 'Abrir chamado');
  }

  public function validation($data, $files) {
    $erros = parent::validation($data, $files);
    if (trim($data['titulo'] ?? '') === '') $erros['titulo'] = 'Informe um título.';
    $curso = (int) ($data['courseid'] ?? 0);
    if ($curso && !array_key_exists($curso, $this->_customdata['cursos'] ?? [])) {
      $erros['courseid'] = 'Selecione um curso em que você está inscrito.';
    }
    if (trim(html_to_text($data['mensagem_editor']['text'] ?? '', 0, false)) === ''
        && strpos($data['mensagem_editor']['text'] ?? '', '<img') === false) {
      $erros['mensagem_editor'] = 'Descreva sua dúvida ou problema.';
    }
    return $erros;
  }
}
