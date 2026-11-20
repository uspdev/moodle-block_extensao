<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_block_extensao_upgrade($versaoAnterior) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    // 2026062501: Remove o campo "codedicurceu" da tabela block_extensao_ministrante
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

    // 2026062601: Adiciona novos campos à tabela block_extensao_turma.
    if ($versaoAnterior < 2026062601) {
        $table = new xmldb_table('block_extensao_turma');

        $field = new xmldb_field('codcurceu', XMLDB_TYPE_INTEGER, '16', null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('codedicurceu', XMLDB_TYPE_INTEGER, '16', null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('numseqofeedi', XMLDB_TYPE_INTEGER, '16', null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('sglund', XMLDB_TYPE_CHAR, '255', null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('nomatvceu', XMLDB_TYPE_TEXT, null, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026062601, 'block', 'extensao');
    }

    if ($versaoAnterior < 2026111900) {
        // Trava campos das configurações dos cursos já criados pelo plugin.
        \block_extensao\local\TravaCurso::aplicar_todos();
        upgrade_plugin_savepoint(true, 2026111900, 'block', 'extensao');
    }

    if ($versaoAnterior < 2026111901) {
        // Chamados de suporte.
        foreach (['block_extensao_chamado', 'block_extensao_chamado_evento'] as $tabela) {
            if (!$dbman->table_exists($tabela)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $tabela);
            }
        }
        upgrade_plugin_savepoint(true, 2026111901, 'block', 'extensao');
    }

    if ($versaoAnterior < 2026111903) {
        // Chamados sem curso são atendidos só pelo admin ("Com o administrador").
        $DB->set_field_select('block_extensao_chamado', 'status', 'escalado',
            "courseid = 0 AND status = 'aberto'");
        upgrade_plugin_savepoint(true, 2026111903, 'block', 'extensao');
    }

    return true;
}