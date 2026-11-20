<?php

namespace block_extensao\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Chamados de suporte.
 *
 * Quem pode o quê:
 *  - qualquer usuário logado: abre chamados e vê, comenta, fecha e reabre os seus;
 *  - administrador: vê e atende todos;
 *  - quem tem block/extensao:atenderchamados no curso do chamado (gerentes de
 *    categoria, por herança da categoria e subcategorias): vê e atende.
 *
 * Estados:
 *  - aberto   ("Com a unidade"): chamado de curso aguardando os gerentes da unidade;
 *  - escalado ("Com o administrador"): só o admin resolve. Chamados sem curso já
 *    nascem assim; os de curso chegam aqui quando o gerente encaminha;
 *  - fechado.
 * O admin pode devolver um escalado com curso para a unidade.
 *
 * Notificações (e-mail):
 *  - solicitante: confirmação da abertura e cada ação de outra pessoa;
 *  - gerentes da unidade do curso: aviso de chamado novo;
 *  - admin: nunca (acompanha pela lista).
 */
class Chamados {

  const ABERTO = 'aberto';      // com a unidade
  const ESCALADO = 'escalado';  // com o administrador
  const FECHADO = 'fechado';

  /** Rótulos das situações. */
  const ROTULOS = [
    self::ABERTO => 'Com a unidade',
    self::ESCALADO => 'Com o administrador',
    self::FECHADO => 'Fechado',
  ];

  /** Situação de um chamado não fechado: com curso vai para a unidade; sem curso, para o admin. */
  public static function status_inicial($courseid) {
    return empty($courseid) ? self::ESCALADO : self::ABERTO;
  }
  const CAPABILITY = 'block/extensao:atenderchamados';
  const POR_PAGINA = 25;
  const COMPONENTE = 'block_extensao';

  /**
   * Tempo decorrido desde $timestamp, em linguagem natural, usando só a maior
   * unidade: "agora", "há 5 min", "há 3 h", "há 2 dias", "há 4 meses", "há 1 ano".
   */
  public static function tempo_relativo($timestamp, $agora = null) {
    $seg = max(0, ($agora ?? time()) - (int) $timestamp);
    if ($seg < 60) return 'agora';
    $unidades = [
      [365 * DAYSECS, 'ano', 'anos'],
      [30 * DAYSECS, 'mês', 'meses'],
      [DAYSECS, 'dia', 'dias'],
      [HOURSECS, 'h', 'h'],
      [MINSECS, 'min', 'min'],
    ];
    foreach ($unidades as [$tam, $sing, $plural]) {
      if ($seg >= $tam) {
        $n = intdiv($seg, $tam);
        return 'há ' . $n . ' ' . ($n === 1 ? $sing : $plural);
      }
    }
  }

  /** Opções do editor (imagens no texto). */
  public static function opcoes_editor() {
    return [
      'maxfiles' => 10,
      'maxbytes' => 10 * 1024 * 1024,
      'subdirs' => 0,
      'context' => \context_system::instance(),
      'accepted_types' => ['web_image'],
      'autosave' => false,
    ];
  }

  /** Opções dos anexos (imagens e PDF). */
  public static function opcoes_anexos() {
    return [
      'maxfiles' => 5,
      'maxbytes' => 10 * 1024 * 1024,
      'subdirs' => 0,
      'accepted_types' => ['web_image', '.pdf'],
    ];
  }

  // ---------------------------------------------------------------- permissões

  public static function eh_admin() {
    return has_capability('moodle/site:config', \context_system::instance());
  }

  /** Ids dos cursos cujos chamados o usuário atende (sem contar admin). */
  public static function cursos_atendidos($userid = null) {
    global $USER;
    static $cache = [];
    $userid = $userid ?: $USER->id;
    if (!isset($cache[$userid])) {
      $cursos = get_user_capability_course(self::CAPABILITY, $userid, false, '', '', 0);
      $cache[$userid] = $cursos ? array_values(array_map(fn($c) => (int) $c->id, $cursos)) : [];
    }
    return $cache[$userid];
  }

  /** O usuário atende algum chamado (admin ou gerente)? */
  public static function eh_atendente() {
    return self::eh_admin() || !empty(self::cursos_atendidos());
  }

  public static function pode_atender($chamado) {
    if (self::eh_admin()) return true;
    if (empty($chamado->courseid)) return false;
    $ctx = \context_course::instance($chamado->courseid, IGNORE_MISSING);
    return $ctx && has_capability(self::CAPABILITY, $ctx);
  }

  public static function pode_ver($chamado) {
    global $USER;
    return (int) $chamado->userid === (int) $USER->id || self::pode_atender($chamado);
  }

  // ---------------------------------------------------------------- dados

  /** Cursos em que o usuário está inscrito (qualquer papel): id => nome. */
  public static function cursos_usuario($userid) {
    $cursos = [];
    foreach (enrol_get_users_courses($userid, true, 'id, fullname') as $c) {
      if ($c->id == SITEID) continue;
      $cursos[$c->id] = format_string($c->fullname);
    }
    \core_collator::asort($cursos);
    return $cursos;
  }

  public static function obter($id) {
    global $DB;
    return $DB->get_record('block_extensao_chamado', ['id' => $id]);
  }

  public static function eventos($chamadoid) {
    global $DB;
    return $DB->get_records('block_extensao_chamado_evento', ['chamadoid' => $chamadoid], 'timecreated ASC, id ASC');
  }

  /**
   * Abre um chamado. $dados vem do formulário (titulo, courseid,
   * mensagem_editor, anexos).
   */
  public static function criar($dados) {
    global $DB, $USER;
    $agora = time();
    $chamado = (object) [
      'userid' => $USER->id,
      'courseid' => (int) $dados->courseid,
      'titulo' => trim($dados->titulo),
      'status' => self::status_inicial($dados->courseid),
      'timecreated' => $agora,
      'timemodified' => $agora,
      'timeclosed' => null,
    ];
    $transacao = $DB->start_delegated_transaction();
    $chamado->id = $DB->insert_record('block_extensao_chamado', $chamado);
    $evento = self::registrar($chamado, 'abertura', $dados);
    $transacao->allow_commit();
    self::notificar($chamado, $evento);
    self::notificar_gerentes($chamado, $evento);
    return $chamado->id;
  }

  public static function comentar($chamado, $dados) {
    $evento = self::registrar($chamado, 'comentario', $dados);
    self::notificar($chamado, $evento);
  }

  public static function fechar($chamado) {
    global $DB;
    if ($chamado->status === self::FECHADO) return;
    $chamado->status = self::FECHADO;
    $chamado->timeclosed = time();
    $DB->update_record('block_extensao_chamado', $chamado);
    self::notificar($chamado, self::registrar($chamado, 'fechamento'));
  }

  public static function reabrir($chamado) {
    global $DB;
    if ($chamado->status !== self::FECHADO) return;
    $chamado->status = self::status_inicial($chamado->courseid);
    $chamado->timeclosed = null;
    $DB->update_record('block_extensao_chamado', $chamado);
    self::notificar($chamado, self::registrar($chamado, 'reabertura'));
  }

  /** Gerente encaminha ao administrador (precisa de acesso que ele não tem). */
  public static function escalar($chamado) {
    global $DB;
    if ($chamado->status !== self::ABERTO) return;
    $chamado->status = self::ESCALADO;
    $DB->update_record('block_extensao_chamado', $chamado);
    self::notificar($chamado, self::registrar($chamado, 'encaminhamento'));
  }

  /** Admin devolve o chamado ao atendimento da unidade. */
  public static function devolver($chamado) {
    global $DB;
    if ($chamado->status !== self::ESCALADO || empty($chamado->courseid)) return;
    $chamado->status = self::ABERTO;
    $DB->update_record('block_extensao_chamado', $chamado);
    self::notificar($chamado, self::registrar($chamado, 'devolucao'));
  }

  public static function esta_aberto($chamado) {
    return $chamado->status !== self::FECHADO;
  }

  /** Grava um evento do histórico (com texto e arquivos, se houver). */
  private static function registrar($chamado, $tipo, $dados = null) {
    global $DB, $USER;
    $ctx = \context_system::instance();
    $evento = (object) [
      'chamadoid' => $chamado->id,
      'userid' => $USER->id,
      'tipo' => $tipo,
      'mensagem' => '',
      'mensagemformat' => FORMAT_HTML,
      'timecreated' => time(),
    ];
    $evento->id = $DB->insert_record('block_extensao_chamado_evento', $evento);

    if ($dados !== null) {
      $dados = file_postupdate_standard_editor($dados, 'mensagem', self::opcoes_editor(), $ctx,
        self::COMPONENTE, 'mensagem', $evento->id);
      $evento->mensagem = $dados->mensagem;
      $evento->mensagemformat = $dados->mensagemformat;
      $DB->update_record('block_extensao_chamado_evento', $evento);
      if (!empty($dados->anexos)) {
        file_save_draft_area_files($dados->anexos, $ctx->id, self::COMPONENTE, 'anexo', $evento->id, self::opcoes_anexos());
      }
    }
    $DB->set_field('block_extensao_chamado', 'timemodified', $evento->timecreated, ['id' => $chamado->id]);
    return $evento;
  }

  /** Texto do evento pronto para exibir (imagens com URL real). */
  public static function mensagem_html($evento) {
    $ctx = \context_system::instance();
    $texto = file_rewrite_pluginfile_urls($evento->mensagem, 'pluginfile.php', $ctx->id,
      self::COMPONENTE, 'mensagem', $evento->id);
    return format_text($texto, $evento->mensagemformat, ['context' => $ctx]);
  }

  /** Anexos do evento: [nome, url, ehpdf]. */
  public static function anexos($eventoid) {
    $ctx = \context_system::instance();
    $lista = [];
    foreach (get_file_storage()->get_area_files($ctx->id, self::COMPONENTE, 'anexo', $eventoid, 'filename', false) as $f) {
      $lista[] = [
        'nome' => $f->get_filename(),
        'url' => \moodle_url::make_pluginfile_url($ctx->id, self::COMPONENTE, 'anexo', $eventoid,
          $f->get_filepath(), $f->get_filename(), true)->out(false),
        'pdf' => $f->get_mimetype() === 'application/pdf',
      ];
    }
    return $lista;
  }

  // ---------------------------------------------------------------- listagem

  /**
   * Lista paginada (no banco) de chamados.
   *
   * @param string $escopo 'meus' ou 'atendimento'.
   * @param array  $filtros status ('abertos' = aberto ou escalado | 'aberto' | 'escalado' | 'fechado' | ''), busca, courseid.
   * @return array [registros, total]
   */
  public static function listar($escopo, array $filtros, $pagina, $porpagina = self::POR_PAGINA) {
    global $DB, $USER;
    $where = [];
    $params = [];

    if ($escopo === 'atendimento') {
      if (!self::eh_admin()) {
        $cursos = self::cursos_atendidos();
        if (empty($cursos)) return [[], 0];
        list($in, $p) = $DB->get_in_or_equal($cursos, SQL_PARAMS_NAMED, 'cur');
        $where[] = "c.courseid $in";
        $params += $p;
      }
    } else {
      $where[] = 'c.userid = :me';
      $params['me'] = $USER->id;
    }

    if (($filtros['status'] ?? '') === 'abertos') {
      $where[] = 'c.status IN (:st1, :st2)';
      $params['st1'] = self::ABERTO;
      $params['st2'] = self::ESCALADO;
    } else if (!empty($filtros['status'])) {
      $where[] = 'c.status = :status';
      $params['status'] = $filtros['status'];
    }
    if (isset($filtros['courseid']) && $filtros['courseid'] !== '' && $filtros['courseid'] !== null) {
      $where[] = 'c.courseid = :courseid';
      $params['courseid'] = (int) $filtros['courseid'];
    }
    if (!empty($filtros['busca'])) {
      $busca = trim($filtros['busca']);
      $cond = $DB->sql_like('c.titulo', ':busca', false, false);
      $params['busca'] = '%' . $DB->sql_like_escape($busca) . '%';
      if (ctype_digit(ltrim($busca, '#'))) {
        $cond = "($cond OR c.id = :buscaid)";
        $params['buscaid'] = (int) ltrim($busca, '#');
      }
      $where[] = $cond;
    }

    $sqlwhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    // No atendimento do admin, os encaminhados a ele aparecem primeiro
    $ordem = ($escopo === 'atendimento' && self::eh_admin() && ($filtros['status'] ?? '') === 'abertos')
      ? "CASE WHEN c.status = 'escalado' THEN 0 ELSE 1 END," : '';
    $total = $DB->count_records_sql("SELECT COUNT(1) FROM {block_extensao_chamado} c $sqlwhere", $params);
    $registros = $DB->get_records_sql(
      "SELECT c.*, co.fullname AS curso, cp.name AS unidade
         FROM {block_extensao_chamado} c
    LEFT JOIN {course} co ON co.id = c.courseid
    LEFT JOIN {course_categories} cc ON cc.id = co.category
    LEFT JOIN {course_categories} cp ON cp.id = cc.parent
         $sqlwhere
     ORDER BY $ordem c.timemodified DESC, c.id DESC",
      $params, $pagina * $porpagina, $porpagina);
    return [$registros, $total];
  }

  /**
   * Unidade de um curso: a categoria acima da categoria do curso (n-1).
   * Ex.: curso em "São Paulo / FFLCH / 2026" => "FFLCH".
   */
  public static function unidade_curso($courseid) {
    global $DB;
    if (!$courseid) return '';
    $nome = $DB->get_field_sql(
      "SELECT cp.name
         FROM {course} co
         JOIN {course_categories} cc ON cc.id = co.category
         JOIN {course_categories} cp ON cp.id = cc.parent
        WHERE co.id = :id", ['id' => $courseid]);
    return $nome ? trim(format_string($nome)) : '';
  }

  /**
   * Atendentes por unidade, agrupados pela categoria de 1º nível (cidade).
   * Unidade = categoria de 2º nível cujo nome corresponde a uma unidade
   * (sglund) da tabela block_extensao_turma; atendentes = gerentes atribuídos
   * na categoria da unidade.
   *
   * @param bool $com_email Inclui o e-mail dos atendentes (só para atendentes).
   * @return array [['cidade' => nome, 'unidades' => [['unidade' => nome, 'atendentes' => [['nome', 'email']]], ...]], ...]
   */
  public static function atendentes_por_unidade($com_email = false) {
    global $DB;
    $unidades = [];
    foreach ($DB->get_fieldset_sql("SELECT DISTINCT sglund FROM {block_extensao_turma} WHERE sglund IS NOT NULL") as $u) {
      $unidades[\core_text::strtoupper(trim($u))] = true;
    }
    $lista = [];
    $cidades = $DB->get_records('course_categories', ['depth' => 1], '', 'id, name');
    foreach ($DB->get_records('course_categories', ['depth' => 2], 'name', 'id, name, parent') as $cat) {
      $nome = trim($cat->name);
      if (!isset($unidades[\core_text::strtoupper($nome)])) continue;
      $atendentes = [];
      foreach (self::gerentes_categoria($cat->id) as $u) {
        $atendentes[] = ['nome' => fullname($u), 'email' => $com_email ? $u->email : ''];
      }
      usort($atendentes, fn($a, $b) => strcmp(\core_text::strtolower($a['nome']), \core_text::strtolower($b['nome'])));
      $cidade = isset($cidades[$cat->parent]) ? trim(format_string($cidades[$cat->parent]->name)) : '';
      $lista[$cidade][] = ['unidade' => format_string($nome), 'atendentes' => array_values($atendentes)];
    }
    $grupos = [];
    foreach ($lista as $cidade => $unidades) {
      usort($unidades, fn($a, $b) => strcmp($a['unidade'], $b['unidade']));
      $grupos[] = ['cidade' => $cidade, 'unidades' => $unidades];
    }
    usort($grupos, fn($a, $b) => strcmp($a['cidade'], $b['cidade']));
    return $grupos;
  }

  /**
   * Gerentes atribuídos diretamente numa categoria (papéis do arquétipo manager).
   *
   * @return \stdClass[] Usuários ativos, indexados por id.
   */
  public static function gerentes_categoria($categoriaid) {
    global $DB;
    $papeis = array_keys(get_archetype_roles('manager'));
    $ctx = \context_coursecat::instance($categoriaid, IGNORE_MISSING);
    if (!$papeis || !$ctx) return [];
    list($in, $params) = $DB->get_in_or_equal($papeis, SQL_PARAMS_NAMED, 'papel');
    $params['ctx'] = $ctx->id;
    return $DB->get_records_sql(
      "SELECT DISTINCT u.*
         FROM {role_assignments} ra
         JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0
        WHERE ra.contextid = :ctx AND ra.roleid $in", $params);
  }

  /**
   * Categoria da unidade de um curso: a de 2º nível no caminho do curso.
   * Ex.: "São Paulo / FFLCH / 2026" => FFLCH.
   */
  public static function categoria_unidade_curso($courseid) {
    global $DB;
    $path = $DB->get_field_sql(
      "SELECT cc.path FROM {course} co JOIN {course_categories} cc ON cc.id = co.category WHERE co.id = :id",
      ['id' => $courseid]);
    $partes = $path ? explode('/', trim($path, '/')) : [];
    return count($partes) >= 2 ? (int) $partes[1] : null;
  }

  /** Usuários (com campos de nome) para exibir nomes em lote. */
  public static function usuarios(array $ids) {
    global $DB;
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $campos = 'id, ' . implode(', ', \core_user\fields::get_name_fields());
    return $DB->get_records_list('user', 'id', $ids, '', $campos);
  }

  // ---------------------------------------------------------------- notificações

  /**
   * Avisa o solicitante: confirmação da abertura e qualquer ação de outra
   * pessoa. Atendentes (admin/gerentes) não recebem notificações.
   */
  private static function notificar($chamado, $evento) {
    global $USER;
    $dono = \core_user::get_user($chamado->userid);
    if (!$dono || !empty($dono->deleted) || !empty($dono->suspended)) return;
    if ($evento->tipo !== 'abertura' && (int) $evento->userid === (int) $dono->id) return;

    $url = new \moodle_url('/blocks/extensao/pages/chamado.php', ['id' => $chamado->id]);
    $autor = fullname($USER);
    $acoes = [
      'abertura' => empty($chamado->courseid)
        ? 'Seu chamado foi aberto e está com a administração'
        : 'Seu chamado foi aberto e está com o atendimento da unidade',
      'comentario' => "$autor comentou no seu chamado",
      'fechamento' => "$autor fechou o seu chamado",
      'reabertura' => "$autor reabriu o seu chamado",
      'encaminhamento' => 'Seu chamado foi encaminhado à administração',
      'devolucao' => 'Seu chamado voltou ao atendimento da unidade',
    ];
    $acao = $acoes[$evento->tipo] ?? 'Seu chamado foi atualizado';
    $titulo = format_string($chamado->titulo);
    $corpo = $evento->mensagem !== '' ? self::mensagem_html($evento) : '';

    $html = '<p><b>' . s($acao) . '</b></p>'
      . '<p>Chamado #' . $chamado->id . ': ' . $titulo . '</p>'
      . ($corpo ? '<div style="border-left:3px solid #0d94ab;padding-left:10px">' . $corpo . '</div>' : '')
      . '<p><a href="' . $url->out(false) . '">Ver o chamado</a></p>';

    $msg = new \core\message\message();
    $msg->component = self::COMPONENTE;
    $msg->name = 'chamado';
    $msg->userfrom = \core_user::get_noreply_user();
    $msg->userto = $dono;
    $msg->subject = "[Suporte #{$chamado->id}] $acao: $titulo";
    $msg->fullmessage = html_to_text($html);
    $msg->fullmessageformat = FORMAT_PLAIN;
    $msg->fullmessagehtml = $html;
    $msg->smallmessage = "$acao: $titulo";
    $msg->notification = 1;
    $msg->contexturl = $url->out(false);
    $msg->contexturlname = 'Ver o chamado';
    $msg->courseid = SITEID;
    try {
      message_send($msg);
    } catch (\Throwable $e) {
      debugging('Falha ao notificar chamado ' . $chamado->id . ': ' . $e->getMessage());
    }
  }

  /**
   * Avisa os gerentes da unidade do curso que há um chamado novo
   * (primeiro nível de atendimento). Chamados sem curso não têm unidade.
   */
  private static function notificar_gerentes($chamado, $evento) {
    global $USER;
    if (empty($chamado->courseid)) return;
    $categoria = self::categoria_unidade_curso($chamado->courseid);
    if (!$categoria) return;
    $gerentes = self::gerentes_categoria($categoria);
    unset($gerentes[$USER->id]);
    if (!$gerentes) return;

    $url = new \moodle_url('/blocks/extensao/pages/chamado.php', ['id' => $chamado->id]);
    $titulo = format_string($chamado->titulo);
    $curso = format_string(get_course($chamado->courseid)->fullname);
    $corpo = $evento->mensagem !== '' ? self::mensagem_html($evento) : '';
    $html = '<p><b>Novo chamado a ser atendido</b></p>'
      . '<p>Chamado #' . $chamado->id . ': ' . $titulo . '<br>'
      . 'Solicitante: ' . s(fullname($USER)) . '<br>'
      . 'Curso: ' . $curso . '</p>'
      . ($corpo ? '<div style="border-left:3px solid #FFB500;padding-left:10px">' . $corpo . '</div>' : '')
      . '<p><a href="' . $url->out(false) . '">Atender o chamado</a></p>'
      . '<p style="color:#6a737b">Se for preciso um acesso que você não tem, use "Comentar e encaminhar ao administrador".</p>';

    foreach ($gerentes as $gerente) {
      $msg = new \core\message\message();
      $msg->component = self::COMPONENTE;
      $msg->name = 'chamado_novo';
      $msg->userfrom = \core_user::get_noreply_user();
      $msg->userto = $gerente;
      $msg->subject = "[Suporte #{$chamado->id}] Novo chamado: $titulo";
      $msg->fullmessage = html_to_text($html);
      $msg->fullmessageformat = FORMAT_PLAIN;
      $msg->fullmessagehtml = $html;
      $msg->smallmessage = "Novo chamado: $titulo";
      $msg->notification = 1;
      $msg->contexturl = $url->out(false);
      $msg->contexturlname = 'Atender o chamado';
      $msg->courseid = SITEID;
      try {
        message_send($msg);
      } catch (\Throwable $e) {
        debugging('Falha ao avisar gerente sobre o chamado ' . $chamado->id . ': ' . $e->getMessage());
      }
    }
  }
}
