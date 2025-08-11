Este plugin Moodle foi desenvolvido com o intuito de automatizar o processo de troca de informações entre o Moodle de Extensão da USP e a base de dados de extensão, que alimenta o sistema Apolo, além de facilitar o processo de criação de ambientes para os docentes de cursos de extensão.

- CLI para sincronização de cursos e ministrantes com a base de dados da USP (Apolo);
- Scheduler para sincronização diária às 00h01;
- Visualização e inclusão de todos os docentes de uma turma, inclusive a criação de conta (Shibbolleth) e envio de e-mails para aqueles que ainda não têm acesso ao Moodle;
- Interface individual para listagem e criação de turmas nas quais o usuário logado conste como docente;
- Recuperação automática após exclusão de ambientes criados através do plugin.

### Desenvolvimento 

Subindo um ambiente de desenvolvimento:

    mkdir moodles
    cd moodles
    wget https://raw.githubusercontent.com/fflch/moodle4_composer/refs/heads/master/php.ini
    git clone -b extensao git@gitlab.uspdigital.usp.br:atp/moodle.git moodle-extensao
    php -S 0.0.0.0:8888 -t moodle-extensao -c php.ini

Endereço para login: http://0.0.0.0:8888/acessar/

Mudanças:

- Em "Aparência" -> "Temas" -> "USP Extensão 2".
- Blocos usados: Cursos acessados recentemente, Usuários Online
- No perfil -> Modificar -> Opcional -> Número de identificação: colocar número USP
- Configuração em email mailtrap
- 


#### Colocando esse bloco para dev:

    cd blocks
    rm -rf extensao
    git clone git@gitlab.uspdigital.usp.br:atp/moodle-block_extensao.git extensao




## Configurações

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
    
### Mapeamento cepaview:

As tabelas na base do CEPAVIEW são as correspondentes:

| Padrão                   | CEPAVIEW (Visualização)        |
| ------------------------ |:------------------------------:|
| OFERECIMENTOATIVIDADECEU | CEPAVIEW_OFERECIMENTOATIVIDADE |
| CURSOCEU                 | CEPAVIEW_CURSOCEU              |
| EDICAOCURSOOFECEU        | CEPAVIEW_EDICAOCURSOOFECEU     |
| MINISTRANTECEU           | CEPAVIEW_MINISTRANTECEU        |
| EMAILPESSOA              | CEPAVIEW_EMAILPESSOA           |
| UNIDADE                  | CEPAVIEW_UNIDADE               |
| CAMPUS                   | CEPAVIEW_CAMPUS                |
| PESSOA                   | CEPAVIEW_PESSOA                |   
    
## Troubleshooting
   
Listagem dos ministrantes com quantidades de turmas:

    select codpes, count(*) as n_turmas from block_extensao_ministrante group by codpes order by n_turmas;
    
Listagem de turmas com quantidades de ministrantes:

    select codofeatvceu, count(*) as n_ministrantes from block_extensao_ministrante group by codofeatvceu order by n_ministrantes;    
    
Dado um turma com codofeatvceu igual a 90509, listar seus ministrantes:

    select codpes from block_extensao_ministrante where codofeatvceu = 90509;

### Plugins usado no moodle extensão:

Blocks:

    blocks/extensao (Custom block)
    blocks/advnotifications (Advanced Notifications block)
    blocks/configurable_reports (Configurable Reports block)
    blocks/feedback (Feedback block)
    blocks/game (Game block)
    blocks/lp (Learning Plan block)
    blocks/point_view (Point View block)
    blocks/workflow (Workflow block)
    blocks/xp (XP block, likely for "Level Up XP" plugin)

Local Plugins:

    local/commander (Custom plugin)
    local/mass_enroll (Mass Enrollment plugin)

Mod Plugins (Activities/Resources):

    mod/coursecertificate (Course Certificate module)
    mod/dialogue (Dialogue activity)
    mod/diary (Diary activity)
    mod/googlemeet (Google Meet integration)
    mod/hvp (H5P third-party plugin, though H5P is now core in newer versions)
    mod/pdfannotator (PDF Annotator activity)
    mod/publication (Publication activity)
    mod/quizgame (Quiz Game activity)
    mod/simplecertificate (Simple Certificate module)
    mod/studentquiz (StudentQuiz activity)
    mod/vpl (Virtual Programming Lab)

Theme:

    theme/extensao2 (Custom theme, likely for "Extensão" functionality)
    theme/moove (Third-party theme, not part of core Moodle)

Other:

    extensao (Top-level custom directory, possibly a custom plugin or theme component)
    filter/filtercodes (Filter Codes plugin)
    filter/generico (Generico filter plugin)

Referências:

- https://gitlab.uspdigital.usp.br/atp/moodle/-/tree/edisc/blocks/usp_cursos