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
 * @package    tool_xmldb
 * @copyright  2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This class will produce XSL documentation for the loaded XML file
 *
 * @package    tool_xmldb
 * @copyright  2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_documentation extends XMLDBAction {

    /**
     * Init method, every subclass will have its own
     */
    function init() {
        parent::init();

        // Set own custom attributes
        $this->sesskey_protected = false; // This action doesn't need sesskey protection

        // Get needed strings
        $this->loadStrings(array(
            'backtomainview' => 'tool_xmldb',
            'documentationintro' => 'tool_xmldb'
        ));
    }

    /**
     * Invoke method, every class will have its own
     * returns true/false on completion, setting both
     * errormsg and output as necessary
     */
    function invoke() {
        parent::invoke();

        $result = true;

        // Set own core attributes
        $this->does_generate = ACTION_GENERATE_HTML;

        // These are always here
        global $CFG, $XMLDB;

        // Do the job, setting $result as needed

        // Get the dir containing the file
        $dirpath = required_param('dir', PARAM_PATH);
        $dirpath = $CFG->dirroot . $dirpath;
        $path = $dirpath.'/install.xml';
        if(!file_exists($path) || !is_readable($path)) {
            return false;
        }

        // Add link to download HTML.
        $encodeddirpath = urlencode(required_param('dir', PARAM_PATH));
        $download = '<a href="index.php?action=generate_documentation&dir='.$encodeddirpath.'&download=1">Download HTML file</a>';
        $this->output = $download;

        // Add link back to home
        $b = ' <p class="centerpara buttons">';
        $b .= '&nbsp;<a href="index.php?action=main_view#lastused">[' . $this->str['backtomainview'] . ']</a>';
        $b .= '</p>';
        $this->output.=$b;

        $c = ' <p class="centerpara">';
        $c .= $this->str['documentationintro'];
        $c .= '</p>';
        $this->output.=$c;

        if(class_exists('XSLTProcessor')) {
            // Transform XML file and display it
            $doc = new DOMDocument();
            $xsl = new XSLTProcessor();

            $doc->load(dirname(__FILE__).'/xmldb.xsl');
            $xsl->importStyleSheet($doc);

            $doc->load($path);
            $this->output.=$xsl->transformToXML($doc);
            $this->output.=$b;
        } else {
            $this->output.=get_string('extensionrequired','tool_xmldb','xsl');
        }

        // Launch postaction if exists (leave this unmodified)
        if ($this->getPostAction() && $result) {
            return $this->launch($this->getPostAction());
        }

        return $result;
    }

    /**
     * Sets the headers for downloading the generated XMLDB documentation as HTML, with theme's style.
     */
    public function download_html() {
        global $OUTPUT;

        $pluginname = $this->get_plugin_name(required_param('dir', PARAM_PATH));
        $content = $this->get_xmldb_in_html();
        $style = $this->extract_style_from_header($OUTPUT->header());
        $html = $this->construct_html($pluginname, $style, $content);

        $filename = $pluginname . '_xmldb_doc' . '.html';

        header('Content-type: text/plain');
        header('Content-Disposition: attachment; filename=' . $filename);

        print $html;
    }

    /**
     * Gets plugin name, in frankenstyle format, used to set page title and file name.
     *
     * @param string $directory The directory of the XMLDB definition for the plugin.
     * @return string Plugin name.
     */
    protected function get_plugin_name($directory) {
        $pluginname = substr($directory, 1);
        $pluginname = str_replace('/db', '', $pluginname);
        $pluginname = str_replace('/', '_', $pluginname);

        return $pluginname;
    }

    /**
     * The same as in invoke(); gets the XMLDB definition in HTML.
     *
     * @return string
     */
    protected function get_xmldb_in_html() {
        global $CFG;

        $doc = new DOMDocument();
        $xsl = new XSLTProcessor();

        $dirpath = required_param('dir', PARAM_PATH);
        $dirpath = $CFG->dirroot . $dirpath;
        $path = $dirpath.'/install.xml';

        $doc->load(dirname(__FILE__).'/xmldb.xsl');
        $xsl->importStyleSheet($doc);

        $doc->load($path);

        $content = $xsl->transformToXML($doc);

        return $content;
    }

    /**
     * Extracts the style defined in the stylesheet linked in output header. First, gets the URL for the stylesheet, and then,
     * gets the content.
     * The $header parameter is the header returned by $OUTPUT->header(), but we cannot use $OUTPUT inside this function.
     *
     * @param string $header The whole header of the page, generated by $OUTPUT->header().
     * @return string The style extracted from the stylesheet linked in the header.
     */
    protected function extract_style_from_header($header) {
        global $CFG;

        $stylespath = $CFG->wwwroot . '/theme/styles.php';
        $stylepositionstart = strpos($header, $stylespath);
        $stylepositionend = strpos($header, '/all');

        $styleurl = substr($header, $stylepositionstart, $stylepositionend);
        $style = file_get_contents($styleurl);

        return $style;
    }

    /**
     * Constructs the HTML page for the received page title, style and body content.
     * A padding for the body is also defined.
     *
     * @param string $title Page title.
     * @param string $style The style extracted from header.
     * @param string $content The XMLDB definition in HTML.
     * @return string The HTML page constructed with style and body content.
     */
    protected function construct_html($title, $style, $content) {
        $bodypadding = 'body{padding:1.5em !important;}';
        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= "<title>$title</title>";
        $html .= "<style type='text/css'>$style $bodypadding</style>";
        $html .= '<body>';
        $html .= "<h1>'$title' XMLDB documentation</h1>";
        $html .= $content;
        $html .= '</body>';
        $html .= '</html>';

        return $html;
    }

}

