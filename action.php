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

}

