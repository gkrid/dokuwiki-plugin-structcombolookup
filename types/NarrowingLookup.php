<?php
namespace dokuwiki\plugin\structcombolookup\types;

use dokuwiki\plugin\struct\meta\Column;
use dokuwiki\plugin\struct\meta\Search;
use dokuwiki\plugin\struct\meta\Value;
use dokuwiki\plugin\struct\types\Lookup;

class NarrowingLookup extends Lookup
{
    protected $config = array(
        'schema' => '',
        'field' => '',
        'narrow by' => '',
        'disable child' => false
    );

    /** @var  Column caches the referenced column */
    protected $narrowByColumn = null;

    /**
     * Get the configured lookup column
     *
     * @return Column|false
     */
    protected function getNarrowByColumn() {
        if($this->narrowByColumn !== null) return $this->narrowByColumn;
        $this->narrowByColumn = $this->getColumn($this->config['schema'], $this->config['narrow by']);
        return $this->narrowByColumn;
    }


    /**
     * Creates the options array
     *
     * @return array
     */
    protected function getNarrowByOptions() {
        $schema = $this->config['schema'];
        $column = $this->getNarrowByColumn();
        if(!$column) return array();
        $field = $column->getLabel();

        $search = new Search();
        $search->addSchema($schema);
        $search->addColumn($field);
        $search->addSort($field);

        $options = array('' => '');
        $results = $search->execute();
        foreach ($results as $result) {
            $options[$result[0]->getRawValue()] = $result[0]->getDisplayValue();
        }
        return $options;
    }

    protected function getRawValueNarrowingValue($rawvalue) {
        $schema = $this->config['schema'];
        $column = $this->getNarrowByColumn();
        if(!$column) return '';
        $field = $column->getLabel();

        $search = new Search();
        $search->addSchema($schema);
        $search->addColumn($field);
        $search->addFilter($this->config['field'], $rawvalue, '=');
        $result = $search->execute();
        if (!isset($result[0])) return '';
        return $result[0][0]->getRawValue();
    }

    /**
     * Creates the options array
     *
     * @return array
     */
    protected function getOptions() {
        $schema = $this->config['schema'];
        $column = $this->getLookupColumn();
        if(!$column) return array();
        $field = $column->getLabel();

        $narrowingColumn = $this->getNarrowByColumn();
        if(!$narrowingColumn) return array();
        $narrowingField = $narrowingColumn->getLabel();



        $search = new Search();
        $search->addSchema($schema);
        $search->addColumn($field);
        $search->addColumn($narrowingField);

        $search->addSort($field);
        $result = $search->execute();
        $pids = $search->getPids();
        $len = count($result);

        /** @var Value[][] $result */
        $options = array('' => array('', ''));
        for($i = 0; $i < $len; $i++) {
            $options[$pids[$i]] = array($result[$i][0]->getDisplayValue(), $result[$i][1]->getRawValue());
        }
        return $options;
    }


    protected function parentValueEditor($name, $rawvalue) {
        $params = array(
            'data-child-name' => $name,
            'class' => 'struct_'.strtolower($this->getClass()). '_parent',
            'onchange' => 'structcombolookup_change_narrowby(this, '.(int)$this->config['disable child'].')'
        );
        $attributes = buildAttributes($params, true);
        $html = "<select $attributes>";
        foreach($this->getNarrowByOptions() as $opt => $val) {
            if($opt == $this->getRawValueNarrowingValue($rawvalue)) {
                $selected = 'selected="selected"';
            } else {
                $selected = '';
            }

            $html .= "<option $selected value=\"" . hsc($opt) . "\">" . hsc($val) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    /**
     * A Dropdown with a single value to pick
     *
     * @param string $name
     * @param string $rawvalue
     * @return string
     */
    public function valueEditor($name, $rawvalue, $htmlID) {
        $html = $this->parentValueEditor($name, $rawvalue);

        $params = array(
            'name' => $name,
            'class' => 'struct_'.strtolower($this->getClass()) . '_child',
            'id' => $htmlID
        );
        if ($this->config['disable child']) {
            $params['disabled'] = 'disabled';
        }
        $attributes = buildAttributes($params, true);
        $html .= "<select $attributes>";
        foreach($this->getOptions() as $opt => $option) {
            list($val, $parent) = $option;
            if($opt == $rawvalue) {
                $selected = 'selected="selected"';
            } else {
                $selected = '';
            }

            $html .= "<option data-parent=\"" . hsc($parent) . "\" $selected value=\"" . hsc($opt) . "\">" . hsc($val) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }
}