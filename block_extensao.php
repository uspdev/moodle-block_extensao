<?php
/**
 * Este eh o bloco. Se verifica se o usuario possui um numero USP em seu 
 * cadastro no Moodle (idnumber), e se possuir verifica se tem algum
 * curso associado na base sincronizada com o Apolo, apresentando botoes
 * de criacao de ambiente ao usuario para os cursos para o qual este for
 * ministrante.
 */

require_once(__DIR__ . '/../../config.php');
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

require_once('classes/local/Turmas.php');
require_once('classes/local/Categorias.php');

use block_extensao\local\Turmas;
use block_extensao\local\Categorias;

class block_extensao extends block_base {
    public function init() {
        $this->title = 'USP Extensão';
    }

    public function get_content() {
        global $USER, $OUTPUT, $CFG;
        
        // Para garantir que o formulario nao se duplique
        if ($this->content != null) return $this->content;
        else $this->content =  new stdClass;

        // caso tenha numero USP
        if (isset($USER->username) and !empty($USER->username)) {
            // precisamos capturar na base Moodle os cursos nos quais o usuario eh docente e
            // cujo ambiente ainda nao foi criado
            $cursos = Turmas::cursos_formatados_usuario($USER->username);
            
            // Precisamos tambem saber se o usuario eh gerente de alguma categoria
            $categorias = Categorias::usuario_gerente_categoria($USER->id);

            if (!empty($categorias)) {
                foreach ($categorias as $categoria) {
                    if (!is_null($categoria->idnumber)) {
                        $cursos_categoria = Turmas::cursos_formatados_categoria($categoria->idnumber);
                        $cursos = $cursos + $cursos_categoria;
                    }
                }
            }
        } else {
            $cursos = [];
            $categorias = [];
        }
        // Lista de cursos aguardando criação de ambiente. Cada curso vira um
        // cartão com botão "Criar ambiente", que leva ao fluxo de criação
        // (criar_ambiente.php?turma=...; a permissão é checada lá).
        $lista_cursos = [];
        if (!empty($cursos)) {
            global $DB;
            $turmas = $DB->get_records_list('block_extensao_turma', 'codofeatvceu', array_keys($cursos));
            foreach ($turmas as $turma) {
                if (!empty($turma->id_moodle)) continue;
                $nome = trim(str_replace(["\r", "\n"], ' ', $turma->nome_curso_apolo));
                if (!empty($turma->nomatvceu) && $turma->nomatvceu != $turma->nome_curso_apolo) {
                    $nome .= ' - ' . trim($turma->nomatvceu);
                }
                $inicio = !empty($turma->dtainiofeatv) ? userdate($turma->dtainiofeatv, '%d/%m/%Y') : '-';
                $fim    = !empty($turma->dtafimofeatv) ? userdate($turma->dtafimofeatv, '%d/%m/%Y') : '-';
                $lista_cursos[] = [
                    'codofeatvceu' => $turma->codofeatvceu,
                    'nome'         => format_string($nome),
                    'inicio'       => $inicio,
                    'fim'          => $fim,
                    'ordem'        => (int) $turma->dtainiofeatv,
                    // texto usado pelo filtro de busca (JS)
                    'busca'        => s(core_text::strtolower($nome . ' ' . $turma->codofeatvceu . ' ' . $turma->sglund)),
                    'url_criar'    => (new moodle_url('/blocks/extensao/pages/criar_ambiente.php', [
                        'turma'   => $turma->codofeatvceu,
                        'sesskey' => sesskey(),
                    ]))->out(false),
                ];
            }
            // Os que começam antes aparecem primeiro
            usort($lista_cursos, function ($a, $b) { return $a['ordem'] <=> $b['ordem']; });
        }

        // informacoes que serao enviadas ao template
        $infos = array(
            'sem_cursos' => empty($lista_cursos),
            'cursos'     => $lista_cursos,
            'total'      => count($lista_cursos),
            // Com muitos cursos, mostra a busca e limita a altura da lista
            'com_busca'  => count($lista_cursos) > 5,
        );

        // Estratégia para capturamos se usuário é admin e usarmos no mustache para mostrar mais opções
        $context = context_system::instance();
        $infos['is_admin'] = has_capability('moodle/site:config', $context);

        // Responsáveis/ministrantes e gerentes de categoria também acessam a
        // página de ambientes (só as turmas que podem ver)
        $infos['pode_ver_ambientes'] = $infos['is_admin']
            || (isloggedin() && !isguestuser() && Turmas::filtro_sql_turmas_usuario($USER) !== null);

        // Link para página de gerencia de ambientes
        $infos['url_ambientes'] = (new moodle_url('/blocks/extensao/pages/ambientes.php'))->out(false);

        // template
        $this->content->text = $OUTPUT->render_from_template('block_extensao/extensao_block', $infos);
        return $this->content;
    }

    function has_config(){
        return true;
    }    

}
