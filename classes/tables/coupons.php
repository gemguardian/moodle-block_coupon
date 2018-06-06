<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * this file contains the table to display coupons
 *
 * File         coupons.php
 * Encoding     UTF-8
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_coupon\tables;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * block_coupon\tables\coupons
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coupons extends \table_sql {

    /**
     * Filter to display used coupons only
     */
    const USED = 1;
    /**
     * Filter to display unused coupons only
     */
    const UNUSED = 2;
    /**
     * Filter to display all coupons
     */
    const ALL = 3;
    /**
     * Do we render the history or the current status?
     *
     * @var int
     */
    protected $ownerid;

    /**
     * Filter for coupon display
     *
     * @var int
     */
    protected $filter;
    /**
     * Localised delete string
     * @var string
     */
    protected $strdelete;
    /**
     * Localised delete confirmation string
     * @var string
     */
    protected $strdeleteconfirm;

    /**
     *
     * @var \block_coupon\filtering\filtering
     */
    protected $filtering;

    /**
     * Get filtering instance
     * @return \block_coupon\filtering\filtering
     */
    public function get_filtering() {
        return $this->filtering;
    }

    /**
     * Set filtering instance
     * @param \block_coupon\filtering\filtering $filtering
     * @return \block_coupon\tables\coupons
     */
    public function set_filtering(\block_coupon\filtering\filtering $filtering) {
        $this->filtering = $filtering;
        return $this;
    }

    /**
     * Create a new instance of the logtable
     *
     * @param int $ownerid if set, display only coupons from given owner
     * @param int $filter table filter
     */
    public function __construct($ownerid = null, $filter = 3) {
        global $USER;
        parent::__construct(__CLASS__. '-' . $USER->id . '-' . ((int)$ownerid));
        $this->ownerid = (int)$ownerid;
        $this->filter = (int)$filter;
        $this->sortable(true, 'c.senddate', 'DESC');
        $this->no_sorting('owner');
        $this->no_sorting('course');
        $this->no_sorting('cohort');
        $this->strdelete = get_string('action:coupon:delete', 'block_coupon');
        $this->strdeleteconfirm = get_string('action:coupon:delete:confirm', 'block_coupon');
    }

    /**
     * Set the sql to query the db.
     * This method is disabled for this class, since we use internal queries
     *
     * @param string $fields
     * @param string $from
     * @param string $where
     * @param array $params
     * @throws exception
     */
    public function set_sql($fields, $from, $where, array $params = null) {
        // We'll disable this method.
        throw new exception('err:statustable:set_sql');
    }

    /**
     * Display the general status log table.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     */
    public function render($pagesize, $useinitialsbar = true) {
        $columns = array('owner', 'for_user_email', 'senddate',
            'enrolperiod', 'submission_code', 'course', 'cohorts', 'groups', 'roleid', 'issend');
        if ($this->is_downloading() == '') {
            $columns[] = 'action';
        }
        switch ($this->filter) {
            case self::USED:
                array_splice($columns, 1, 0, ['usedby']);
                break;
            default:
                // Has no extra columns.
                break;
        }
        $this->define_table_columns($columns);

        // Generate SQL.
        $fields = 'c.*, ' . get_all_user_name_fields(true, 'u') . ', NULL as action';
        $from = '{block_coupon} c ';
        $from .= 'JOIN {user} u ON c.ownerid=u.id ';
        $from .= 'LEFT JOIN {role} r ON c.roleid=r.id ';
        $where = array();
        $params = array();
        if ($this->ownerid > 0) {
            $where[] = 'c.ownerid = :ownerid';
            $params['ownerid'] = $this->ownerid;
        }
        switch ($this->filter) {
            case self::USED:
                $where[] = 'claimed = 1';
                $fields .= ', ' . get_all_user_name_fields(true, 'u1', '', 'user_');
                $from .= ' JOIN {user} u1 ON c.userid=u1.id';
                break;
            case self::UNUSED:
                $where[] = 'claimed = 0';
                break;
            case self::ALL:
                // Has no extra where clause.
                break;
        }

        if (empty($where)) {
            // Prevent bugs.
            $where[] = '1 = 1';
        }

        // Add filtering rules.
        if (!empty($this->filtering)) {
            list($fsql, $fparams) = $this->filtering->get_sql_filter();
            if (!empty($fsql)) {
                $where[] = $fsql;
                $params += $fparams;
            }
        }

        parent::set_sql($fields, $from, implode(' AND ', $where), $params);
        $this->out($pagesize, $useinitialsbar);
    }

    /**
     * Render visual representation of the 'usedby' column for use in the table
     *
     * @param \stdClass $row
     * @return string time string
     */
    public function col_usedby($row) {
        global $CFG;
        // Nasty modification. Does moodle support better methods here at all??
        $obj = new \stdClass();
        foreach ($row as $k => $v) {
            if (stristr($k, 'user_') !== false) {
                $nk = str_replace('user_', '', $k);
                $obj->{$nk} = $v;
            }
        }

        $url = new \moodle_url($CFG->wwwroot . '/user/profile.php', ['id' => $row->userid]);
        return \html_writer::link($url, fullname($obj));
    }

    /**
     * Render visual representation of the 'owner' column for use in the table
     *
     * @param \stdClass $row
     * @return string time string
     */
    public function col_owner($row) {
        return fullname($row);
    }

    /**
     * Render visual representation of the 'enrolperiod' column for use in the table
     *
     * @param \stdClass $row
     * @return string enrolperiod string
     */
    public function col_enrolperiod($row) {
        static $strindefinite;
        if ($strindefinite === null) {
            $strindefinite = get_string('enrolperiod:indefinite', 'block_coupon');
        }
        return (($row->enrolperiod <= 0) ? $strindefinite : format_time($row->enrolperiod));
    }

    /**
     * Render visual representation of the 'senddate' column for use in the table
     *
     * @param \stdClass $row
     * @return string time string
     */
    public function col_senddate($row) {
        static $strimmediately;
        if ($strimmediately === null) {
            $strimmediately = get_string('report:immediately', 'block_coupon');
        }
        return (is_null($row->senddate) ? $strimmediately : userdate($row->senddate));
    }

    /**
     * Render visual representation of the 'issend' column for use in the table
     *
     * @param \stdClass $row
     * @return string time string
     */
    public function col_issend($row) {
        static $stryes;
        static $strno;
        if ($stryes === null) {
            $stryes = get_string('yes');
            $strno = get_string('no');
        }
        return (((bool)$row->issend) ? $stryes : $strno);
    }

    /**
     * Render visual representation of the 'role' column for use in the table
     *
     * @param \stdClass $row
     * @return string role string
     */
    public function col_roleid($row) {
        global $DB;
        if (empty($row->roleid)) {
            return '-';
        }
        static $roles = [];
        if (!isset($roles[$row->roleid])) {
            $role = $DB->get_record('role', ['id' => $row->roleid]);
            $roles[$row->roleid] = role_get_name($role);
        }
        return $roles[$row->roleid];
    }

    /**
     * Render visual representation of the 'cohorts' column for use in the table
     *
     * @param \stdClass $row
     * @return string time string
     */
    public function col_cohorts($row) {
        global $DB;
        $rs = array();
        $records = $DB->get_records_sql("SELECT c.id,c.name FROM {block_coupon_cohorts} cc
            LEFT JOIN {cohort} c ON cc.cohortid = c.id
            WHERE cc.couponid = ?", array($row->id));
        foreach ($records as $record) {
            $rs[] = $record->name;
        }
        return implode($this->is_downloading() ? ', ' : '<br/>', $rs);
    }

    /**
     * Render visual representation of the 'course' column for use in the table
     *
     * @param \stdClass $row
     * @return string time string
     */
    public function col_course($row) {
        global $DB;
        $rs = array();
        $records = $DB->get_records_sql("SELECT c.id,c.fullname FROM {block_coupon_courses} cc
            LEFT JOIN {course} c ON cc.courseid = c.id
            WHERE cc.couponid = ?", array($row->id));
        foreach ($records as $record) {
            $rs[] = $record->fullname;
        }
        return implode($this->is_downloading() ? ', ' : '<br/>', $rs);
    }

    /**
     * Render visual representation of the 'groups' column for use in the table
     *
     * @param \stdClass $row
     * @return string time string
     */
    public function col_groups($row) {
        global $DB;
        $rs = array();
        $records = $DB->get_records_sql("SELECT g.id,g.name FROM {block_coupon_groups} cg
            LEFT JOIN {groups} g ON cg.groupid = g.id
            WHERE cg.couponid = ?", array($row->id));
        foreach ($records as $record) {
            $rs[] = $record->name;
        }
        return implode($this->is_downloading() ? ', ' : '<br/>', $rs);
    }

    /**
     * Render visual representation of the 'timecreated' column for use in the table
     *
     * @param \stdClass $row
     * @return string time string
     */
    public function col_timecreated($row) {
        return userdate($row->timecreated);
    }

    /**
     * Render visual representation of the 'action' column for use in the table
     *
     * @param \stdClass $row
     * @return string actions
     */
    public function col_action($row) {
        $actions = array();
        $actions[] = $this->get_action($row, 'delete', true);
        return implode('', $actions);
    }

    /**
     * Return the image tag representing an action image
     *
     * @param string $action
     * @return string HTML image tag
     */
    protected function get_action_image($action) {
        global $OUTPUT;
        return '<img src="' . $OUTPUT->image_url($action, 'block_coupon') . '"/>';
    }

    /**
     * Return a string containing the link to an action
     *
     * @param \stdClass $row
     * @param string $action
     * @param bool $confirm true to enable javascript confirmation of this action
     * @return string link representing the action with an image
     */
    protected function get_action($row, $action, $confirm = false) {
        $actionstr = 'str' . $action;
        $onclick = '';
        if ($confirm) {
            $actionconfirmstr = 'str' . $action . 'confirm';
            $onclick = ' onclick="return confirm(\'' . $this->{$actionconfirmstr} . '\');"';
        }
        return '<a ' . $onclick . 'href="' . new \moodle_url($this->baseurl,
                array('action' => $action, 'itemid' => $row->id, 'sesskey' => sesskey())) .
                '" alt="' . $this->{$actionstr} .
                '">' . $this->get_action_image($action) . '</a>';
    }

    /**
     * Define columns for output table and define the headers through automated
     * lookup of the language strings.
     *
     * @param array $columns list of column names
     */
    protected function define_table_columns($columns) {
        $this->define_columns($columns);
        $headers = array();
        foreach ($columns as $name) {
            $headers[] = get_string('th:' . $name, 'block_coupon');
        }
        $this->define_headers($headers);
    }

}