<?php

namespace block_extensao\form;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use block_extensao\local\Turmas;
use block_extensao\local\Ambiente;
use block_extensao\local\Atuacao;


// formulario para os docentes criarem um ambiente para um curso (versao lista com 5 ou menos cursos)
class redirecionamento_criacao_ambiente_lista extends \moodleform {
  public function definition () {
    global $CFG, $DB;

    // input hidden com o id da turma no plugin Extensao
    $codofeatvceu = "";
    if (isset($this->_customdata['codofeatvceu'])) {
      $codofeatvceu = $this->_customdata['codofeatvceu'];
      

      // Obter informacoes do curso
      $curso = Turmas::info_turma_id_extensao($codofeatvceu);

      // Se o nome do curso coincidir com o nome da atividade, não precisa repetir
      $nome_curso = "($curso->codofeatvceu) {$curso->nome_curso_apolo}";
      if($curso->nome_curso_apolo != $curso->nomatvceu) $nome_curso .= " - {$curso->nomatvceu}";

      $dataInicio = $curso->dtainiofeatv;
      $dataFim = $curso->dtafimofeatv;

      // Formatando a data de inicio
      $dataInicioFormatada = is_null($dataInicio) ? '-' : date('d/m/Y', $dataInicio);
      $dataFimFormatada = is_null($dataFim) ? '-' : date('d/m/Y', $dataFim);

      $labelCurso = "$nome_curso<br>";
      $labelCurso .= "<span style='color: red;'>(".$dataInicioFormatada."-".$dataFimFormatada.")</span>";      

      // Adicionando o elemento de texto com o nome do curso e a data de inicio estilizada
      $this->_form->addElement('static', 'curso_com_data_inicio', $labelCurso);
    }
    $this->_form->addElement('hidden', 'codofeatvceu', $codofeatvceu);
    $this->_form->setType('codofeatvceu', PARAM_TEXT);

    // botao de submit
    $this->_form->addElement('submit', 'redirecionar_criar_ambiente', 'Criar ambiente');
  }
}
