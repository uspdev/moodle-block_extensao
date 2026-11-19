<?php

namespace block_extensao\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Trava campos das configurações dos cursos criados pelo plugin.
 *
 * Os dados (nome completo, nome breve, categoria, número de identificação e
 * descrição) vêm do Apolo e não devem ser alterados por docentes ou gerentes.
 * O formulário de edição de curso do Moodle já congela cada um desses campos
 * quando o usuário não tem a capability correspondente; aqui as capabilities
 * são proibidas (CAP_PROHIBIT) no contexto do curso. Site admins não são
 * afetados, pois ignoram capabilities.
 */
class TravaCurso {

  /** Capabilities proibidas => campo travado no formulário do curso. */
  const CAPABILITIES = [
    'moodle/course:changefullname',   // Nome completo do curso
    'moodle/course:changeshortname',  // Nome breve do curso
    'moodle/course:changecategory',   // Categoria do curso
    'moodle/course:changeidnumber',   // Número de identificação do curso
    'moodle/course:changesummary',    // Descrição (sumário e imagem do curso)
  ];

  /** Arquétipos de papéis que recebem a trava. */
  const ARQUETIPOS = ['manager', 'coursecreator', 'editingteacher', 'teacher'];

  /**
   * Aplica a trava em um curso.
   *
   * @param int $courseid Id do curso no Moodle.
   * @return bool Falso se o curso não existir.
   */
  public static function aplicar($courseid) {
    $context = \context_course::instance($courseid, IGNORE_MISSING);
    if (!$context) return false;

    foreach (self::papeis() as $roleid) {
      foreach (self::CAPABILITIES as $cap) {
        assign_capability($cap, CAP_PROHIBIT, $roleid, $context->id, true);
      }
    }
    $context->mark_dirty();
    return true;
  }

  /**
   * Papéis a travar: os dos arquétipos acima e qualquer outro papel que tenha
   * alguma das capabilities permitida no sistema.
   *
   * @return int[] Ids dos papéis.
   */
  private static function papeis() {
    $roleids = [];
    foreach (self::ARQUETIPOS as $arquetipo) {
      foreach (get_archetype_roles($arquetipo) as $role) $roleids[$role->id] = true;
    }
    $sistema = \context_system::instance();
    foreach (self::CAPABILITIES as $cap) {
      foreach (get_roles_with_capability($cap, CAP_ALLOW, $sistema) as $role) $roleids[$role->id] = true;
    }
    return array_keys($roleids);
  }

  /**
   * Aplica a trava em todos os cursos já criados pelo plugin.
   *
   * @return int Quantidade de cursos travados.
   */
  public static function aplicar_todos() {
    global $DB;
    $ids = $DB->get_fieldset_sql(
      "SELECT DISTINCT t.id_moodle
         FROM {block_extensao_turma} t
         JOIN {course} c ON c.id = t.id_moodle
        WHERE t.id_moodle IS NOT NULL AND t.id_moodle > 0"
    );
    $total = 0;
    foreach ($ids as $id) {
      if (self::aplicar((int) $id)) $total++;
    }
    return $total;
  }
}
