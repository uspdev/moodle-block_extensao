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

## Itens para conversar reunião 2026062602:

- Nova tela de administração de cursos do apolo (falta fazer um show dos cursos para mostrar matriculados para conferência)
- Os responsáveis do curso no Apolo agora podem criar qualquer disciplina do curso (resolve problema apontados por Ettore/Adriana)
- Alunos são inseridos via cron (não foi feito novo plugin)
- Conpuesp

Pendências Thiago:

- Nova tela de administração falta fazer um show dos cursos para mostrar matriculados no Apolo para conferência
- Fazer um task scheduled para atualizar os estudantes com apolo somente dos cursos ainda não finalizados
- Na inscrição da matricula dos estuantes, fazer um merge entre usuários dos duplicados (Leônidas)?
- Trocar cepaview para uspdb-sync 
- Gerente de categoria criar curso é útil?
- Falta fazer o form de solicitição de novos ambientes não apolo (com aprovação peja CCeX)