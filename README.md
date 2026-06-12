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
- Tabelas da base de dados ([ver relação do CEPAVIEW](#mapeamento-cepaview)):
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

## Sincronização com o Apolo

Configurados os dados de acesso à base, para sincronizar com o Sistema Apolo, rode:

    php cli/sync.php

Para mais informações, há a opção de ajuda:

    php cli/sync.php -h