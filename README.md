# Cursos de Extensão USP

O plugin possui as seguintes configurações:
- Credenciais da base de dados (Apolo):
    - Host;
    - Porta;
    - Base de dados;
    - Usuário;
    - Senha.
- Envio de e-mails:
    - Texto enviado para novos usuários. Contém tokens de substituição;
- Tabelas da base de dados:
    - Oferecimento do curso/turma. Padrão: `OFERECIMENTOATIVIDADECEU`;
    - Informações do curso. Padrão: `CURSOCEU`;
    - Informações da edição do curso. Padrão: `EDICAOCURSOOFECEU`;
    - Informações dos ministrantes. Padrão: `MINISTRANTECEU`;
    - E-mails. Padrão: `EMAILPESSOA`;
    - Unidades da USP. Padrão: `UNIDADE`;
    - Campi da USP. Padrão: `CAMPUS`;
    - Informações de cadastro. Padrão: `PESSOA`;
- Busca de cursos:
    - Período de busca de turmas: 3, 6, 9 meses ou 1 ano.

## Task para sincronização com o Apolo

    admin/cli/scheduled_task.php --execute="\\block_extensao\\task\\sincronizar"

Os responsáveis do curso podem criar qualquer turma (displcina/atividade/ofereciemnto) do curso - como todos responsáveis são adicionados como ministrantes, pode remover a lógica anterior


docker exec -it moodleusp php moodle/admin/cli/cron.php


fazer um task shceduled para atualizar os estudantes

na matricula dos estuantes, fazer um merge?

Desativar a notificação na criação/atualização (Recomendado)

Se você está criando usuários via API (Web Services) ou quer apenas que eles não recebam e-mails de alteração de senha/boas-vindas, altere as seguintes configurações no painel do Moodle:

    Acesse como Administrador: Administração do site > Plugins > Autenticação > Gerenciar autenticação.


    ministrantes duplicados