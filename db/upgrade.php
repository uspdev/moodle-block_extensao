<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_block_extensao_upgrade($versaoAnterior) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    // 2026062501: Remove o campo "codedicurceu" do banco de dados
    if ($versaoAnterior < 2026062501) {
        $tabela = new xmldb_table('block_extensao_ministrante');
        $campo_codedicurceu = new xmldb_field('codedicurceu');

        // Verifica se o campo existe antes de tentar deletar para evitar erros fatais
        if ($dbman->field_exists($tabela, $campo_codedicurceu)) {
            $dbman->drop_field($tabela, $campo_codedicurceu);
        }

        // Registrar o ponto de salvamento desta nova versão
        upgrade_plugin_savepoint(true, 2026062501, 'block', 'extensao');
    }

    return true;
}