<?php
/**
 * Notificações do bloco Extensão.
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    // Avisos ao solicitante sobre interações no seu chamado de suporte.
    'chamado' => [
        'defaults' => [
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
    // Aviso aos gerentes da unidade: chamado novo a ser atendido.
    'chamado_novo' => [
        'defaults' => [
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
