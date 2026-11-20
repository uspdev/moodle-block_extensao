# Cursos de Extensão USP

> Tutorial para usuários (responsáveis, ministrantes e gerentes de categoria): [docs/index.html](docs/index.html)

O plugin possui as seguintes configurações:
- Credenciais da base de dados (Apolo):
    - Host;
    - Porta;
    - Base de dados;
    - Usuário;
    - Senha.
- Envio de e-mails:
    - Texto enviado para novos usuários. Contém tokens de substituição;
- Busca de cursos:
    - Período de busca de turmas: 3, 6, 9 meses ou 1 ano.

## Tasks

O plugin tem três tasks. As agendadas dependem do cron do Moodle.

### `sincronizar` (agendada, todo dia às 00:01)

Busca no Apolo as turmas de extensão abertas e atualiza a tabela `block_extensao_turma` e os ministrantes de cada turma. É ela que define quais turmas aparecem para os docentes criarem ambiente.

    admin/cli/scheduled_task.php --execute="\\block_extensao\\task\\sincronizar"

### `matriculados_task` (adhoc, roda uma vez)

Colocada na fila em `Ambiente.php` quando um ambiente Moodle é criado para uma turma. Na próxima execução do cron, inscreve como `student` no curso recém-criado os alunos matriculados naquela turma no Apolo. Por isso a matrícula inicial não é imediata.

Para rodar as adhoc pendentes na hora:

    admin/cli/adhoc_task.php --execute

### `sincronizar_matriculados` (agendada, todo dia às 00:00)

Percorre todas as turmas que já têm ambiente Moodle (`id_moodle` preenchido) cujo curso ainda está aberto (`enddate` vazio ou no futuro) e repete a matrícula para cada uma. Assim entram no curso os alunos que se matricularam no Apolo depois da criação do ambiente. Se uma turma der erro, o erro vai para o log e as outras continuam.

    admin/cli/scheduled_task.php --execute="\\block_extensao\\task\\sincronizar_matriculados"

Atenção: Quem cancelar a matrícula no Apolo **não** é desinscrito do curso no Moodle.

## API pública: URL do curso de uma turma

Não exige login. Dado o código da turma no Apolo (`codofeatvceu`), retorna a URL do curso no Moodle:

    GET /blocks/extensao/api/curso.php?codofeatvceu=150627
    {"url":"https://<moodle>/course/view.php?id=18"}

Se a turma não existir, ainda não tiver ambiente criado ou o código for inválido, retorna `{"url":""}`.
