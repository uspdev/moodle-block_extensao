<?php
/**
 * Funções do bloco Extensão usadas pelo núcleo do Moodle.
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Entrega arquivos dos chamados de suporte (imagens do texto e anexos).
 * Só quem pode ver o chamado (solicitante ou atendente) recebe o arquivo.
 */
function block_extensao_pluginfile($course, $birecord, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    if ($context->contextlevel != CONTEXT_SYSTEM || !in_array($filearea, ['mensagem', 'anexo'])) {
        send_file_not_found();
    }
    require_login(null, false);

    $eventoid = (int) array_shift($args);
    $evento = $DB->get_record('block_extensao_chamado_evento', ['id' => $eventoid]);
    $chamado = $evento ? \block_extensao\local\Chamados::obter($evento->chamadoid) : null;
    if (!$chamado || !\block_extensao\local\Chamados::pode_ver($chamado)) {
        send_file_not_found();
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'block_extensao', $filearea, $eventoid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
