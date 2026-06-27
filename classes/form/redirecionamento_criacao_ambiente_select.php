<?php

namespace block_extensao\form;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use block_extensao\local\Turmas;
use block_extensao\local\Ambiente;
use block_extensao\local\Atuacao;

// formulario para os docentes criarem um ambiente para um curso (versao select)

class redirecionamento_criacao_ambiente_select extends \moodleform {
  public function definition () {
    global $CFG, $DB;
    // Captura a lista de cursos
    if (isset($this->_customdata['cursos'])) 
        $cursos = $this->_customdata['cursos'];
    else 
        $cursos = [];

    $options = array();
    foreach ($cursos as $indice => $nome_curso) {
      $turma = $DB->get_record('block_extensao_turma', ['codofeatvceu'=>$indice]);
  
      // Convertendo o formato da data
      $Inicio = !is_null($turma->dtainiofeatv) ? date('d/m/Y', $turma->dtainiofeatv) : '';
      $Fim = !is_null($turma->dtafimofeatv) ? date('d/m/Y', $turma->dtafimofeatv) : '';

      // Se o nome do curso coincidir com o nome da atividade, não precisa repetir
      $curso = "($turma->codofeatvceu) {$turma->nome_curso_apolo}";
      if($turma->nome_curso_apolo != $turma->nomatvceu) $curso .= " - {$turma->nomatvceu}";
      
      // Formatado com a data de inicio entre parenteses;
      $option_label = "$curso ($Inicio-$Fim)";
      $options[$indice] = $option_label;
    }
  
    $options = array('placeholder' => "Buscar") + $options;
    $this->_form->addElement('autocomplete', 'select_ambiente', '', $options);

    // botao de ajuda com os cursos disponiveis para o ministrante 
    $this->_form->addHelpButton('select_ambiente',  'cursos_disponiveis', 'block_extensao');


    // botao de submit
    $this->_form->addElement('submit', 'redirecionar_criar_ambiente', 'Criar ambiente');
  }
}
