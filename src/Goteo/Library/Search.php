<?php

namespace Goteo\Library {

    use Goteo\Model\Project;
    use Goteo\Application\Config;

	/*
	 * Clase para realizar búsquedas de proyectos
	 *
	 */
    class Search {

        /**
         * Metodo para realizar una busqueda por parametros
         * @param array multiple $params 'category', 'location', 'reward', 'query', node', 'status'
         * @param bool showall si true, muestra tambien proyectos en estado de edicion y revision
         * @return array results
         */
		public static function params ($params, $showall = false, $limit = null) {

            $results = array();
            $where   = array();
            $values  = array(':lang' => \LANG);

            // @TODO : estos siguientes deberían ser JOINs
            if ($category = $params['category']) {
                if(!is_array($category)) $category = array($category);
                $category = array_filter($category, function($v){return is_numeric($v);});
                if($category) {
                    $where[] = 'AND project.id IN (
                                    SELECT distinct(project)
                                    FROM project_category
                                    WHERE category IN ('. implode(', ', $category) . ')
                                )';
                }
            }

            if ($location = $params['location']) {
                if(!is_array($location)) $location = array($location);
                $location = array_map(function($v){return "'" .addslashes($v) . "'";}, $location);
                if($location) {
                    $where[] = 'AND MD5(project.project_location) IN ('. implode(', ', $location) .')';
            }

            if ($reward = $params['reward']) {
                if(!is_array($reward)) $reward = array($reward);
                }
                $reward = array_map(function($v){return "'" .addslashes($v) . "'";}, $reward);
                if($reward) {
                    $where[] = 'AND project.id IN (
                                    SELECT DISTINCT(project)
                                    FROM reward
                                    WHERE icon IN ('. implode(', ', $reward) . ')
                                    )';
                }
            }

            if (!empty($params['query'])) {
                $where[] = ' AND (project.name LIKE :text
                                OR project.description LIKE :text
                                OR project.motivation LIKE :text
                                OR project.about LIKE :text
                                OR project.goal LIKE :text
                                OR project.related LIKE :text
                                OR project.keywords LIKE :text
                                OR project.location LIKE :text
                            )';
                $values[':text'] = "%{$params['query']}%";
            }


            if (!empty($params['node'])) {
                $where[] = ' AND project.node = :node';
                $values[':node'] = Config::get('current_node');
            }

            if (!empty($params['status'])) {
                $where[] = ' AND project.status = :status';
                $values[':status'] = $params['status'];
            }

            $minstatus = ($showall) ? '1' : '2';
            $maxstatus = ($showall) ? '4' : '7';

            $different_select="project.popularity as popularity,";

            if(Project::default_lang(\LANG)=='es') {
                $different_select2=" IFNULL(project_lang.description, project.description) as description";
            }
            else {
                $different_select2=" IFNULL(project_lang.description, IFNULL(eng.description, project.description)) as description";
                $eng_join=" LEFT JOIN project_lang as eng
                                ON  eng.id = project.id
                                AND eng.lang = 'en'";
            }

            $sql ="
                SELECT
                    project.id as project,
                    $different_select2,
                    project.status as status,
                    project.published as published,
                    project.created as created,
                    project.updated as updated,
                    project.success as success,
                    project.closed as closed,
                    project.mincost as mincost,
                    project.maxcost as maxcost,
                    project.amount as amount,
                    project.image as image,
                    project.num_investors as num_investors,
                    project.num_messengers as num_messengers,
                    project.num_posts as num_posts,
                    project.days as days,
                    project.name as name,
                    $different_select
                    user.id as user_id,
                    user.name as user_name,
                    project_conf.noinvest as noinvest,
                    project_conf.one_round as one_round,
                    project_conf.days_round1 as days_round1,
                    project_conf.days_round2 as days_round2
                FROM  project
                INNER JOIN user
                    ON user.id = project.owner
                LEFT JOIN project_conf
                    ON project_conf.project = project.id
                LEFT JOIN project_lang
                            ON  project_lang.id = project.id
                            AND project_lang.lang = :lang
                $eng_join
                WHERE project.status > $minstatus
                    AND project.status < $maxstatus
                ";

            if (!empty($where)) {
                $sql .= implode ('
                ', $where);
            }

            $sql .= " ORDER BY project.status ASC, project.published DESC";
            // Limite
            if (!empty($limit) && \is_numeric($limit)) {
                $sql .= " LIMIT $limit";
            }

           // die(\sqldbg($sql, $values));

            try {
                $query = Project::query($sql, $values);
                foreach ($query->fetchAll(\PDO::FETCH_OBJ) as $row) {
                    $results[] = Project::getWidget($row);
                }
                return $results;
            } catch (\PDOException $e) {
                throw new Exception('Fallo la sentencia de busqueda');
            }
		}

	}

}
