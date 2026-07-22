<?php

namespace block_extensao\task;

use core\task\scheduled_task;
use block_extensao\local\Matriculados;

class sincronizar_matriculados extends scheduled_task {

    /**
     * Retorna o nome amigável da tarefa exibido no painel de administração.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_sincronizar_matriculados', 'block_extensao');
    }

    /**
     * Execução da tarefa agendada.
     */
    public function execute() {
        global $DB;

        mtrace("Iniciando sincronizacao de matriculados das turmas ativas...");

        // SQL para buscar turmas vinculadas ao Moodle cujos cursos ainda estao abertos
        // Considera aberto se course.enddate for 0 (sem data de fim) OU maior/igual ao momento atual.
        $sql = "SELECT extensao.codofeatvceu, 
                       extensao.id_moodle AS courseid,
                       c.enddate
                  FROM {block_extensao_turma} extensao
            INNER JOIN {course} c ON c.id = extensao.id_moodle
                 WHERE extensao.id_moodle IS NOT NULL 
                   AND extensao.id_moodle > 0
                   AND (c.enddate = 0 OR c.enddate >= :now)";

        $params = [
            'now' => time()
        ];

        $turmas_ativas = $DB->get_records_sql($sql, $params);

        if (empty($turmas_ativas)) {
            mtrace("Nenhuma turma ativa encontrada para sincronizar.");
            return;
        }

        $total = count($turmas_ativas);
        mtrace("Encontrada(s) {$total} turma(s) ativa(s). Processando...");

        foreach ($turmas_ativas as $turma) {
            mtrace("Sincronizando codofeatvceu: {$turma->codofeatvceu} com Course ID: {$turma->courseid}...");

            try {
                // Linha solicitada executada para cada curso aberto:
                Matriculados::cria_matriculados($turma->codofeatvceu, $turma->courseid);
                
                mtrace(" -> Sucesso no processamento do curso {$turma->courseid}.");
            } catch (\Throwable $e) {
                // Trata excecoes para garantir que uma falha individual nao pare o loop dos demais cursos
                mtrace(" -> ERRO ao sincronizar turma {$turma->codofeatvceu}: " . $e->getMessage());
            }
        }

        mtrace("Sincronizacao finalizada com sucesso!");
    }
}