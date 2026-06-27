<?php

namespace block_extensao\task;

defined('MOODLE_INTERNAL') || die();


require_once(__DIR__ . '/../local/Matriculados.php');
use block_extensao\local\Matriculados;

class matriculados_task extends \core\task\adhoc_task {

    public function execute() {
        // Recupera os dados que foram passados na hora do clique
        $data = $this->get_custom_data();
        
        $codofeatvceu = $data->codofeatvceu;
        $courseid     = $data->courseid;

        Matriculados::cria_matriculados($codofeatvceu, $courseid);
    }
}