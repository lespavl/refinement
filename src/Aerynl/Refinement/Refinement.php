<?php namespace Aerynl\Refinement;

use Illuminate\Support\Pluralizer;
use Config;

class Refinement
{

    /**
     * Updates refinements in session
     * @param string $refinements_name - name of the array in session, which keeps current refinements
     * @param array $new_refinements - array of new refinements
     * $new_refinements = array(
     *      $table_name1 => array(
     *          $table_column1 => array(
     *              $value1, $value2, $value3
     *          ),
     *          $table_column2 => array(
     *              $value1, $value2, $value3
     *          )
     *      )
     * );
     */
    public static function updateRefinements($refinements_name, $new_refinements)
    {
        \Session::put($refinements_name, $new_refinements);
    }

    /**
     * @param string $current_model - main model name. Its elements are being filtered.
     * @param string $session_name - name of the session array, where refinements are kept
     * @param array $eager - eager loading of models
     * @param array $additional_wheres
     * @param array $additional_joins
     * @param array $refinements_array - in case you want to filter not by session, but some specific array
     * $refinements_array = array(
     *      $table_name1 => array(
     *          $table_column1 => array(
     *              $value1, $value2, $value3
     *          ),
     *          $table_column2 => array(
     *              $value1, $value2, $value3
     *          )
     *      )
     * );
     * \Illuminate\Database\Query\Builder $query - Query Builder object
     */
    public static function getRefinedQuery($current_model, $session_name = "", $eager = array(), $additional_wheres = array(), $additional_joins = array(), $refinements_array = array())
    {
        if (empty($current_model)) return false;

        if (!is_array($eager)) $eager = array($eager);
        $query = $current_model::with($eager);
        if (!empty($additional_wheres)) {
            foreach ($additional_wheres as $additional_where) {
                if (empty($additional_where)) continue;
                $query->whereRaw($additional_where);
            }
        }

        $already_joined = array();
        if (!empty($additional_joins)) {
            foreach ($additional_joins as $additional_join) {
                if (empty($additional_join) || empty($additional_join['table_name'])
                    || empty($additional_join['right_join']) || empty($additional_join['operand'])
                    || empty($additional_join['left_join'])) continue;
                $query->join($additional_join['table_name'], $additional_join['right_join'], $additional_join['operand'], $additional_join['left_join']);
                $already_joined[] = $additional_join['table_name'];
            }
        }

        $refinements = empty($refinements_array) ? \Session::get($session_name) : $refinements_array;
        if (empty($refinements)) return $query;

        $current_table = strtolower(Pluralizer::plural($current_model));
        $config_joins = Config::get('refinement::joins');

        foreach ($refinements as $refinement_table => $refinement) {
            $refinement_model = ucfirst(Pluralizer::singular($refinement_table));

            if ($current_model != $refinement_model && !in_array($refinement_table, $already_joined)) {
                /* in this case we need to join the table to be able to filter by it */

                /* first we need to find join statement in configuration array */
                $join_statement = array();
                $join_statement_keys = array(
                    "{$current_table}|{$refinement_table}",
                    "{$refinement_table}|{$current_table}"
                );

                foreach ($join_statement_keys as $join_statement_key) {
                    if (empty($config_joins[$join_statement_key])) continue;
                    $join_statement = $config_joins[$join_statement_key];
                }

                /* if there is no record in config array for this table, skip filtering by it */
                if (empty($join_statement)) continue;

                $query = $query->join($refinement_table, $join_statement['left'], $join_statement['operand'], $join_statement['right']);
                $already_joined[] = $refinement_table;
            }

            foreach ($refinement as $refinement_column => $refinement_values) {
                $query = $query->whereIn($refinement_table . '.' . $refinement_column, $refinement_values);
            }
        }

        return $query;
    }

    /**
     * Is used for generating array of refinements options using passed scheme
     *
     * @param string $current_model - main model name. Its elements are being filtered.
     * @param array $options_scheme - scheme of options
     * $options_scheme = array(
     *      array(
     *          'parent_table' => string $parent_table_name, - filtering is based on this table
     *          'filter_column' => string $parent_table_column - column of $parent_table. filtering is based on this column
     *          'join_table' => string $join_table_name, - table, which is joined to parent table and contains option names
     *          'filter_value' => string $join_table_column - column with option names
     *      ), ...
     * );
     *
     * e.g.
     * $maintenance_options = array(
     * array( 'parent_table' => 'elements', 'join_table' => 'locations', 'filter_column' => 'location_id', 'filter_value' => 'location' ),
     * array( 'parent_table' => 'elements', 'join_table' => 'element_type', 'filter_column' => 'element_type_id',
     *      'filter_value' => 'element_type', 'additional_wheres' => array("elements.approval_pending is not NULL") ),
     * );
     * This means, that results are filtered by `elements.location_id`. And location names you can see in `locations.location` column.
     *
     * @param string $session_name - name of the session array, where refinements are kept
     * @param array $eager - eager loading of models
     * @param array $additional_wheres
     * @param array $additional_joins
     * @return array of options in the following scheme:
     * $options_array = array(
     *      array(
     *          'column_name' => string $column_name,
     *          'parent_table' => string $parent_table
     *          'title' => string $category_title
     *          'options' => array(
     *              $option_id => array(
     *                  'name' => string $option_name,
     *                  'id' => mixed $option_id,
     *                  'count' => int $number_of_results,
     *                  'checked' => bool $is_checked
     *              ),
     *              $option_id => array(
     *                  'name' => string $option_name,
     *                  'id' => mixed $option_id,
     *                  'count' => int $number_of_results,
     *                  'checked' => bool $is_checked
     *              )
     *          )
     *      ), ...
     * );
     */
    public static function generateOptionsArray($current_model, $options_scheme = array(), $session_name = "", $eager = array(), $additional_wheres = array(), $additional_joins = array())
    {
        if (empty($options_scheme) || empty($current_model)) return array();

        $options_array = array();
        $current_table = strtolower(Pluralizer::plural($current_model));
        $full_refinements_array = \Session::has($session_name) ? \Session::get($session_name) : array();
        $current_model_id = \App::make($current_model)->getKeyName();
        $config_joins = Config::get('refinement::joins');
        $titles = Config::get('refinement::titles');

        /* remember tables, which will be added by refinements function */
        $already_joined = array();
        if (!empty($additional_joins)) {
            foreach ($additional_joins as $additional_join) {
                if (empty($additional_join) || empty($additional_join['table_name'])
                    || empty($additional_join['right_join']) || empty($additional_join['operand'])
                    || empty($additional_join['left_join'])) continue;
                $already_joined[] = $additional_join['table_name'];
            }
        }

        foreach ($options_scheme as $option_key => $option_scheme) {
            $titles_key = $option_scheme['parent_table']."|".$option_scheme['filter_value'];

            $option_data = array(
                'parent_table' => $option_scheme['parent_table'],
                'column_name' => $option_scheme['filter_column'],
                'title' => !empty($titles[$titles_key]) ? $titles[$titles_key] : ucfirst($option_scheme['filter_value']),
                'options' => array()
            );

            /* generating refinement array without our current option selected */
            $option_refinements_array = $full_refinements_array;
            $selected_options_array = array();
            if(!empty($option_refinements_array[$option_scheme['parent_table']][$option_scheme['filter_column']])) {
                $selected_options_array = $option_refinements_array[$option_scheme['parent_table']][$option_scheme['filter_column']];
                unset($option_refinements_array[$option_scheme['parent_table']][$option_scheme['filter_column']]);
            }

            if(empty($option_refinements_array[$option_scheme['parent_table']])) {
                unset($option_refinements_array[$option_scheme['parent_table']]);
            }

            /* add additional wheres */
            $option_additional_wheres = $additional_wheres;
            if(!empty($option_scheme['additional_wheres'])) {
                foreach($option_scheme['additional_wheres'] as $option_where) {
                    if(in_array($option_where, $option_additional_wheres)) continue;
                    $option_additional_wheres[] = $option_where;
                }
            }

            /* generate query with updated refinements */
            $option_query = self::getRefinedQuery($current_model, "", $eager, $option_additional_wheres, $additional_joins, $option_refinements_array);

            /* add option parent table if we haven't joined before */
            $option_parent_model = ucfirst(Pluralizer::singular($option_scheme['parent_table']));
            if ($current_model != $option_parent_model && empty($option_refinements_array[$option_scheme['parent_table']])
                && !in_array($option_scheme['parent_table'], $already_joined)) {

                /* first we need to find join statement in configuration array */
                $join_statement = array();
                $join_statement_keys = array(
                    "{$current_table}|{$option_scheme['parent_table']}",
                    "{$option_scheme['parent_table']}|{$current_table}"
                );

                foreach ($join_statement_keys as $join_statement_key) {
                    if (empty($config_joins[$join_statement_key])) continue;
                    $join_statement = $config_joins[$join_statement_key];
                }

                /* if there is no record in config array for this table, skip filtering by it */
                if (empty($join_statement)) continue;

                $option_query = $option_query->join($option_scheme['parent_table'], $join_statement['left'], $join_statement['operand'], $join_statement['right']);
            }

            /* add option child table if needed */
            if (!empty($option_scheme['join_table']) && $current_table != $option_scheme['join_table']) {
                $join_statement = array(
                    'left' => "{$option_scheme['parent_table']}.{$option_scheme['filter_column']}",
                    'operand' => "=",
                    'right' => "{$option_scheme['join_table']}.id"
                );

                $option_query = $option_query->leftJoin($option_scheme['join_table'],
                    $join_statement['left'], $join_statement['operand'], $join_statement['right']);
            }

            /* add specific for this option selects */
            if(empty($option_scheme['join_table'])) {
                $option_name = $option_scheme['parent_table'] . "." . $option_scheme['filter_value'];
                $option_id = $option_scheme['parent_table'].".".$option_scheme['filter_value'];
            } else {
                $option_name = $option_scheme['join_table'] . "." . $option_scheme['filter_value'];
                $option_id = $option_scheme['join_table'].".id";
            }

            /* TODO: a soon as this issue is fixed, rewrite to have options counted by sql https://github.com/sleeping-owl/with-join/issues/10 */
            $option_query = $option_query->select(
                \DB::raw("COUNT(1) as option_count, {$option_name} as option_name, {$option_id} as option_id")
            )->groupBy($option_id, $current_table.".".$current_model_id)->orderBy($option_name);

            /* finally getting records */
            $options_records = self::getArrayFromQuery($option_query);
            foreach ($options_records as $option_record) {
                if(empty($option_data['options'][$option_record->option_id])) {
                    $option_data['options'][$option_record->option_id] = array(
                        'name' => $option_record->option_name,
                        'id' => $option_record->option_id,
                        'count' => 0,
                        'checked' => in_array($option_record->option_id, $selected_options_array)
                    );
                }
                $option_data['options'][$option_record->option_id]['count'] += $option_record->option_count;
            }

            $options_array[] = $option_data;
        }

        return $options_array;
    }

    /**
     * Is used for quick selecting of big number of records from database without creating ORM objects
     * @param \Illuminate\Database\Query\Builder $query - Query Builder object
     * @return array $results - results of query
     */
    private static function getArrayFromQuery($query)
    {
        $sql = $query->toSql();

        foreach ($query->getBindings() as $binding) {
            $value = is_numeric($binding) ? $binding : "'" . $binding . "'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        $results = \DB::select($sql);

        return $results;
    }
}