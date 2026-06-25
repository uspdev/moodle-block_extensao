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

