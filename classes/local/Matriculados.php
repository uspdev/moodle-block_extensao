<?php

namespace block_extensao\local;
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/user/lib.php');

use core\message\message;
use block_extensao\local\Service\Query;

class Matriculados {

    public static function cria_matriculados($codofeatvceu, $courseid){
        global $DB;
        $query = new Query();
        $array_de_codpes = $query->matriculados($codofeatvceu);
        $matriculados = $query->informacoesUsuario($array_de_codpes);

        // não tem matriculados ainda
        if(empty($matriculados)) return;

        // indexando pelo codpes para facilitar a vida
        $matriculadosPorCodpes = [];
        foreach ($matriculados as $m) {
            $matriculadosPorCodpes[$m['codpes']] = $m;
        }

        // Estudantes com contas já criadas no moodle
        list($insql1, $params1) = $DB->get_in_or_equal($array_de_codpes, SQL_PARAMS_NAMED);
        list($insql2, $params2) = $DB->get_in_or_equal($array_de_codpes, SQL_PARAMS_NAMED, 'idn');

        $sql = "
            SELECT id, username, idnumber
            FROM {user}
            WHERE username $insql1
            OR idnumber $insql2
        ";

        $existentes = $DB->get_records_sql($sql, $params1 + $params2);

        // Atualiza os usuários existentes
        $userids = [];
        foreach ($existentes as $existente) {
            $codpes = null;

            if (isset($matriculadosPorCodpes[$existente->username])) {
                $codpes = $existente->username;
            } elseif (!empty($existente->idnumber) &&
                    isset($matriculadosPorCodpes[$existente->idnumber])) {
                $codpes = $existente->idnumber;
            }

            if ($codpes === null) {
                continue;
            }

            $m = $matriculadosPorCodpes[$codpes];

            $partes = preg_split('/\s+/', trim($m['nompes']));
            $firstname = array_shift($partes);
            $lastname = implode(' ', $partes);

            $update = new \stdClass();
            $update->id = $existente->id;
            $update->username = $codpes;
            $update->idnumber = $codpes;
            $update->firstname = $firstname;
            $update->lastname = $lastname;
            $update->confirmed = 1;
            $update->email = $m['codema'];
            $update->auth = 'shibboleth';

            try {
                user_update_user($update, false);
            } catch (\Throwable $e) {
                // TODO: Thiago. verificar com o Leônicas com os usuários repetidos na base
                //mtrace("Erro atualizando usuário {$existente->id}: ".$e->getMessage());
            }

            $userids[] = $existente->id;

            // Remove para saber quem ainda precisa ser criado.
            unset($matriculadosPorCodpes[$codpes]);
        }

        // Agora cadastra os que não tem cadastro
        foreach ($matriculadosPorCodpes as $codpes => $m) {

            $partes = preg_split('/\s+/', trim($m['nompes']));
            $firstname = array_shift($partes);
            $lastname = implode(' ', $partes);

            $novo = new \stdClass();
            $novo->username = $codpes;
            $novo->idnumber = $codpes;
            $novo->firstname = $firstname;
            $novo->lastname = $lastname;
            $novo->email = $m['codema'];
            $novo->auth = 'shibboleth';
            $novo->confirmed = 1;

            try {
                $novo->id = user_create_user($novo, false, false);
                $userids[] = $novo->id;
            } catch (\Throwable $e) {
                // TODO: Thiago. verificar com o Leônicas com os usuários repetidos na base
                //mtrace("Erro criando usuário {$codpes}: ".$e->getMessage());
            }
        }


        // Atribui papel de student
        $enrol = $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol' => 'manual'
        ], '*', MUST_EXIST);

        $plugin = enrol_get_plugin('manual');
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
        foreach ($userids as $userid) {
            $plugin->enrol_user(
                $enrol,
                $userid,
                $studentrole
            );
        }

    }

}
