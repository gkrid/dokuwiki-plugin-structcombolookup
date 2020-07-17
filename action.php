<?php
/**
 * DokuWiki Plugin structcombolookup (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <it@rid.pl>
 */

// must be run within Dokuwiki
use dokuwiki\plugin\struct\meta\Search;
use dokuwiki\plugin\structcombolookup\types\NarrowingLookup;

if (!defined('DOKU_INC')) {
    die();
}

class action_plugin_structcombolookup extends DokuWiki_Action_Plugin
{

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('PLUGIN_STRUCT_TYPECLASS_INIT', 'BEFORE', $this, 'handle_plugin_struct_typeclass_init');
        $controller->register_hook('PLUGIN_BUREAUCRACY_TEMPLATE_SAVE', 'BEFORE', $this, 'handle_lookup_fields');
    }

    /**
     * [Custom event handler which performs action]
     *
     * Called for event:
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handle_plugin_struct_typeclass_init(Doku_Event $event, $param)
    {
        //MIGRATION FIX
        $this->migration_fix();
        //END OF MIGRATION FIX
        $event->data['ComboLookup'] = 'dokuwiki\\plugin\\structcombolookup\\types\\ComboLookup';
        $event->data['NarrowingLookup'] = 'dokuwiki\\plugin\\structcombolookup\\types\\NarrowingLookup';

    }

    public function handle_lookup_fields(Doku_Event $event, $param) {
        /** @var helper_plugin_struct_field $field */
        foreach($event->data['fields'] as $field) {
            if(!is_a($field, 'helper_plugin_struct_field')) continue;
            if(!$field->column->getType() instanceof NarrowingLookup) continue;

            $rawvalue = $field->getParam('value');

            $config = $field->column->getType()->getConfig();
            $search = new Search();
            $search->addSchema($config['schema']);

            $schema = $search->getSchemas()[0];
            if ($schema->isLookup()) {
                $id = '%rowid%';
            } else {
                $id = '%pageid%';
            }

            $search->addColumn($config['narrow by']);
            $search->addFilter($id, $rawvalue, '=');
            $result = $search->execute();
            //cannot determine parent
            if (!isset($result[0][0])) continue;
            $parentValue = $result[0][0]->getDisplayValue();

            $schemaName = $field->column->getTable();
            $colLabel = $field->column->getLabel();
            $key = "$schemaName.$colLabel.narrowBy";
            $event->data['patterns'][$key] = "/(@@|##)$schemaName\\.$colLabel\\.narrowBy\\1/";
            $event->data['values'][$key] = $parentValue;
        }
        return true;
    }

    public function migration_fix() {
        /** @var \helper_plugin_struct_db $helper */
        $helper = plugin_load('helper', 'struct_db');
        $sqlite = $helper->getDB();

        // check if we have already migrated
        $sql = 'SELECT val FROM opts WHERE opt=?';
        $res = $sqlite->query($sql, 'structcombolookup_updated');
        $val = $sqlite->res2single($res);
        if ($val == 1) return;

        // get latest versions of schemas with islookup property
        $sql = "SELECT MAX(id) AS id, tbl FROM schemas
                    GROUP BY tbl
            ";
        $res = $sqlite->query($sql);
        $schemas = $sqlite->res2arr($res);

        $sqlite->query('BEGIN TRANSACTION');
        $ok = true;

        // Step 2: transfer data back from original tables (temp_*)
        foreach ($schemas as $schema) {
            $name = $schema['tbl'];
            $sid = $schema['id'];

            // introduce composite ids in lookup columns
            $s = $this->getLookupColsSql($sid);
            $res = $sqlite->query($s);
            $cols = $sqlite->res2arr($res);

            if ($cols) {
                foreach ($cols as $col) {
                    $colno = $col['COL'];
                    $colname = "col$colno";
                    // lookup fields pointing to pages have to be migrated first!
                    // they rely on a simplistic not-a-number check, and already migrated lookups pass the test!
                    $f = 'UPDATE data_%s SET %s = \'["\'||%s||\'",0]\' WHERE %s != \'\' AND CAST(%s AS DECIMAL) != %s';
                    $s = sprintf($f, $name, $colname, $colname, $colname, $colname, $colname);
                    $ok = $ok && $sqlite->query($s);
                    if (!$ok) return false;
                    // multi_
                    $f = 'UPDATE multi_%s SET value = \'["value",0]\' WHERE colref = %s AND CAST(value AS DECIMAL) != value';
                    $s = sprintf($f, $name, $colno);
                    $ok = $ok && $sqlite->query($s);
                    if (!$ok) return false;

                    // simple lookup fields
                    $s = "UPDATE data_$name SET col$colno = '[" . '""' . ",'||col$colno||']' WHERE col$colno != '' AND CAST(col$colno AS DECIMAL) = col$colno";
                    $ok = $ok && $sqlite->query($s);
                    if (!$ok) return false;
                    // multi_
                    $s = "UPDATE multi_$name SET value = '[" . '""' . ",'||value||']' WHERE colref=$colno AND CAST(value AS DECIMAL) = value";
                    $ok = $ok && $sqlite->query($s);
                    if (!$ok) return false;
                }
            }
        }

        // save information about update
        $s = 'INSERT INTO opts(opt,val) VALUES ("structcombolookup_updated", 1)';
        $ok = $ok && $sqlite->query($s);

        if (!$ok) {
            $sqlite->query('ROLLBACK TRANSACTION');
            return false;
        }
        $sqlite->query('COMMIT TRANSACTION');
        return true;
    }

    /**
     * Returns a select statement to fetch Lookup columns in the current schema
     *
     * @param int $sid Id of the schema
     * @return string SQL statement
     */
    protected function getLookupColsSql($sid)
    {
        return "SELECT C.colref AS COL, T.class AS TYPE
                FROM schema_cols AS C
                LEFT OUTER JOIN types AS T
                    ON C.tid = T.id
                WHERE C.sid = $sid
                AND (TYPE = 'ComboLookup' OR TYPE = 'NarrowingLookup')
            ";
    }

}

