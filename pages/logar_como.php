<?php
/**
 * "Logar como" um responsavel/ministrante ou aluno matriculado da turma,
 * a partir do painel de ambientes ou da pagina da turma.
 * Restrito ao site admin. Se o usuario ainda nao existir no Moodle, a conta
 * eh criada (mesmo padrao dos alunos em Matriculados::cria_matriculados).
 *
 * Path: /blocks/extensao/pages/logar_como.php?codpes=...&codofeatvceu=...&sesskey=...[&voltar=turma]
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/user/lib.php');

use block_extensao\local\Usuario;

$codpes       = required_param('codpes', PARAM_INT);
$codofeatvceu = required_param('codofeatvceu', PARAM_INT);
$origem       = optional_param('voltar', '', PARAM_ALPHA);

$context = context_system::instance();
$PAGE->set_url(new moodle_url('/blocks/extensao/pages/logar_como.php', [
    'codpes' => $codpes, 'codofeatvceu' => $codofeatvceu, 'voltar' => $origem,
]));
$PAGE->set_context($context);

require_login();
require_sesskey();

// Apenas o site admin pode usar este recurso
if (!is_siteadmin()) {
    throw new moodle_exception('nopermissions', 'error', '', 'logar como outro usuário');
}

$voltar = $origem === 'turma'
    ? new moodle_url('/blocks/extensao/pages/turma.php', ['codofeatvceu' => $codofeatvceu])
    : new moodle_url('/blocks/extensao/pages/ambientes.php');

// O codpes precisa ser responsavel/ministrante (tabela local) ou aluno
// matriculado (Apolo) da turma informada
$pessoa = $DB->get_record('block_extensao_ministrante', [
    'codpes'       => $codpes,
    'codofeatvceu' => $codofeatvceu,
], 'codpes, nompes, codema', IGNORE_MULTIPLE);
if (!$pessoa) {
    try {
        $query = new \block_extensao\local\Service\Query();
        $matriculados = array_map(fn($c) => (int) $c, $query->matriculados($codofeatvceu));
        if (in_array($codpes, $matriculados, true)) {
            $info = $query->informacoesUsuario([$codpes]);
            $pessoa = (object) [
                'codpes' => $codpes,
                'nompes' => $info[0]['nompes'] ?? (string) $codpes,
                'codema' => $info[0]['codema'] ?? '',
            ];
        }
    } catch (\Throwable $e) {
        redirect($voltar, 'Erro ao consultar os matriculados no Apolo: ' . $e->getMessage(),
            null, \core\output\notification::NOTIFY_ERROR);
    }
}
if (!$pessoa) {
    redirect($voltar, 'Pessoa não encontrada entre os responsáveis, ministrantes ou matriculados desta turma.',
        null, \core\output\notification::NOTIFY_ERROR);
}

try {
    $usuario = Usuario::obter_ou_criar_por_codpes($codpes, $pessoa->nompes, $pessoa->codema);
} catch (\Throwable $e) {
    redirect($voltar, 'Erro ao criar o usuário ' . $codpes . ': ' . $e->getMessage(),
        null, \core\output\notification::NOTIFY_ERROR);
}

if ($usuario->id == $USER->id) {
    redirect($voltar, 'Você já está logado como este usuário.', null, \core\output\notification::NOTIFY_WARNING);
}
if (is_siteadmin($usuario)) {
    redirect($voltar, 'Não é permitido logar como outro administrador.', null, \core\output\notification::NOTIFY_ERROR);
}
if (!empty($usuario->suspended)) {
    redirect($voltar, 'O usuário ' . fullname($usuario) . ' está suspenso.', null, \core\output\notification::NOTIFY_ERROR);
}

// Destino: o curso da turma, se ja existir; senao, o painel
$turma = $DB->get_record('block_extensao_turma', ['codofeatvceu' => $codofeatvceu], 'codofeatvceu, id_moodle');
if ($turma && !empty($turma->id_moodle) && $DB->record_exists('course', ['id' => $turma->id_moodle])) {
    $destino = new moodle_url('/course/view.php', ['id' => $turma->id_moodle]);
} else {
    $destino = new moodle_url('/my/');
}

// Login-as no contexto de sistema (gera o evento user_loggedinas no log)
\core\session\manager::loginas($usuario->id, $context);

redirect($destino, get_string('loggedinas', '', fullname($USER, true)), null,
    \core\output\notification::NOTIFY_INFO);
