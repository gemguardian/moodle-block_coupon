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
 * Coupon overview(s) implementation for use with block_coupon
 *
 * File         reports.php
 * Encoding     UTF-8
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      R.J. van Dongen <rogier@sebsoft.nl>
 * @author      Sebastian Berm <sebastian@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_coupon\controller;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use html_writer;

/**
 * block_coupon\manager\reports
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      R.J. van Dongen <rogier@sebsoft.nl>
 * @author      Sebastian Berm <sebastian@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reports {

    /**
     * @var \moodle_page
     */
    protected $page;
    /**
     * @var \core_renderer
     */
    protected $output;
    /**
     * @var \block_coupon_renderer
     */
    protected $renderer;

    /**
     * Create new manager instance
     * @param \moodle_page $page
     * @param \core\output_renderer $output
     * @param \core_renderer|null $renderer
     */
    public function __construct($page, $output, $renderer = null) {
        $this->page = $page;
        $this->output = $output;
        $this->renderer = $renderer;
    }

    /**
     * Execute page request
     */
    public function execute_request() {
        $this->process_progress_report();
    }

    /**
     * Display progress report
     */
    protected function process_progress_report() {
        global $USER;
        $ownerid = (has_capability('block/coupon:viewallreports', $this->page->context) ? 0 : $USER->id);

        // Table instance.
        $table = new \block_coupon\tables\report($ownerid);
        $table->baseurl = $this->page->url;

        $filtering = new \block_coupon\tablefilters\report($this->page->url);
        $table->set_filtering($filtering);

        $table->is_downloadable(true);
        $table->show_download_buttons_at(array(TABLE_P_BOTTOM, TABLE_P_TOP));
        $download = optional_param('download', '', PARAM_ALPHA);
        if (!empty($download)) {
            $table->is_downloading($download, 'couponreport', 'couponreport');
            $table->render(25);
            exit;
        }

        $id = $this->page->url->param('id');

        echo $this->output->header();
        echo html_writer::start_div('block-coupon-container');
        echo html_writer::start_div();
        echo $this->renderer->get_tabs($this->page->context, 'cpreport', array('id' => $id));
        echo html_writer::end_div();
        $filtering->display_add();
        $filtering->display_active();
        echo $table->render(25);
        echo html_writer::end_div();
        echo $this->output->footer();
    }


    /**
     * Return new url based on the current page-url
     *
     * @param array $mergeparams
     * @return \moodle_url
     */
    protected function get_url($mergeparams = []) {
        $url = $this->page->url;
        $url->params($mergeparams);
        return $url;
    }

}
